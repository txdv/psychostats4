# FTP Feeder support. Attempts a standard FTP connection. If you require PASSIVE support use ftp_pasv:// instead of ftp://
package PS::Feeder::ftp;

use strict;
use warnings;
use base qw( PS::Feeder );
use Digest::MD5 qw( md5_hex );
use File::Spec::Functions qw( splitpath catfile );
use File::Path;

our $VERSION = '1.00';


sub init {
	my $self = shift;

	eval "require Net::FTP";
	if ($@) {
		$::ERR->warn("Net::FTP not installed. Unable to load $self->{class} object.");
		return undef;
	}

	$self->{conf}->load('logsource_ftp');		# load logsource_ftp specific configs

	$self->{_opts} = {};				# settings used in the FTP connection
	$self->{_dir} = '';
	$self->{_logs} = [ ];
	$self->{_curline} = 0;
	$self->{_log_regexp} = qr/\.log$/io;
	$self->{_protocol} = 'ftp';
	$self->{_idle} = time;
	$self->{max_idle} = 25;				# should be made a configurable option ...
	$self->{orig_logsource} = $self->{logsource};

	return undef unless $self->_parsesource;
	return undef unless $self->_connect;

	# load logsource_ftp config settings
	foreach (qw( skiplast delete savedir )) {
		$self->{$_} = 
			$self->{conf}->get_logsource_ftp($self->{_opts}{Host} . '.' . $_) || 
			$self->{conf}->get_logsource_ftp($_) ||
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

	return undef unless $self->_readdir;

	$self->{state} = $self->load_state;

	# we have a previous state to deal with. We must "fast-forward" to the log we ended with.
	if ($self->{state}{file}) {
		my $statelog = $self->{state}{file};
		# first: find the log that matches our previous state in the current log directory
		while (scalar @{$self->{_logs}}) {
#			print "'$self->{_logs}->[0]' == '$statelog'\n";
#			if ($self->{_logs}->[0] eq $statelog) {			# we found the matching log!
			if ($self->{game}->logcompare($self->{_logs}->[0], $statelog) != -1) {
				$self->_opennextlog;
				# finally: fast-forward to the proper line
				my $fh = $self->{_loghandle};
				while (defined(my $line = <$fh>)) {
					if (++$self->{_curline} >= $self->{state}->{line}) {
#						die $line;
						$::ERR->verbose("Resuming from source $self->{_curlog} (line: $self->{_curline})");
						return @{$self->{_logs}} ? @{$self->{_logs}}+1 : 1;
					}
				}
			} else {
				shift @{$self->{_logs}};
			} 
		}

		if (!$self->{_curlog}) {
			$::ERR->warn("Unable to find log $statelog from previous state in $self->{_dir}. Ignoring directory.");
		}
	}

	return scalar @{$self->{_logs}} ? 1 : 0;
}

# reads the contents of the current directory
sub _readdir {
	my $self = shift;
	$self->{_logs} = [ grep { !/^\./ && !/WS_FTP/ && /$self->{_log_regexp}/ } $self->{ftp}->ls ];
	if (scalar @{$self->{_logs}}) {
		$self->{_logs} = $self->{game}->logsort($self->{_logs});
	}
	pop(@{$self->{_logs}}) if $self->{skiplast};	# skip the last log in the directory
	$::ERR->verbose(scalar(@{$self->{_logs}}) . " logs found in $self->{_dir}");
	return scalar @{$self->{_logs}};
}

# establish a connection with the FTP host
sub _connect {
	my $self = shift;
	my $host = $self->{_opts}{Host};
	$self->{ftp} = new Net::FTP($host, %{$self->{_opts}});
	if (!$self->{ftp}) {
		$::ERR->warn("$self->{class} error connecting to FTP server: $@");
		return undef;
	}

	if (!$self->{ftp}->login($self->{_user}, $self->{_pass})) {
		chomp(my $msg = $self->{ftp}->message);
		$::ERR->warn("$self->{class} error logging into FTP server: $msg");
		return undef;
	}

	if ($self->{_dir} and !$self->{ftp}->cwd($self->{_dir})) {
		chomp(my $msg = $self->{ftp}->message);
		$::ERR->warn("$self->{class} error changing FTP directory: $msg");
		return undef;
	}

	$self->info("Connected to $self->{_protocol}://$self->{_opts}{Host}:$self->{_opts}{Port}. CWD=" . $self->{ftp}->pwd);

	return 1;
}

# parse the logsource and strip off it's parts for connection options
sub _parsesource {
	my $self = shift;

	# All {_opts} are passed directly to the Net::FTP object when it's created.
	# This allows subclasses to add their own options to the list
	$self->{_opts}{Host} = 'localhost';
	$self->{_opts}{Port} = 21;
	$self->{_opts}{Timeout} = 120;
	$self->{_opts}{Passive} = 0;
#	$self->{_opts}{LocalAddr} = ;
	$self->{_opts}{Debug} = $self->{conf}->get_opt('debug') || 0 > 1 ? 1 : 0;
	$self->{_dir} = '';
	$self->{_user} = '';
	$self->{_pass} = '';

	if ($self->{orig_logsource} =~ /^([^:]+):\/\/(?:([^:]+)(?::([^@]+))?@)?([^\/]+)\/?(.*)/) {
		my ($protocol,$user,$pass,$host,$dir) = ($1,$2,$3,$4,$5);
		if ($host =~ /^([^:]+):(.+)/) {
			$self->{_opts}{Host} = $1;
			$self->{_opts}{Port} = $2;
		} else {
			$self->{_opts}{Host} = $host;
		}

		# user & pass are optional
		$self->{_user} = $user || $self->{conf}->get_logsource_ftp('username') || 
			$self->{conf}->get_logsource_ftp($self->{_opts}{Host} . '.username');
		$self->{_pass} = $pass || $self->{conf}->get_logsource_ftp('password') || 
			$self->{conf}->get_logsource_ftp($self->{_opts}{Host} . '.password');

		$self->{_dir} = defined $dir ? $dir : '';

		$self->{logsource} = "$protocol://";
		if ($self->{_user}) {
			$self->{logsource} .= "$self->{_user}";
#			$self->{logsource} .= ":" . md5_hex($self->{_pass}) if $self->{_pass};
			$self->{logsource} .= "@";
		}
		$self->{logsource} .= $self->{_opts}{Host};
		$self->{logsource} .= ":" . $self->{_opts}{Port} if $self->{_opts}{Port} ne '21';
		$self->{logsource} .= "/" . $self->{_dir};

	} else {
		$::ERR->warn("Invalid logsource syntax. Valid example: ftp://user:pass\@host.com/path/to/logs");
		return undef;
	}
	return 1;
}

sub _opennextlog {
	my $self = shift;

	# delete previous log if we had one, and we have 'delete' enabled in the logsource_ftp config
	if ($self->{delete} and $self->{_curlog}) {
		$self->debug2("Deleting log $self->{_curlog}");
		if (!$self->{ftp}->delete($self->{_curlog})) {
			chomp(my $msg = $self->{ftp}->message);
			$self->debug2("Error deleting log: $msg");
		}
	}

	undef $self->{_loghandle};				# close the previous log, if there was one
	return undef if !scalar @{$self->{_logs}};		# no more logs or directories to scan

	$self->{_curlog} = shift @{$self->{_logs}};
	$self->{_curline} = 0;	

	# keep trying logs until we get one that works (however, chances are if 1 log fails to load they all will)
	while (!$self->{_loghandle}) {
		$self->{_loghandle} = new_tmpfile IO::File;
		if (!$self->{_loghandle}) {
			$::ERR->warn("Error creating temporary file for download: $!");
			undef $self->{_loghandle};
			undef $self->{_curlog};
			last;					# that's it, we give up
		}
		$self->debug2("Downloading log $self->{_curlog}");
		if (!$self->{ftp}->get( $self->{_curlog}, $self->{_loghandle} )) {
			undef $self->{_loghandle};
			chomp(my $msg = $self->{ftp}->message);
			$::ERR->warn("Error downloading file: $msg");
			if (scalar @{$self->{_logs}}) {
				$self->{_curlog} = shift @{$self->{_logs}};	# try next log
			} else {
				last;						# no more logs, we're done
			}
		} else {
			seek($self->{_loghandle},0,0);		# back up to the beginning of the file, so we can read it

			if ($self->{savedir}) {			# save entire file to our local directory ...
				my $file = catfile($self->{savedir}, $self->{_curlog});
				my $path = (splitpath($file))[1] || '';
				eval { mkpath($path) } if $path and !-d $path;
				if (open(F, ">$file")) {
					my $fh = $self->{_loghandle};
					while (defined(my $line = <$fh>)) {
						print F $line;
					}
					close(F);
					seek($self->{_loghandle},0,0);
				} else {
					$::ERR->warn("Error creating local file for writting ($file): $!");
				}
			}
		}
	}
	$self->{_idle} = time;
	return $self->{_loghandle};
}

sub next_event {
	my $self = shift;
	my $line;

	$self->idle;

	# User is trying to ^C out, try to exit cleanly (save our state)
	if ($::GRACEFUL_EXIT > 0) {
		$self->save_state;
		return undef;
	}

	# No current loghandle? Get the next log in the queue
	if (!$self->{_loghandle}) {
		$self->_opennextlog;
		return undef unless $self->{_loghandle};
	}

	# read the next line, if it's undef (EOF), get the next log in the queue
	my $fh = $self->{_loghandle};
	while (!defined($line = <$fh>)) {
		$fh = $self->_opennextlog;
		return undef unless $fh;
	}

	if ($self->{_verbose}) {
		$self->{_totallines}++;
		$self->{_totalbytes} += length($line);
#		$self->{_prevlines} = $self->{_totallines};
#		$self->{_prevbytes} = $self->{_totalbytes};
#		$self->{_lasttime} = time;
	}

#	my $logsrc = "ftp://" . $self->{_opts}{Host} . ($self->{_opts}{Port} ne '21' ? ':' . $self->{_opts}{Host} : '' ) . '/' . $self->{_dir};
#	my @ary = ( $logsrc, $line, ++$self->{_curline} );
	my @ary = ( $self->{_curlog}, $line, ++$self->{_curline} );
	return wantarray ? @ary : [ @ary ];
}

sub save_state {
	my $self = shift;

	$self->{state}{file} = $self->{_curlog};
	$self->{state}{line} = $self->{_curline};

	$self->SUPER::save_state;
}

sub done {
	my $self = shift;
	$self->SUPER::done(@_);
	$self->{ftp}->quit if defined $self->{ftp};
	$self->{ftp} = undef;
}

# called in the next_event method for each event. Used an an anti-idle timeout for FTP
sub idle {
	my ($self) = @_;
	if (time - $self->{_idle} > $self->{max_idle}) {
		$self->{_idle} = time;
		$self->{ftp}->site("NOP");
		# sending a site NOP command will usually just send back a 500 error
		# but the server will see the connection as being active.
		# This has not been widely tested on various servers. Some servers
		# might be smart enough to see repeated commands... I'm not sure.
		# in which case the idle timeout may still disconnect us.
	}
}

1;
