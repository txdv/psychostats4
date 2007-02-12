# SFTP Feeder support. Requires Net::SSH::Perl
package PS::Feeder::sftp;

use strict;
use warnings;
use base qw( PS::Feeder );
use Digest::MD5 qw( md5_hex );
use File::Spec::Functions qw( splitpath catfile );
use File::Path;

our $VERSION = '1.00';
my $FH = undef;

sub init {
	my $self = shift;

	eval "require Net::SFTP";
	if ($@) {
		$::ERR->warn("Net::SFTP not installed. Unable to load $self->{class} object.");
		return undef;
	}

	$self->{conf}->load('logsource_sftp');		# load logsource_sftp specific configs

	$self->{_opts} = {};				# settings used in the SFTP connection
	$self->{_dir} = '';
	$self->{_logs} = [ ];
	$self->{_curline} = 0;
	$self->{_log_regexp} = qr/\.log$/io;
	$self->{_protocol} = 'sftp';
	$self->{orig_logsource} = $self->{logsource};

	return undef unless $self->_parsesource;
	return undef unless $self->_connect;

	foreach (qw( skiplast delete savedir )) {
		$self->{$_} = 
			$self->{conf}->get_logsource_sftp($self->{_host} . '.' . $_) || 
			$self->{conf}->get_logsource_sftp($_) || 
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
				while (defined(my $line = <$FH>)) {
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
	$self->{_logs} = [ 
		map {
			( $_->{filename} )
		}
		grep { 
			$_->{filename} !~ /^\./ && 
			$_->{filename} !~ /WS_FTP/ && 
			$_->{filename} =~ /$self->{_log_regexp}/ 
		} 
		$self->{sftp}->ls($self->{_dir})
	];
	if (scalar @{$self->{_logs}}) {
		$self->{_logs} = $self->{game}->logsort($self->{_logs});
	}
	pop(@{$self->{_logs}}) if $self->{skiplast};
	$::ERR->verbose(scalar(@{$self->{_logs}}) . " logs found in $self->{_dir}");
	return scalar @{$self->{_logs}};
}

# establish a connection with the FTP host
sub _connect {
	my $self = shift;

	eval {
		$self->{sftp} = new Net::SFTP($self->{_host}, %{$self->{_opts}});
	};
	if (!$self->{sftp}) {
		$self->warn("$self->{class} error connecting to SFTP server: $@");
		return undef;
	}

	$::ERR->verbose("Connected via SFTP to $self->{_opts}{user}\@$self->{_host}.");

	return 1;
}

# parse the logsource and strip off it's parts for connection options
sub _parsesource {
	my $self = shift;

	# All {_opts} are passed directly to the Net::SFTP object when it's created.
	# This allows subclasses to add their own options to the list
	$self->{_host} = 'localhost';
	$self->{_opts}{user} = '';
	$self->{_opts}{password} = '';
	$self->{_dir} = '';

	if ($self->{logsource} =~ /^([^:]+):\/\/(?:([^:]+)(?::([^@]+))?@)?([^\/]+)\/?(.*)/) {
		my ($protocol,$user,$pass,$host,$dir) = ($1,$2,$3,$4,$5);
		if ($host =~ /^([^:]+):(.+)/) {
			$self->{_host} = $1;
			$self->{_opts}{port} = $2;
		} else {
			$self->{_host} = $host;
		}

		# user & pass are optional
		$self->{_opts}{user} = $user || $self->{conf}->get_logsource_sftp($self->{_host} . '.username') || 
			$self->{conf}->get_logsource_sftp('username');
		$self->{_opts}{password} = $pass || $self->{conf}->get_logsource_sftp($self->{_host} . '.password') || 
			$self->{conf}->get_logsource_sftp('password');

		# load other optional SSH settings
		foreach my $o (qw( port protocol privileged debug identity_files compression compression_level ciphers )) {
			$self->{_opts}{$o} = 
				$self->{conf}->get_logsource_sftp($self->{_host} . ".$o") || 
				$self->{conf}->get_logsource_sftp($o);
		}

		$self->{_opts}{port} ||= 22;
		$self->{_opts}{protocol} ||= '1,2';
		$self->{_opts}{privileged} ||= 0;
#		$self->{_opts}{debug} ||= 0;
		$self->{_opts}{debug} = 0;			# don't want SSH debugging output, it's too much!
		$self->{_opts}{identity_files} ||= [];
		$self->{_opts}{compression} ||= 0;
		$self->{_opts}{compression_level} ||= 6;
		$self->{_opts}{ciphers} ||= '';

		$self->{_dir} = defined $dir ? $dir : '';

		$self->{logsource} = "$protocol://";
		if ($self->{_opts}{user}) {
			$self->{logsource} .= $self->{_opts}{user};
#			$self->{logsource} .= ":" . md5_hex($self->{_opts}{password}) if $self->{_opts}{password};
			$self->{logsource} .= "@";
		}
		$self->{logsource} .= $self->{_host};
		$self->{logsource} .= ":" . $self->{_opts}{port};
		$self->{logsource} .= "/" . $self->{_dir};

	} else {
		$self->warn("Invalid logsource syntax. Valid example: sftp://user:pass\@host.com/path/to/logs");
		return undef;
	}
	return 1;
}

# not a class method. Only used as a sftp->get() callback function
sub _get_callback {
	my ($sftp, $data, $offset, $size) = @_;
	print $FH $data;
}

sub _opennextlog {
	my $self = shift;

	# delete previous log if we had one, and we have 'delete' enabled in the logsource_sftp config
	if ($self->{delete} and $self->{_curlog}) {
		$self->debug2("Deleting log $self->{_curlog}");
		eval { $self->{sftp}->do_remove($self->{_dir} . "/" . $self->{_curlog}) };
		if ($@) {
			$self->debug2("Error deleting log: $@");
		}
	}

	undef $FH;						# close the previous log, if there was one
	return undef if !scalar @{$self->{_logs}};		# no more logs or directories to scan

	$self->{_curlog} = shift @{$self->{_logs}};
	$self->{_curline} = 0;	

	# keep trying logs until we get one that works (however, chances are if 1 log fails to load they all will)
	while (!$FH) {
		$FH = new_tmpfile IO::File;
		if (!$FH) {
			$self->warn("Error creating temporary file for download: $!");
			undef $FH;
			undef $self->{_curlog};
			last;					# that's it, we give up
		}
		$self->debug2("Downloading log $self->{_curlog}");
#		$self->info("SFTP: Downloading log $self->{_curlog}");
		if (!$self->{sftp}->get( $self->{_dir} . "/" . $self->{_curlog}, undef, \&_get_callback)) {
			$self->warn("Error downloading file: " . ($self->{sftp}->status)[1]);
		} else {
			seek($FH,0,0);		# back up to the beginning of the file, so we can read it

			if ($self->{savedir}) {			# save entire file to our local directory ...
				my $file = catfile($self->{savedir}, $self->{_curlog});
				my $path = (splitpath($file))[1] || '';
				eval { mkpath($path) } if $path and !-d $path;
				if (open(F, ">$file")) {
					while (defined(my $line = <$FH>)) {
						print F $line;
					}
					close(F);
					seek($FH,0,0);
				} else {
					$::ERR->warn("Error creating local file for writting ($file): $!");
				}
			}
		}
	}
	return $FH;
}

sub next_event {
	my $self = shift;
	my $line;

	# User is trying to ^C out, try to exit cleanly (save our state)
	if ($::GRACEFUL_EXIT > 0) {
		$self->save_state;
		return undef;
	}

	# No current loghandle? Get the next log in the queue
	if (!$FH) {
		$self->_opennextlog;
		return undef unless $FH;
	}

	# read the next line, if it's undef (EOF), get the next log in the queue
	my $fh = $FH;
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

1;
