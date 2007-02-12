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

our $VERSION = '1.00';


sub init {
	my $self = shift;

	$self->{conf}->load('logsource_stream');

	$self->{_opts} = {};
	$self->{_dir} = '';
	$self->{_logs} = [ ];
	$self->{_curline} = 0;
	$self->{_log_regexp} = qr/\.log$/io;
	$self->{_protocol} = 'ftp';
	$self->{orig_logsource} = $self->{logsource};

	$self->{_curlog} = 'unknown';
	$self->{_curline} = 0;

	return undef unless $self->_parsesource;
	return undef unless $self->_connect;

	# load logsource_stream config settings
	foreach (qw( skiplast savedir )) {
		$self->{$_} = 
			$self->{conf}->get_logsource_stream($self->{_host} . '.' . $_) || 
			$self->{conf}->get_logsource_stream($_) ||
			0;
	}

	# if a savedir was configured and it's not a directory try to create it
	if ($self->{savedir} and !-d $self->{savedir}) {
		if (-e $self->{savedir}) {
			$::ERR->warn("Invalid directory configured for saving logs ('$self->{savedir}'): Is a file");
			$self->{savedir} = '';
		} else {
			eval { mkpath($self->{savedir}) };
			if ($@) {
				$::ERR->warn("Error creating directory for saving logs ('$self->{savedir}'): $@");
				$self->{savedir} = '';
			}
		}
	}
	if ($self->{savedir}) {
		$::ERR->info("Downloaded logs will be saved to: $self->{savedir}");
	}

	return 1;
}

# establish a connection with the FTP host
sub _connect {
	my $self = shift;
	$self->{socket} = new IO::Socket::INET(
		Proto => 'udp', 
#		LocalHost => $self->{_host}, 
		LocalPort => $self->{_port}
	);
	if (!$self->{socket}) {
		$::ERR->warn("$self->{class} error binding to local port $self->{_host}:$self->{_port}: $@");
		return undef;
	}

	$self->{select} = new IO::Select($self->{socket});

	$self->info("Listening on socket $self->{_protocol}://$self->{_host}:$self->{_port}");

	return 1;
}

# parse the logsource and strip off it's parts for connection options
sub _parsesource {
	my $self = shift;

	$self->{_host} = 'localhost';
	$self->{_port} = 28000;
	$self->{_dir} = '';
	$self->{_user} = '';
	$self->{_pass} = '';

	if ($self->{orig_logsource} =~ /^([^:]+):\/\/([^\/:]+)(?::(\d+))?\/?(.*)/) {
		my ($protocol,$host,$port,$dir) = ($1,$2,$3,$4);
		$self->{_protocol} = $protocol;
		$self->{_host} = $host;
		$self->{_port} = $port || 28000;
		$self->{_dir} = $dir;

		$self->{logsource} = "$protocol://";
		$self->{logsource} .= $self->{_host};
		$self->{logsource} .= ":" . $self->{_port};
		$self->{logsource} .= "/" . $self->{_dir};

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

			$head  = substr($line,0,5,'');	# "....R" (hl2) or "....l" (hl1)
			$head .= substr($line,0,3,'') if substr($head,-1) ne 'R';	# HL1 (remove entire '....log.')
			$line = substr($line,0,-1);	# remove trailing NULL byte

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

	my @ary = ( $self->{_curlog}, $line, ++$self->{_curline} );
	return wantarray ? @ary : [ @ary ];
}

sub done {
	my $self = shift;
	$self->SUPER::done(@_);
}

1;
