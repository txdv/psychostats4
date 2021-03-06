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
#	FTP log support. Requires Net::FTP
#
package PS::Feeder::ftp;

use strict;
use warnings;
use base qw( PS::Feeder );
use File::Spec::Functions qw( splitpath catfile );
use File::Path;
use util qw( print_r );

our $VERSION = '4.00.' . (('$Rev$' =~ /(\d+)/)[0] || '000');

sub init {
	my $self = shift;
	my %args = @_;
	$self->SUPER::init(%args) or return;

	if ($self->{logsource}{recursive}) {
		$self->warn("FTP logsources do not support recursive directories.");
		return 0;
	}

	$self->{_pos} = 0;
	$self->{_logs} = [ ];
	$self->{_dirs} = [ ];
	$self->{_curdir} = '';
	$self->{_log_regex} = qr/\.[Ll][Oo][Gg]$/o;
	$self->{_protocol} = 'ftp';
	$self->{_idle} = time;
	$self->{max_idle} = 25;				# should be made a configurable option ...
	$self->{reconnect} = 0;

	eval "require Net::FTP";
	if ($@) {
		$self->warn("Net::FTP not installed. Unable to load $self->{class} object.");
		return;
	}

	return unless $self->_connect;

	$self->{_dirs} = [ $self->{logsource}{path} ];
	while (!scalar @{$self->{_logs}} and scalar @{$self->{_dirs}}) {
		$self->readnextdir;
	}

	return 1;
}

# establish a connection with the FTP host
sub _connect {
	my $self = shift;
	my $reconnect = shift;
	my $prot = 'ftp';
	my $host = $self->{logsource}{host} || 'localhost';
	my $user = $self->opt->username || $self->{logsource}{username} || '';
	my $pass = $self->opt->password || $self->{logsource}{password} || '';
	my $port = $self->opt->port || $self->{logsource}{port} || '21';
	my $pasv = $self->opt->passive || $self->{logsource}{passive};
	my %opts = (
		Port 	=> $port,
		Timeout => 120,
		Passive => $pasv ? 1 : 0,
		Debug 	=> $self->opt->debug ? 1 : 0
	);

	$self->{reconnect}++ if $reconnect;
	$self->info(($reconnect ? "Rec" : "C") . "onnecting to $prot://" . ($user ne '' ? "$user@" : "") . "$host:$port ...");

	$self->{ftp} = new Net::FTP($host, %opts);
	if (!$self->{ftp}) {
		$::ERR->warn("$self->{class} error connecting to FTP server: $@");
		return;
	}

	if (!$self->{ftp}->login($user, $pass)) {
		chomp(my $msg = $self->{ftp}->message);
		$::ERR->warn("Error logging into FTP server: $msg");
		return;
	}

	# get the current directory
	chomp($self->{_logindir} = $self->{ftp}->pwd);

	if ($self->{_dir} and !$self->{ftp}->cwd($self->{_dir})) {
		chomp(my $msg = $self->{ftp}->message);
		$::ERR->warn("$self->{class} error changing FTP directory: $msg");
		return;
	}

	# do transfers in binary so we can use REST commands to fast forward
	# log files when needed from a previous state.
	$self->{ftp}->binary;

	$self->info(sprintf("Connected to %s://%s%s%s%s. HOME=%s, CWD=%s",
		$prot,
		$user ? $user . '@' : '',
		$host,
		$port ne '21' ? ':' . $port : '',
		$pasv ? ' (pasv)' : '',
		$self->{_logindir},
		$self->{ftp}->pwd
	));

	return 1;
}

# reads the contents of the next directory
sub readnextdir {
	my $self = shift;
	return unless @{$self->{_dirs}};
	my $dir = shift @{$self->{_dirs}};

	$self->{_curdir} = $dir;
	$self->{_logs} = [];

	if (!$self->{ftp}->cwd($dir)) {
		chomp(my $msg = $self->{ftp}->message);
		$::ERR->warn("$self->{class} error changing FTP directory: $msg");
		return;
	}

	$self->{_logs} = [ grep { !/^\./ && !/WS_FTP/ && /$self->{_log_regex}/ } $self->{ftp}->ls ];
	# change back to original directory so our full paths will be valid
	$self->{ftp}->cwd($self->{_logindir});

	# sort the logs we have ...
	if (scalar @{$self->{_logs}}) {
		$self->{_logs} = $self->logsort($self->{_logs});
	}

	$self->info(scalar(@{$self->{_logs}}) . " logs found in " . ($self->{_curdir} || '/'));

	# skip the last log in the directory
	if ($self->{logsource}{skiplast}) {
		$self->verbose("Last log will be skipped.");
		pop(@{$self->{_logs}});
	}

	return scalar @{$self->{_logs}};
}

