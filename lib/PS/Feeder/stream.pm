package PS::Feeder::stream;
#
#	UDP Stream support.
#	This feeder is EXPERIMENTAL and not very rubust. 
#	It will allow for any number of simulteanous streams however it makes no attempt to separate
#	them into different game instances. This will currently lead to broken stats, mainly with timing
#	and map statistics. As long as you track by STEAMID most of the stats should come out ok.
#
#	A future version will try and separate steams into different 'games'.
#

use strict;
use warnings;
use base qw( PS::Feeder );

use IO::Socket::INET;
use IO::Select;

use Digest::MD5 qw( md5_hex );
use File::Spec::Functions qw( splitpath catfile );
use File::Path;

our $VERSION = '1.00.' . ('$Rev$' =~ /(\d+)/)[0];


sub init {
	my $self = shift;

	$self->{_opts} = {};
	$self->{_dir} = '';
	$self->{_logs} = [ ];
	$self->{_curline} = 0;
	$self->{_log_regexp} = qr/\.log$/io;
	$self->{_protocol} = 'stream';

	$self->{debug} = $self->{conf}->get_opt('debug');

	$self->{_curlog} = 'stream';
	$self->{_curline} = 0;

	return undef unless $self->_connect;

	# if a savedir was configured and it's not a directory try to create it
=pod
	if ($self->{logsource}{savedir} and !-d $self->{logsource}{savedir}) {
		if (-e $self->{logsource}{savedir}) {
			$::ERR->warn("Invalid directory configured for saving logs ('$self->{logsource}{savedir}'): Is a file");
			$self->{logsource}{savedir} = '';
		} else {
			eval { mkpath($self->{logsource}{savedir}) };
			if ($@) {
				$::ERR->warn("Error creating directory for saving logs ('$self->{logsource}{savedir}'): $@");
				$self->{logsource}{savedir} = '';
			}
		}
	}
	if ($self->{logsource}{savedir}) {
		$::ERR->info("Downloaded logs will be saved to: $self->{logsource}{savedir}");
	}
=cut

	return 1;
}

# setup our socket for listening to incoming log streams
sub _connect {
	my $self = shift;
	$self->{socket} = new IO::Socket::INET(
		Proto => 'udp', 
#		LocalHost => $self->{_host}, 
		LocalPort => $self->{_port}
	);
	if (!$self->{socket}) {
		$::ERR->warn("Error binding to local port $self->{_host}:$self->{_port}: $@");
		return undef;
	}

	$self->{select} = new IO::Select($self->{socket});

	$self->info("Listening on socket $self->{_protocol}://$self->{_host}:$self->{_port} ...");
	$self->info("Note: It may take some time before any new 'Processing' lines are seen below.");

	return 1;
}

# parse the logsource and strip off it's parts for connection options
sub parsesource {
	my $self = shift;
	my $db = $self->{db};
	my $log = $self->{logsource};

	$self->{_host} = 'localhost';
	$self->{_port} = 28000;
	$self->{_user} = '';
	$self->{_pass} = '';
	$self->{_dir} = '';

	if (ref $log) {
		$self->{_host} = $log->{host} if defined $log->{host};
		$self->{_port} = $log->{port} if defined $log->{port};
		$db->update($db->{t_config_logsources}, { lastupdate => time }, [ 'id' => $log->{id} ]);

	} elsif ($log =~ /^([^:]+):\/\/([^\/:]+)(?::(\d+))?\/?(.*)/) {
		my ($protocol,$host,$port,$dir) = ($1,$2,$3,$4);
		$self->{_protocol} = $protocol;
		$self->{_host} = $host;
		$self->{_port} = $port || 28000;
		$self->{_dir} = $dir;

		# see if a matching logsource already exists
		my $exists = $db->get_row_hash(sprintf("SELECT * FROM $db->{t_config_logsources} " . 
			"WHERE type='stream' AND host=%s AND port=%s ", 
			$db->quote($self->{_host}),
			$db->quote($self->{_port})
		));

		if (!$exists) {
			# fudge a new logsource record and save it
			$self->{logsource} = {
				'id'		=> $db->next_id($db->{t_config_logsources}),
				'type'		=> 'stream',
				'path'		=> $self->{_dir},
				'host'		=> $self->{_host},
				'port'		=> $self->{_port},
				'passive'	=> undef,
				'username'	=> undef,
				'password'	=> undef,
				'recursive'	=> undef,
				'depth'		=> undef,
				'skiplast'	=> 0,
				'delete'	=> 0,
				'options'	=> undef,
				'defaultmap'	=> 'unknown',
				'enabled'	=> 0,		# leave disabled since this was given from -log on command line
				'idx'		=> 0x7FFFFFFF,
				'lastupdate'	=> time
			};
			$db->insert($db->{t_config_logsources}, $self->{logsource});
		} else {
			$self->{logsource} = $exists;
			$db->update($db->{t_config_logsources}, { lastupdate => time }, [ 'id' => $exists->{id} ]);
		}

	} else {
		$::ERR->warn("Invalid logsource syntax. Valid example: stream://localhost:28000");
		return undef;
	}
	return 1;
}

sub next_event {
	my $self = shift;
	my $line;
	my ($peername, $port, $packedip, $ip, $head);

	while (1) {
		if ($::GRACEFUL_EXIT > 0) {
			return undef;
		}

		if (my ($s) = $self->{select}->can_read(1)) {
			$s->recv($line, 1024);
			next unless $line;
			$peername = $s->peername || next;
			($port, $packedip) = sockaddr_in($peername);
			$ip = inet_ntoa($packedip);
			# TODO: implement some sort of ACL for allowed client hosts based on $ip
			# however, i do not want another config table just for streams

			$head  = substr($line,0,5,'');					# "....R" (hl2) or "....l" (hl1)
			$head .= substr($line,0,3,'') if substr($head,-1) ne 'R';	# HL1 (remove entire '....log.')
			$line = substr($line,0,-1);					# remove trailing NULL byte

#			$line = decode('UTF-8',$line);

#			if ($self->{_curlog} eq 'unknown') {
#			}

			# keep track of the current log name
			if ($line =~ /^L .......... - ..:..:..: Log file started/) {
				if ($line =~ /file ".*(L\d+\.log)"/) {
					$self->{_curlog} = $1;
				}
			}

			last if length $line > 0;
		}
	}

	if ($self->{_verbose}) {
		$self->{_totallines}++;
		$self->{_totalbytes} += length($line) + length($head) + 1;
#		$self->{_prevlines} = $self->{_totallines};
#		$self->{_prevbytes} = $self->{_totalbytes};
#		$self->{_lasttime} = time;
	}

#	if ($self->{debug}) {
#		print STDERR $line;
#	}

	my @ary = ( $self->{_curlog}, $line, ++$self->{_curline} );
	return wantarray ? @ary : [ @ary ];
}

sub done {
	my $self = shift;
	$self->SUPER::done(@_);
}

1;
