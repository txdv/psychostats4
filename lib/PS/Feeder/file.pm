# Basic 'file' Feeder. 
# Feeds logs found in a local directory. 
# Sub-directories will be scanned if logsource_file(recursive) is enabled, 
# but only upto a depth defined by logsource_file(depth). If it's 0 ALL sub-dirs will be scanned.
package PS::Feeder::file;

use strict;
use warnings;
use base qw( PS::Feeder );

use IO::File;
use File::Spec::Functions qw( catfile splitpath );

our $VERSION = '1.10.' . ('$Rev$' =~ /(\d+)/)[0];

sub init { # called from calling program after new()
	my $self = shift;

	$self->{_logs} = [ ];
	$self->{_dirs} = [ ];
	$self->{_curdir} = '';
	$self->{_curlog} = '';
	$self->{_curline} = 0;
	$self->{_curevent} = '';
	$self->{_log_regexp} = qr/\.log$/io;
	$self->{_last_saved} = time;

	$self->{type}  = $PS::Feeder::WAIT;
	$self->{state} = $self->load_state;

	# build a directory tree. This does not actually load any log files
	$self->_dirtree($self->{logsource}{path});

	# loop through each directory until we have a set of logs to start with.
	# We stop at the first directory with logs in it (no need to load all sub-dirs at the same time)
	while (!scalar @{$self->{_logs}} and scalar @{$self->{_dirs}}) {
		$self->readnextdir;
	}

	# we have a previous state to deal with. We must "fast-forward" to the log we ended with.
	if ($self->{state}{file}) {
		my ($ignore, $statepath, $statelog) = splitpath($self->{state}{file});

		# first: find the proper sub-directory for our logsource.
		# speeds up the fast-forward when there's more than 1 sub-dir.
		while (scalar @{$self->{_dirs}}) {
#			print catfile($statepath,'') . " == " . catfile($self->{_curdir},'') . "\n";
			if (catfile($statepath,'') eq catfile($self->{_curdir},'')) {
				last;
			} else {
				$self->readnextdir;
			}
		}

		# second: find the log that matches our previous state in the current log directory
		while (scalar @{$self->{_logs}}) {
			my $cmp = $self->{game}->logcompare($self->{_logs}[0], $statelog);
			if ($cmp == 0) { # ==
				$self->_opennextlog;
				# finally: fast-forward to the proper line
				if (int($self->{state}{pos} || 0) > 0) {	# FAST forward quickly
					seek($self->{_loghandle}, $self->{state}{pos}, 0);
					$self->{_curline} = $self->{state}{line};
					$::ERR->verbose("Resuming from source $self->{state}{file} (line: $self->{_curline}, pos: $self->{state}{pos})");
					return $self->{type};
				} else {					# move forward slowly
					my $fh = $self->{_loghandle};
					while (defined(my $line = <$fh>)) {
						if (++$self->{_curline} >= $self->{state}{line}) {
							$::ERR->verbose("Resuming from source $self->{state}{file} (line: $self->{_curline})");
							return $self->{type};
						}
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
			$::ERR->warn("Unable to find log $statelog from previous state in $statepath. Ignoring directory.");
			$self->readnextdir;
		}
	}

	# if we have no logs in our list at this point we can safely assume we never will, so the init fails
	# (which allows the caller to simply skip us)
	return scalar @{$self->{_logs}} ? $self->{type} : $PS::Feeder::ERROR;
}

sub _dirtree {
	my $self = shift;
	my $dir = shift;
	my $depth = shift || 0;

#	print "_dirtree($dir, $depth)\n";

	return if ($self->{logsource}{maxdepth} and ($depth > $self->{logsource}{maxdepth}));

	local *D = new IO::File;
	if (!opendir(D, $dir)) {
		$::ERR->warn("Error opening logsource directory $dir: $!");
		return;
	}

	push(@{$self->{_dirs}}, $dir);			# add directory to our order list

	# I check this here because I want the line above to do it's thing for the primary 'logsource'
	if ($self->{logsource}{recursive}) {
		while (defined(my $f = readdir(D))) {
			next if substr($f,0,1) eq '.';		# ignore any file/dir that starts with a period
			my $file = catfile($dir, $f);		# absolute filename
			next unless -d $file;			# ignore non-directories
			$self->debug2("follow_symlinks=0, Ignoring $file") && next if -l $file and !$self->{logsource}{follow_symlinks};
			$self->_dirtree($file, $depth+1);	# go into sub-directory
		}
	}
	closedir(D);
}

# reads the contents of the next directory
sub readnextdir {
	my $self = shift;
	return undef unless @{$self->{_dirs}};
	my $dir = shift @{$self->{_dirs}};

	$self->{_curdir} = $dir;
	$self->{_logs} = [];

	if (!opendir(D, $dir)) {
		$::ERR->warn("Error opening logsource directory $dir: $!");
		return;
	}

	while (defined(my $f = readdir(D))) {
		next if substr($f,0,1) eq '.';				# ignore any file/dir that starts with a period
		my $file = catfile($dir, $f);				# absolute filename
		next if -d $file;					# ignore sub-dirs
		next if $file =~ /WS_FTP/;				# ignore WS log files, they mess things up
		next unless $file =~ $self->{_log_regexp};		# regexp is compiled in init()
		push(@{$self->{_logs}}, $f);				# add the base filename (no path)
	}
	closedir(D);

	# sort the logs we have ...
	if (scalar @{$self->{_logs}}) {
		$self->{_logs} = $self->{game}->logsort($self->{_logs});
	}

	pop(@{$self->{_logs}}) if $self->{logsource}{skiplast};		# skip the last log in the directory

	$::ERR->verbose(scalar(@{$self->{_logs}}) . " logs found in $self->{_curdir}");
	$::ERR->debug2(scalar(@{$self->{_logs}}) . " logs found in $self->{_curdir}");

	return scalar @{$self->{_logs}};
}

sub _opennextlog {
	my $self = shift;

	# save state after each log (but only if we've actually read at least 1 line already)
#	$self->save_state if $self->{_totallines} and $self->{game}->{timestamp};

	# close the previous log, if there was one
	undef $self->{_loghandle};

	# no more logs in current directory, get next directory
	while (!scalar @{$self->{_logs}} and scalar @{$self->{_dirs}}) {
		$self->readnextdir;
	}
	return undef if !scalar @{$self->{_logs}};		# no more logs or directories to scan

	$self->{_curlog} = catfile($self->{_curdir}, shift @{$self->{_logs}});
	$self->{_curline} = 0;	

	# keep trying logs until we get one that works (however, chances are if 1 log fails to load they all will)
	while (!$self->{_loghandle}) {
		$self->debug2("Opening log $self->{_curlog}");
		$self->{_loghandle} = new IO::File;
		if (!$self->{_loghandle}->open("< " . $self->{_curlog})) {
			$::ERR->warn("Error opening log '$self->{_curlog}': $!");
			undef $self->{_loghandle};
			last;
#			undef $self->{_curlog};
#			if (@{$self->{_logs}}) {
#				$self->{_curlog} = catfile($self->{_curdir}, shift @{$self->{_logs}});
#			} else {
#				last;
#			}
		}
#		binmode($self->{_loghandle}, ":encoding(UTF-8)");
	}
	return $self->{_loghandle};
}

# returns undef if there are no more events, or 
# returns a 2 element array (log, line).
sub next_event {
	my $self = shift;
	my $line;

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

	$self->save_state if time - $self->{_last_saved} > 60;

#	$self->{_curevent} = $line;
	my @ary = ( $self->{_curlog}, $line, ++$self->{_curline});
	return wantarray ? @ary : [ @ary ];
}

sub save_state {
	my $self = shift;

	$self->{state}{lastupdate} = time;
	$self->{state}{file} = $self->{_curlog};
	$self->{state}{line} = $self->{_curline};
	$self->{state}{pos}  = defined $self->{_loghandle} ? tell($self->{_loghandle}) : undef;

	$self->{_last_saved} = time;

	$self->SUPER::save_state;
}

sub parsesource {
	my $self = shift;
	my $db = $self->{db};
	# if the logsource is already a reference (not a string) then we're all set
	return if ref $self->{logsource};
	# since the logsource is not a hash (assumed to be a string from -log on command line)
	# we attempt to load a matching record from the database and if it doesn't exist we
	# create a new one (but mark it disabled; however its still used in this instance).
	my $log = $self->{logsource};

	# remove the file:// prefix if present
	$log =~ s|^([^:]+)://||;

	# see if a matching logsource already exists
	my $exists = $db->get_row_hash("SELECT * FROM $db->{t_config_logsources} WHERE type='file' AND path=" . $db->quote($log));

	if (!$exists) {
		# fudge a new logsource record and save it
		$self->{logsource} = {
			'id'		=> $db->next_id($db->{t_config_logsources}),
			'type'		=> 'file',
			'path'		=> $log,
			'host'		=> undef,
			'port'		=> undef,
			'username'	=> undef,
			'password'	=> undef,
			'recursive'	=> 0,
			'depth'		=> 0,
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
}

1;