sub _opennextlog {
	my $self = shift;
	my $fastforward = shift;

	# close the previous log, if there was one
	undef $self->{_loghandle};
	$self->{_offsetbytes} = 0;
	$self->{_filesize} = 0;
	$self->{_lastprint} = time;
	$self->{_lastprint_bytes} = 0;


	# delete previous log if we had one, and we have 'delete' enabled in the
	# logsource_ftp config
	if ($self->delete and $self->{_curlog}) {
		$self->debug2("Deleting log $self->{_curlog}");
		if (!$self->{ftp}->delete($self->{_curlog})) {
			chomp(my $msg = $self->{ftp}->message);
			$self->debug2("Error deleting log: $msg");
		}
	}

	# we're done if the maximum number of logs has been reached
	if (!$fastforward and $self->{_maxlogs} and $self->{_totallogs} >= $self->{_maxlogs}) {
		#$self->save_state;
		return;
	}

	# no more logs in current directory, get next directory
	while (!scalar @{$self->{_logs}} and scalar @{$self->{_dirs}}) {
		$self->readnextdir;
	}
	return if !scalar @{$self->{_logs}};		# no more logs or directories to scan

	$self->{_curlog} = catfile($self->{_curdir}, shift @{$self->{_logs}});
	$self->{_curline} = 0;
	$self->{_filesize} = 0;

	# keep trying logs until we get one that works (however, chances are if
	# 1 log fails to load they all will)
	while (!$self->{_loghandle}) {
		$self->{_loghandle} = IO::File->new_tmpfile;
		if (!$self->{_loghandle}) {
			$::ERR->warn("Error creating temporary file for download: $!");
			undef $self->{_loghandle};
			last;				# that's it, we give up
		}

		$self->debug2("Downloading log $self->{_curlog}");
		if (!$self->{ftp}->get($self->{_curlog}, $self->{_loghandle})) {
			undef $self->{_loghandle};
			chomp(my $msg = $self->{ftp}->message);
			$::ERR->warn("Error downloading file: $self->{_curlog}: " . ($msg ? $msg : "Unknown Error"));
			my $ok;
			$ok = $self->_connect(1) unless $self->{reconnect} > 3; # limit the times we reconnect
			last unless $ok;
			#last; # don't try and process any more logs if one fails
			#if (scalar @{$self->{_logs}}) {
			#	$self->{_curlog} = shift @{$self->{_logs}};	# try next log
			#} else {
			#	last;						# no more logs, we're done
			#}
		} else {
			if ($self->{reconnect}) {
				$self->{reconnect} = 0;		# we got a log successfully, so reset our reconnect flag
				#$::ERR->verbose("Reattmpting to process log $self->{_curlog}");
			}
			seek($self->{_loghandle},0,0);		# back up to the beginning of the file, so we can read it
		}
	}

	$self->{_idle} = time;

	if ($self->{_loghandle}) {
		$self->{_totallogs}++ unless $fastforward;
		$self->{_filesize} = (stat $self->{_loghandle})[7];
	}
	return $self->{_loghandle};
}

sub has_event {
	my ($self) = @_;
	if (time - $self->{_lastprint} > $self->{_lastprint_threshold}) {
		$self->echo_processing(1);
		$self->{_lastprint} = time;
	}
	return 1 if @{$self->{_logs}} or @{$self->{_dirs}};
	return 0;
}

# returns undef if there are no more events, or
# returns a 2 element array (line, server).
sub next_event {
	my $self = shift;
	my $line;

	$self->idle;

	# User is trying to ^C out, try to exit cleanly (save our state)
	# Or we've reached our maximum allowed lines
	#if ($::GRACEFUL_EXIT > 0 or ($self->{_maxlines} and $self->{_totallines} >= $self->{_maxlines})) {
	if ($self->{_maxlines} and $self->{_totallines} >= $self->{_maxlines}) {
		#$self->save_state;
		return;
	}

	# No current loghandle? Get the next log in the queue
	if (!$self->{_loghandle}) {
		$self->_opennextlog;
		if ($self->{_loghandle}) {
			$self->echo_processing;
		} else {
			#$self->save_state;
			return;
		}
	}

	# read the next line, if it's undef (EOF), get the next log in the queue
	my $fh = $self->{_loghandle};
	while (!defined($line = <$fh>)) {
		$fh = $self->_opennextlog;
		if ($self->{_loghandle}) {
			$self->echo_processing;
		} else {
			#$self->save_state;
			return;
		}
	}
	# skip the last line if we're at EOF and there are no more logs in the directory
	# do not increment the line counter, etc.
	if ($self->{logsource}{skiplastline} and eof($fh) and !scalar @{$self->{_logs}}) {
		#$self->save_state;
		return;
	}
	$self->{_curline}++;
	$self->{_pos} = tell($fh);

	if ($self->{_verbose}) {
		$self->{_totallines}++;
		$self->{_totalbytes} += length($line);
		$self->{_lastprint_bytes} += length($line);

		if (time - $self->{_lastprint} > $self->{_lastprint_threshold}) {
			$self->echo_processing(1);
			$self->{_lastprint} = time;
		}
	}

	#$self->save_state if time - $self->{_last_saved} > 60;

	# return the event and the server if in array context
	return wantarray ? ($line, scalar $self->server) : $line;
}

