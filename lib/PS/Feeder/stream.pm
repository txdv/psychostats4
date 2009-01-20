#
#	This file is part of PsychoStats.
#
#	Written by Jason Morriss <stormtrooper@psychostats.com>
#	Copyright 2008 Jason Morriss
#
#	PsychoStats is free software: you can redistribute it and/or modify
#	it under the terms of the GNU General Public License as published by
#	the Free Software Foundation, either version 3 of the License, or
#	(at your option) any later version.
#
#	PsychoStats is distributed in the hope that it will be useful,
#	but WITHOUT ANY WARRANTY; without even the implied warranty of
#	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#	GNU General Public License for more details.
#
#	You should have received a copy of the GNU General Public License
#	along with PsychoStats.  If not, see <http://www.gnu.org/licenses/>.
#
#	$Id$
#
package PS::Feeder::stream;

use strict;
use warnings;
use base qw( PS::Feeder );

use util qw( :net abbrnum commify expandlist compacttime );
use FileHandle;
use POSIX;
use IO::Socket::INET;
use IO::Select;
use Time::Local;
use List::Util qw( first );

our $VERSION = '4.00.' . (('$Rev$' =~ /(\d+)/)[0] || '000');

sub init {
	my $self = shift;
	my %args = @_;
	$self->SUPER::init(%args) || return undef;

	# allow alternate bind IP:port to listen on from the command line
	$self->{bindip}   = $args{bindip};
	$self->{bindport} = $args{bindport};

	$self->{listen_on} = 'unknown';

	$self->{_curlog} = '';
	$self->{_curline} = 0;
	$self->{_protocol} = 'stream';

	$self->{_allowed_hosts} = undef;
	$self->{_allowed_cache} = {};
	$self->{_not_allowed} = {};

	$self->{_clients} = {};				# hash of known clients
	$self->{_clients_cleanup_interval} = 60;	# how often to clean stale clients
	$self->{_clients_last_cleaned} = time;		# last time clients were cleaned up
	$self->{_clients_threshold} = 60 * 30;		# total seconds a client can be stale

	# Testing, to make sure the received stream matches the sent stream.
	#;;;$self->{_file} = 'received';
	#;;;if ($self->{_file}) {
	#;;;	$self->{fh} = new FileHandle('>' . $self->{_file});
	#;;;	if (!defined $self->{fh}) {
	#;;;		die "Error opening output file for streamed data: $!";
	#;;;	}
	#;;;	$self->{fh}->autoflush(1);
	#;;;}

	$self->init_acl;
	return $self->_connect;
}

