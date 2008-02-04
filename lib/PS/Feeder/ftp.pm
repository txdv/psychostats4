# FTP Feeder support
package PS::Feeder::ftp;

use strict;
use warnings;
use base qw( PS::Feeder );
use Digest::MD5 qw( md5_hex );
use File::Spec::Functions qw( splitpath catfile );
use File::Path;

our $VERSION = '1.10.' . ('$Rev$' =~ /(\d+)/)[0];

sub init {
	my $self = shift;

	eval "require Net::FTP";
	if ($@) {
		$::ERR->warn("Net::FTP not installed. Unable to load $self->{class} object.");
		return undef;
	}

	$self->{_logs} = [ ];
	$self->{_curline} = 0;
	$self->{_log_regexp} = qr/\.log$/io;
	$self->{_protocol} = 'ftp';
	$self->{_idle} = time;
	$self->{_last_saved} = time;

	$self->{max_idle} = 25;				# should be made a configurable option ...
	$self->{type} = $PS::Feeder::WAIT;
	$self->{reconnect} = 0;

	return undef unless $self->_connect;

	# if a savedir was configured and it's not a directory try to create it
=pod
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
=cut

	return undef unless $self->_readdir;

	$self->{state} = $self->load_state;

	# we have a previous state to deal with. We must "fast-forward" to the log we ended with.
	if ($self->{state}{file}) {
		my $statelog = $self->{state}{file};
		# first: find the log that matches our previous state in the current log directory
		while (scalar @{$self->{_logs}}) {
			my $cmp = $self->{game}->logcompare($self->{_logs}[0], $statelog);
			if ($cmp == 0) { # ==
				$self->_opennextlog;
				# finally: fast-forward to the proper line
				my $fh = $self->{_loghandle};
				while (defined(my $line = <$fh>)) {
					if (++$self->{_curline} >= $self->{state}{line}) {
						$::ERR->verbose("Resuming from source $self->{_curlog} (line: $self->{_curline})");
						return $self->{type};
					}
				}
			} elsif ($cmp == -1) { # <
				shift @{$self->{_logs}};
			} else { # >
				# if we get to a log that is 'newer' then the last log in our state then 
				# we'll just continue from that log since the old log was apparently lost.
				$::ERR->warn("Previous log from state '$statelog' not found. Continuing from " . $self->{_logs}[0] . " instead ...");
				return $self->{type};
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
	my $reconnect = shift;
	my $host = $self->{_opts}{Host};

	$self->{reconnect}++ if $reconnect;
	$self->info(($reconnect ? "Rec" : "C") . "onnecting to $self->{_protocol}://$self->{_user}\@$self->{_opts}{Host}:$self->{_opts}{Port} ...");

	$self->{ftp} = new Net::FTP($host, %{$self->{_opts}});
	if (!$self->{ftp}) {
		$::ERR->warn("$self->{class} error connecting to FTP server: $@");
		return undef;
	}

	if (!$self->{ftp}->login($self->{_user}, $self->{_pass})) {
		chomp(my $msg = $self->{ftp}->message);
		$::ERR->warn("Error logging into FTP server: $msg");
		return undef;
	}

	# get the current directory
	chomp($self->{_logindir} = $self->{ftp}->pwd);

	if ($self->{_dir} and !$self->{ftp}->cwd($self->{_dir})) {
		chomp(my $msg = $self->{ftp}->message);
		$::ERR->warn("$self->{class} error changing FTP directory: $msg");
		return undef;
	}

	$self->info(sprintf("Connected to %s://%s%s%s%s. HOME=%s, CWD=%s",
		$self->{_protocol},
		$self->{_user} ? $self->{_user} . '@' : '',
		$self->{_opts}{Host},
		$self->{_opts}{Port} ne '21' ? ':' . $self->{_opts}{Port} : '',
		$self->{_opts}{Passive} ? " (pasv)" : "",
		$self->{_logindir},
		$self->{ftp}->pwd
	));

	return 1;
}

# parse the logsource and strip off it's parts for connection options
sub parsesource {
	my $self = shift;
	my $db = $self->{db};
	my $log = $self->{logsource};

	$self->{_opts} = {};
	$self->{_opts}{Host} = 'localhost';
	$self->{_opts}{Port} = 21;
	$self->{_opts}{Timeout} = 120;
	$self->{_opts}{Passive} = $self->{conf}->get_opt('passive') ? 1 : 0;
	$self->{_opts}{Debug} = $self->{conf}->get_opt('debug') ? 1 : 0;
	$self->{_dir} = '';
	$self->{_user} = '';
	$self->{_pass} = '';

	if (ref $log) {
		$self->{_opts}{Host} = $log->{host} if defined $log->{host};
		$self->{_opts}{Port} = $log->{port} if defined $log->{port};
		# allow -passive to override the saved logsource setting; {Passive} is set a few lines above
		$self->{_opts}{Passive} = $log->{passive} if defined $log->{passive} and !$self->{_opts}{Passive};
		$self->{_user} = $log->{username};
		$self->{_pass} = $log->{password};
		$self->{_dir}  = $log->{path};
		$db->update($db->{t_config_logsources}, { lastupdate => time }, [ 'id' => $log->{id} ]);

	} elsif ($log =~ /^([^:]+):\/\/(?:([^:]+)(?::([^@]+))?@)?([^\/]+)\/?(.*)/) {
		# ftp://user:pass@hostname.com/some/path/
		my ($protocol,$user,$pass,$host,$dir) = ($1,$2,$3,$4,$5);
		if ($host =~ /^([^:]+):(.+)/) {
			$self->{_opts}{Host} = $1;
			$self->{_opts}{Port} = $2;
		} else {
			$self->{_opts}{Host} = $host;
		}

		# user & pass are optional
		$self->{_user} = $user if $user;
		$self->{_pass} = $pass if $pass;
		$self->{_dir}  = $dir  if $dir;

		$self->{_opts}{Passive} = $self->{conf}->get_opt('passive') ? 1 : 0;

		# see if a matching logsource already exists
		my $exists = $db->get_row_hash(sprintf("SELECT * FROM $db->{t_config_logsources} " . 
			"WHERE type='ftp' AND host=%s AND port=%s AND path=%s AND username=%s", 
			$db->quote($self->{_opts}{Host}),
			$db->quote($self->{_opts}{Port}),
			$db->quote($self->{_dir}),
			$db->quote($self->{_user})
		));

		if (!$exists) {
			# fudge a new logsource record and save it
			$self->{logsource} = {
				'id'		=> $db->next_id($db->{t_config_logsources}),
				'type'		=> 'ftp',
				'path'		=> $self->{_dir},
				'host'		=> $self->{_opts}{Host},
				'port'		=> $self->{_opts}{Port},
				'passive'	=> $self->{_opts}{Passive},
				'username'	=> $self->{_user},
				'password'	=> $self->{_pass},
				'recursive'	=> 0,
				'depth'		=> 0,
				'skiplast'	=> 1,
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
#			undef $self->{_curlog};
			last;					# that's it, we give up
		}
#		binmode($self->{_loghandle}, ":encoding(UTF-8)");
		$self->debug2("Downloading log $self->{_curlog}");
		if (!$self->{ftp}->get( $self->{_curlog}, $self->{_loghandle} )) {
			undef $self->{_loghandle};
			chomp(my $msg = $self->{ftp}->message);
			$::ERR->warn("Error downloading file: $self->{_curlog}: " . ($msg ? $msg : "Unknown Error"));
			my $ok = undef;
#			unshift(@{$self->{_logs}}, $self->{_curlog});		# add current log back on stack
			$ok = $self->_connect(1) unless $self->{reconnect} > 3; # limit the times we reconnect
			last unless $ok;
#			last; # don't try and process any more logs if one fails
#			if (scalar @{$self->{_logs}}) {
#				$self->{_curlog} = shift @{$self->{_logs}};	# try next log
#			} else {
#				last;						# no more logs, we're done
#			}
		} else {
			if ($self->{reconnect}) {
				$self->{reconnect} = 0;		# we got a log successfully, so reset our reconnect flag
#				$::ERR->verbose("Reattmpting to process log $self->{_curlog}");
			}
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

	$self->save_state if time - $self->{_last_saved} > 60;

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
		unless ($self->{_loghandle}) {
			$self->save_state;
			return undef;
		}
	}

	# read the next line, if it's undef (EOF), get the next log in the queue
	my $fh = $self->{_loghandle};
	while (!defined($line = <$fh>)) {
		$fh = $self->_opennextlog;
		unless ($fh) {
			$self->save_state;
			return undef;
		}
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
	$self->{state}{pos}  = defined $self->{_loghandle} ? tell($self->{_loghandle}) : undef;

	$self->{_last_saved} = time;

	$self->SUPER::save_state;
}

sub done {
	my $self = shift;
	$self->SUPER::done(@_);
	$self->{ftp}->quit if defined $self->{ftp};
	$self->{ftp} = undef;
}

# called in the next_event method for each event. Used as an anti-idle timeout for FTP
sub idle {
	my ($self) = @_;
	if (time - $self->{_idle} > $self->{max_idle}) {
		$self->{_idle} = time;
		$self->{ftp}->pwd;
#		$self->{ftp}->site("NOP");
		# sending a site NOP command will usually just send back a 500 error
		# but the server will see the connection as being active.
		# This has not been widely tested on various servers. Some servers
		# might be smart enough to see repeated commands... I'm not sure.
		# in which case the idle timeout may still disconnect us.
	}
}

1;