sub capture_state {
	my ($self) = @_;
	my $state = $self->SUPER::capture_state;
	$state->{pos} = $self->{_pos};
	return $state;
}

# Restore the previous state for file logsources. This means finding the last
# log we were processing and "fast-forward" to that file in the directory and
# scanning ahead to the line we left off on.
sub restore_state {
	my ($self, $db) = @_;
	$db ||= $self->db;
	my ($st, $state);

	# load the generic state information for this logsource
	$st = $db->prepare('SELECT line,pos,file FROM t_state WHERE id=?');
	$st->execute($self->id) or return;	# SQL error ...
	$state = $st->fetchrow_hashref;
	$st->finish;

	# if there's no state, or no file saved then we do nothing.
	return 1 unless ref $state eq 'HASH' and $state->{file};

	# separate the path from the filename.
	my ($statepath, $statelog) = (splitpath($state->{file}))[1,2];

	# first: find the proper sub-directory for our logsource.
	# speeds up the fast-forward when there's more than 1 sub-dir.
	#while (scalar @{$self->{_dirs}}) {
	#	print catfile($statepath,'') . " == " . catfile($self->{_curdir},'') . "\n";
	#	last if catfile($statepath,'') eq catfile($self->{_curdir},'');
	#	$self->readnextdir;
	#}

	# second: find the log that matches our previous state in the directory
	while (scalar @{$self->{_logs}}) {
		my $cmp = $self->logcompare($self->{_logs}[0], $statelog);
		if ($cmp == 0) { 				# == EQUAL
			next unless $self->_opennextlog(1);
			# finally: fast-forward to the proper line
			if (int($state->{pos} || 0) > 0) {
				# FAST forward quickly using seek position
				seek($self->{_loghandle}, $state->{pos}, 0);
				$self->{_offsetbytes} = $state->{pos};
				$self->{_curline} = $state->{line};
				$self->verbose("Resuming from previous state file \"$state->{file}\" (line: $self->{_curline}, pos: $state->{pos})");
				return 1;
			} else {
				# move forward slowly using the line number
				my $fh = $self->{_loghandle};
				while (defined(my $line = <$fh>)) {
					$self->{_offsetbytes} += length($line);
					if (++$self->{_curline} >= $state->{line}) {
						$self->verbose("Resuming from previous state file \"$state->{file}\" (line: $self->{_curline})");
						return 1;
					}
				}
			}

		} elsif ($cmp == -1) { 				# < LESS THAN
			shift @{$self->{_logs}};

		} else { 					# > GREATER THAN
			# if we get to a log that is 'newer' then the log in our
			# state then we'll just continue from that log since the
			# old log was apparently lost.
			$self->warn("Previous log from state '$statelog' not found. Continuing from " . $self->{_logs}[0] . " instead.");
			return 1;
		}
	}

	# if we couldn't find the log then we'll skip the directory and move on.
	if (!$self->{_curlog}) {
		$self->warn("Unable to find log $statelog from previous state in $statepath. Ignoring directory.");
		$self->readnextdir;
	}

	return 1;
}

# returns the 'id' of a logsource if it exists in the database already.
# the criteria used to search is the type and path (files)
sub logsource_exists {
	my ($self, $logsource) = @_;
	my $db = $self->db;

	# prepare a new statement to find the logsource.
	if (!$db->prepared('find_logsource_ftp')) {
		$db->prepare('find_logsource_ftp',
			"SELECT id FROM t_config_logsources WHERE type=? AND host=? AND port=? AND path=?"
		);
	}

	my $exists = $db->execute_selectcol('find_logsource_ftp', @$logsource{qw( type host port path )});
	return $exists;
}

sub done {
	my $self = shift;
	$self->SUPER::done(@_);
	$self->{ftp}->quit if defined $self->{ftp};
	$self->{ftp} = undef;
}

# Called in the next_event method for each event. Used as an anti-idle timeout
# for FTP
sub idle {
	my ($self) = @_;
	if (time - $self->{_idle} > $self->{max_idle}) {
		$self->{_idle} = time;
		$self->{ftp}->pwd;
#		$self->{ftp}->site("NOP");
		# sending a site NOP command will usually just send back a 500
		# error but the server will see the connection as being active.
		# This has not been widely tested on various servers. Some
		# servers might be smart enough to see repeated commands... I'm
		# not sure. In which case the idle timeout may still disconnect
		# us.
	}
}

sub string {
	my ($self) = @_;
	return sprintf('%s://%s%s%s/%s',
		$self->type,
		$self->username ne '' ? $self->username . '@' : '',
		$self->host,
		$self->port || '',
		$self->path || ''
	);
}
1;
