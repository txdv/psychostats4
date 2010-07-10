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
package PS::Feeder::file;

use strict;
use warnings;
use base qw( PS::Feeder );

use PS::SourceFilter;
use IO::File;
use File::Spec::Functions qw( catfile splitpath );

our $VERSION = '4.00.' . (('$Rev$' =~ /(\d+)/)[0] || '000');

sub init {
	my $self = shift;
	my %args = @_;
	$self->SUPER::init(%args) or return;

	$self->{_pos} = 0;
	$self->{_logs} = [ ];
	$self->{_dirs} = [ ];
	$self->{_curdir} = '';
	$self->{_log_regex} = qr/\.[Ll][Oo][Gg]$/o;

	# build a directory tree. This does not actually load any log files
	$self->_dirtree($self->{logsource}{path});

	# loop through each directory until we have a set of logs to start with.
	# We stop at the first directory with logs in it (no need to load all
	# sub-dirs at the same time)
	while (!scalar @{$self->{_logs}} and scalar @{$self->{_dirs}}) {
		$self->readnextdir;
	}

	return 1;
}

sub _dirtree {
	my $self = shift;
	my $dir = shift;
	my $depth = shift || 0;
	return if ($self->{logsource}{maxdepth} and ($depth > $self->{logsource}{maxdepth}));

	local *D = new IO::File;
	if (!opendir(D, $dir)) {
		$self->warn("Error opening logsource directory $dir: $!");
		return;
	}

	push(@{$self->{_dirs}}, $dir); # add directory to our order list

	# I check this here because I want the line above to do it's thing for
	# the primary 'logsource'
	if ($self->{logsource}{recursive}) {
		while (defined(my $f = readdir(D))) {
			next if substr($f,0,1) eq '.';		# ignore any file/dir that starts with a period
			my $file = catfile($dir, $f);		# absolute filename
			next unless -d $file;			# ignore non-directories
			$self->_dirtree($file, $depth+1);	# go into sub-directory
		}
	}
	closedir(D);
}

# reads the contents of the next directory
sub readnextdir {
	my $self = shift;
	return unless @{$self->{_dirs}};
	my $dir = shift @{$self->{_dirs}};

	$self->{_curdir} = $dir;
	$self->{_logs} = [];

	if (!opendir(D, $dir)) {
		$self->warn("Error opening logsource directory $dir: $!");
		return;
	}

	while (defined(my $f = readdir(D))) {
		next if substr($f,0,1) eq '.';				# ignore any file/dir that starts with a period
		my $file = catfile($dir, $f);				# absolute filename
		next if -d $file;					# ignore sub-dirs
		next unless $file =~ $self->{_log_regex};		# regexp is compiled in init()
		next if $file =~ /WS_FTP/;				# ignore WS log files, they mess things up
		push(@{$self->{_logs}}, $f);				# add the base filename (no path)
	}
	closedir(D);

	# sort the logs we have ...
	if (scalar @{$self->{_logs}}) {
		$self->{_logs} = $self->logsort($self->{_logs});
	}

	#$self->info(scalar(@{$self->{_logs}}) . " logs found in $self->{_curdir}");

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
		#;;;$self->debug4("Opening log $self->{_curlog}", 0);
		$self->{_loghandle} = new IO::File;
		if (!$self->{_loghandle}->open("< " . $self->{_curlog})) {
			$self->warn("Error opening log '$self->{_curlog}': $!");
			undef $self->{_loghandle};
			last;
			#undef $self->{_curlog};
			#if (@{$self->{_logs}}) {
			#	$self->{_curlog} = catfile($self->{_curdir}, shift @{$self->{_logs}});
			#} else {
			#	last;
			#}
		}
		#binmode($self->{_loghandle}, ":encoding(UTF-8)");
	}

	if ($self->{_loghandle}) {
		$self->{_totallogs}++ unless $fastforward;
		$self->{_filesize} = -s $self->{_curlog};
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
	# skip the last line if we're at EOF and there are no more logs in the
	# directory. Do not increment the line counter, etc.
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
	while (scalar @{$self->{_dirs}}) {
		#print catfile($statepath,'') . " == " . catfile($self->{_curdir},'') . "\n";
		last if catfile($statepath,'') eq catfile($self->{_curdir},'');
		$self->readnextdir;
	}

	# second: find the log that matches our previous state in the directory
	while (scalar @{$self->{_logs}}) {
		my $cmp = $self->logcompare($self->{_logs}[0], $statelog);
		if ($cmp == 0) { 				# == EQUAL
			$self->_opennextlog(1);
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
	if (!$db->prepared('find_logsource_file')) {
		$db->prepare('find_logsource_file',
			"SELECT id FROM t_config_logsources WHERE type=? AND path=?"
		);
	}
	
	my $exists = $db->execute_selectcol('find_logsource_file', @$logsource{qw( type path )});
	return $exists;
}

sub string {
	my ($self) = @_;
	return sprintf('%s://%s', $self->type, $self->path);
}

1;