# initialize our ACL for allowed hosts
sub init_acl {
	my $self = shift;
	my $opts = $self->{logsource}{options} || return;
	my @allowed = ();
	my @lines = split(/\r?\n/, $opts);
	chomp(@lines);
	
	# each line is: CIDR/bits [:] list,of,ports
	while (my $line = shift @lines) {
		$line =~ s/^\s+//;
		$line =~ s/\s+$//;
		my ($cidr, $list) = split(/[\s:]+/, $line, 2);
		my ($ipstr,$bits) = split(/\//, $cidr, 2);
		unless ($ipstr =~ /^[0-9\.]+$/) {
			$::ERR->warn("Ignoring invalid IP in access-list: '$ipstr'.");
			next;
		}
		my $ip = ip2int($ipstr);
		my $ports = expandlist($list);
		$bits = 32 if !$bits or $bits > 32 or $bits < 1;
		$ports = undef unless @$ports; 

		my $network = ip2int(ipnetwork($ip, $bits)) || next;
		my $broadcast = ip2int(ipbroadcast($ip, $bits)) || next;
		
		$self->{_allowed_hosts} ||= [];
		push(@{$self->{_allowed_hosts}}, {
			network 	=> $network,
			broadcast 	=> $broadcast,
			ports		=> $ports
		});
	}
}

sub bindhost { $_[0]->{bindhost} }
sub bindport { $_[0]->{bindport} }

# setup our socket for listening to incoming log streams
sub _connect {
	my $self = shift;

	# override listen IP:Port from command line
	$self->{bindhost} = $self->{bindip} || $self->host;
	$self->{bindport} ||= $self->port;

	my $host = $self->{bindhost};
	my $port = $self->{bindport};
	
	# if the host is 127.0.0.1 then do not bind to any address, this will
	# listen on all interfaces. Using 'localhost' will only listen on
	# the localhost (no external connections).
	$host = undef if $host eq '127.0.0.1';
	$self->{socket} = new IO::Socket::INET(
		Proto => 'udp',
		LocalHost => $host,
		LocalPort => $port
	);
	$host = '*' unless defined $host;
	$self->{listen_on} = $host . ':' . $port;

	if (!$self->{socket}) {
		$self->{error} = "Error binding to local port $self->{listen_on}: $@";
		return undef;
	}

	$self->{select} = new IO::Select($self->{socket});

	$self->verbose("[$self] Listening on socket udp://$self->{listen_on} ...");

	if ($self->{_allowed_hosts}) {
		$self->verbose("Access control enabled for " . $self->allowed_hosts . " networks.");
	} else {
		$self->verbose("No ACL configured; All log streams will be allowed.");
	}

	return 1;
}

# returns the total hosts allowed
sub allowed_hosts {
	my ($self) = @_;
	return scalar @{$self->{_allowed_hosts}};
}

# clean out clients that are stale
sub _cleanup_clients {
	my ($self) = @_;
	foreach my $cl (keys %{$self->{_clients}}) {
		my $diff = time - $self->{_clients}{$cl}{last};
		if ($diff > $self->{_clients_threshold}) {
			$self->verbose("-- Removing stale client $cl (Idle " . compacttime($diff, 'mm:ss') . " minutes)");
			$self->_delete_client($cl);
		}
	}
	$self->{_clients_last_cleaned} = time;
}

# cleans up and deletes a client
sub _delete_client {
	my ($self, $cl) = @_;
	delete $self->{_clients}{$cl};
}

# creates a new client
sub _create_client {
	my ($self, $ip, $port, $line) = @_;
	my $cl;
	$self->{_clients}{"$ip:$port"} = { };
	$cl = $self->{_clients}{"$ip:$port"};
	$cl->{first} = time;
	$cl->{last}  = time;
	$cl->{lines} = 1;
	$cl->{bytes} = length($line);
	#$cl->{event} = $line;
}

# updates the counter stats for a client
sub _update_client {
	my ($self, $ip, $port, $line) = @_;
	my $cl = $self->{_clients}{"$ip:$port"};
	$cl->{last} = time;
	$cl->{lines}++;
	$cl->{bytes} += length($line);
}

# show some statistics of the connected clients
sub echo_processing {
	my ($self) = @_;
	my $total = keys %{$self->{_clients}};
	my $time = POSIX::strftime('%T', localtime);
	my $s = $total == 1 ? '' : 's';		# I'm OCD when it comes to outputting plurals.
	$::ERR->verbose("[$time] Processing $total stream$s on $self->{listen_on} (" .
			$self->lines_per_second . " lps / " .
			$self->bytes_per_second(1) . ")"
	);
	# show some more detail regarding our known clients
	if ($self->{_verbose}) {
		my $total_bytes = 0;
		my $total_lines = 0;
		foreach my $key (keys %{$self->{_clients}}) {
			my $ipport = sprintf("%-21s", $key);
			my $cl = $self->{_clients}{$key};
			my $elapsed = compacttime(time - $cl->{first});
			my ($bytes, $kb) = split(' ', abbrnum($cl->{bytes}, 1));
			my $lines = $cl->{lines};
			my $idle_time = time - $cl->{last};
			my $idle = compacttime($idle_time, 'mm:ss');
			$::ERR->verbose(sprintf(" %-21s [%8s] [I %5s] [%6.1f %-2s] [%s line%s]",
				$key,
				$elapsed,
				$idle_time < 2 ? '--:--' : $idle,
				$bytes, $kb,
				commify($lines), $lines == 1 ? '' : 's'
			));
			$total_bytes += $cl->{bytes};
			$total_lines += $cl->{lines};
		}
		if (keys %{$self->{_clients}} > 1) {
			$::ERR->verbose(sprintf(" Total %s [%7s] [%d lines]",
				'.' x 36,
				abbrnum($total_bytes),
				$total_lines
			));
		}
	}
}

# Returns true if this Feeder has an event pending (non-blocking).
# This is used from the caller to determine if something is pending w/o
# calling next_event directly (which will block up to a second).
sub has_event {
	my $self = shift;
	if (time - $self->{_lastprint} > $self->{_lastprint_threshold}) {
		$self->echo_processing(1);
		$self->{_lastprint} = time;
	}
	return ($self->{select} and $self->{select}->can_read(0)) ? 1 : 0;
}

# timeout defaults to 1, if 0 is returned then this sub won't block and will
# return with an undef if no event is pending.
sub next_event {
	my ($self, $timeout) = @_;
	my $line;
	my ($peername, $port, $packedip, $ip, $head);

	# the stream never stops, except for GRACEFUL_EXIT, -maxlogs, -maxlines
	while (1) {
		#return undef if $::GRACEFUL_EXIT > 0;

		$line = undef;
		if (my ($s) = $self->{select}->can_read(defined $timeout ? $timeout : 1)) {
			$s->recv($line, 1500);
			next unless $line;
			$peername = $s->peername || next;
			($port, $packedip) = sockaddr_in($peername);
			$ip = inet_ntoa($packedip);
			my $cl = $ip . ':' . $port;
			if (!$self->allowed($ip,$port)) {
				# only report the unauthorized attempt once...
				if (!$self->{_not_allowed}{$cl}) {
					$self->warn("Unauthorized stream from '$cl' will be ignored!");
					$self->{_not_allowed}{$cl} = 1;
				}
				return undef;
			}

			# keep track of traffic stats for each client
			if (!$self->{_clients}{$cl}) {
				my ($myport, $myaddr) = sockaddr_in($s->sockname);
				my $myip = inet_ntoa($myaddr);
				$myip = '*' if $myip eq '0.0.0.0';
				$self->verbose("++ New stream started from $cl on $myip:$myport");
				$self->_create_client($ip, $port, $line);
			} else {
				$self->_update_client($ip, $port, $line);
			}

			# if the line does not normalize properly, skip it.
			$line = $self->normalize_line($line) || last;
			#;;; $self->{fh}->print($line) if $self->{fh};	# _file
			++$self->{_curline};
			
			# keep track of who the last event was from
			$self->{remote_addr} = $ip;
			$self->{remote_port} = $port;

			if ($self->{_echo}) {
				print sprintf("%-22s", $ip.':'.$port) . $line;
			}
			
			if ($self->{_verbose}) {
				$self->{_totalbytes} += length($line);
				$self->{_totallines}++;
				$self->{_lastprint_bytes} += length($line);
	
				if (time - $self->{_lastprint} > $self->{_lastprint_threshold}) {
					$self->echo_processing(1);
					$self->{_lastprint} = time;
				}
			}

			# break endless loop since we got an event...
			last;
		} else {
			# if timeout was defined and is ZERO, then we won't
			# block and wait for the next event. Just return.
			last if defined $timeout and $timeout == 0;
			
			# no events are available, report progress as needed.
			if (time - $self->{_lastprint} > $self->{_lastprint_threshold}) {
				$self->echo_processing(1);
				$self->{_lastprint} = time;
			}
		}
	}
	
	# cleanup known clients
	if (time - $self->{_clients_last_cleaned} > $self->{_clients_cleanup_interval}) {
		$self->_cleanup_clients;
	}
	
	# return the event and the server if in array context
	return wantarray ? ($line, scalar $self->server) : $line;
}

# streams don't really have to do anything to restore state.
sub restore_state {
	#my ($self, $db, $max_time) = @_;
	#my ($st, $state);
	#my $now = timegm(localtime);
	#$db ||= $self->db;
	#$max_time = 60 unless defined $max_time;
	#
	## load the generic state information for this logsource
	#$st = $db->prepare('SELECT last_updated FROM t_state WHERE id=?');
	#$st->execute($self->id) or return;	# SQL error ...
	#$state = $st->fetchrow_hashref;
	#$st->finish;
	#
	## if there's no state, or the time elapsed since the state was saved
	## is more than about 1 minute then ignore it.
	#return 1 unless ref $state eq 'HASH' and $now - $state->{last_updated} < $max_time;

	# nothing to do ..... 

	return 1;
}

# returns the last known server that sent an event packet
sub server {
	my @parts = @{$_[0]}{qw( remote_addr remote_port )};
	return wantarray ? @parts : join(':', @parts);
}

# returns true if the host IP and Port given is allowed.
# If no hosts are configured then all hosts are allowed.
sub allowed {
	my ($self, $ip, $port) = @_;

	# explicitly allow all if no allowed list is defined
	return 1 unless defined $self->{_allowed_hosts};

	my $key = $ip . ':' . $port;

	# quickly check the cache for the IP:Port
	if (exists $self->{_allowed_cache}{$key}) {
		return $self->{_allowed_cache}{$key};
	}
	
	# loop through the ACL for a matching network and port
	$ip = ip2int($ip);
	my $matched = 0;
	foreach my $acl (@{$self->{_allowed_hosts}}) {
		if ($ip >= $acl->{network} and $ip <= $acl->{broadcast}) {
			if (!defined $acl->{ports}) {
				# if no ports are defined then allow all
				$matched = 1;
			} elsif (first { $_ == $port } @{$acl->{ports}}) {
				# must match one of the ports configured
				$matched = 1;
			}
			last if $matched;
		}
	}

	# add the IP to the cache for quick and repetitive lookups
	$self->{_allowed_cache}{$key} = $matched;
	return $matched;
}

# returns the 'id' of a logsource if it exists in the database already.
# the criteria used to search is the host and port (Streams)
sub logsource_exists {
	my ($self, $logsource) = @_;
	my $db = $self->db;

	# prepare a new statement to find the logsource.	
	if (!$db->prepared('find_logsource_stream')) {
		$db->prepare('find_logsource_stream',
			"SELECT id FROM t_config_logsources WHERE type=? AND host=? AND port=?"
		);
	}
	
	my $exists = $db->execute_selectcol('find_logsource_stream', @$logsource{qw( type host port )});
	return $exists;
}

# stringify the actual feeder object config
sub string {
	my ($self) = @_;
	return sprintf('%s://%s:%s',
		$self->type,
		$self->host,
		$self->port
	);
}

1;
