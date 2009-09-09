#!/usr/bin/perl
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

BEGIN { # FindBin isn't going to work on systems that run the stats.pl as SETUID
	use strict;
	use warnings;

	use FindBin; 
	use lib ( $FindBin::Bin, $FindBin::Bin . "/lib" );
}

BEGIN { # make sure we're running the minimum version of perl required
	my $minver = 5.08;
	my $curver = 0.0;
	my ($major,$minor,$release) = split(/\./,sprintf("%vd", $^V));
	$curver = sprintf("%d.%02d",$major,$minor);
	if ($curver < $minver) {
		print "Perl v$major.$minor.$release is too old to run PsychoStats.\n";
		print "Minimum version $minver is required. You must upgrade before continuing.\n";
		if (lc substr($^O,0,-2) eq "mswin") {
			print "\nPress ^C or <enter> to exit.\n";
			<>;
		}
		exit 1;
	}
}

BEGIN { # do checks for required modules
	our %PM_LOADED = ();
	my @modules = qw( DBI DBD::mysql Time::Local Time::HiRes Encode DateTime );
	my %optional = ( DateTime => 1 );
	my @failed_at_life = ();
	my @failed_optional = ();
	my %bad_kitty = ();
	foreach my $module (@modules) {
		my $V = '';
		eval "use $module; \$V = \$${module}::VERSION;";
		if ($@) {	# module not found
			if (exists $optional{$module}) {
				push(@failed_optional, $module);
			} else {
				push(@failed_at_life, $module);
			}
		} else {	# module loaded ok; store for later, if -V is used for debugging purposes
			$PM_LOADED{$module} = $V;
		}
	}

	# check the version of modules
	# DBD::mysql needs to be 3.x at a minimum
	if ($PM_LOADED{'DBD::mysql'} and substr($PM_LOADED{'DBD::mysql'},0,1) lt '3') {
		$bad_kitty{'DBD::mysql'} = '3.0008';
	}

	# if anything failed, kill ourselves, life isn't worth living.
	if (@failed_at_life or keys %bad_kitty) {
		print "PsychoStats failed initialization!\n";
		if (@failed_at_life) {
			print "The following modules are required and could not be loaded.\n";
			print "\t" . join("\n\t", @failed_at_life) . "\n";
			print "\n";
		}
		if (keys %bad_kitty) {
			print "The following modules need to be upgraded to the version shown below\n";
			print "\t$_ v$bad_kitty{$_} or newer (currently installed: $PM_LOADED{$_})\n" for keys %bad_kitty;
			print "\n";
		}

		if (lc substr($^O,0,-2) eq "mswin") {	# WINDOWS
			print "You can install the modules listed by using the Perl Package Manager.\n";
			print "Typing 'ppm' at the Start->Run menu usually will open it up. Enter the module\n";
			print "name and have it install. Then rerun PsychoStats.\n";
			print "\nPress ^C or <enter> to exit.\n";
			<>;
		} else {				# LINUX
			print "You can install the modules listed using either CPAN or if your distro\n";
			print "supports it by installing a binary package with your package manager like\n";
			print "'yum' (fedora / redhat), 'apt-get' or 'aptitude' (debian).\n";
		}
		exit 1;
	}
}

use util qw( compacttime print_r abbrnum commify );
use PS::Core;
use PS::SourceFilter;
use PS::CmdLine;
use PS::DBI;
use PS::Conf;
use PS::ErrLog;
use PS::Feeder;
use PS::Game;
use PS::Plr;
use PS::Map;
use PS::Role;
use PS::Weapon;

use POSIX qw( :sys_wait_h setsid );
use File::Spec::Functions qw( catfile );
use Time::HiRes qw( time usleep );

# The $VERSION and $PACKAGE_DATE are automatically updated via the packaging script.
our $VERSION = '4.0';
our $PACKAGE_DATE = time;
our $REVISION = ('$Rev$' =~ /(\d+)/)[0] || '000';

our $DEBUG = 0;					# Global DEBUG level
our $DEBUGFILE = undef;				# Global debug file to write debug info too
our $ERR;					# Global Error handler
our $DBCONF = {};				# Global database config
our $GRACEFUL_EXIT = 0; #-1;			# (used in CATCH_CONTROL_C)

$SIG{INT} = \&CATCH_CONTROL_C;

my ($opt, $dbconf, $db, $conf);

# verbose tracking/progress variables
my $starttime = time;
my $total_logs = 0;
my $total_events = 0;

eval {
	# I don't think this actually does anything useful, need to research
	# this a bit more...
	binmode(STDOUT, ":utf8");
	binmode(STDERR, ":utf8");
};

$opt = new PS::CmdLine;				# Initialize command line
$DEBUG = int($ENV{PSYCHOSTATS_DEBUG}) || 0;	# sets global debugging

# display our version and exit
if ($opt->version) {
	print "PsychoStats version $VERSION (rev $REVISION)\n";
	print "Packaged on " . scalar(localtime $PACKAGE_DATE) . "\n";
	print "Website: http://www.psychostats.com/\n";
	print "Perl version " . sprintf("%vd", $^V) . " ($^O)\n";
	print "Loaded Modules:\n";
	my $len = 1;
	foreach my $pm (keys %PM_LOADED) {
		# get max length first, so we can be pretty
		$len = length($pm) if length($pm) > $len;
	}
	$len += 2;
	foreach my $pm (sort { lc $a cmp lc $b } keys %PM_LOADED) {
		printf("  %-${len}sv%s\n", $pm, $PM_LOADED{$pm});
	}
	exit;
}

if (defined(my $df = $opt->debugfile)) {
	$df = 'debug.txt' unless $df;		# if filename is empty
	$DEBUGFILE = $df;
	# this won't work, now that the Filter::Simple is used for debugging.
	$DEBUG = 1 unless $DEBUG;		# force DEBUG on if we're specifying a file
	;;; PS::Core->debug("DEBUG START: " . scalar(localtime) . " (level $DEBUG) File: $DEBUGFILE");
}

# Load the basic stats.cfg for database settings (unless 'noconfig' is specified
# on the command line) The config filename can be specified on the commandline,
# otherwise stats.cfg is used. If that file does not exist then the config is
# loaded from the __DATA__ block of this file.
$dbconf = {};
if (!$opt->noconfig) {
	if ($opt->config) {
		;;; PS::Core->debug("Loading DB config from " . $opt->config);
		$dbconf = PS::Conf->loadfile( $opt->config );
	} elsif (-f catfile($FindBin::Bin, 'stats.cfg')) {
		;;; PS::Core->debug("Loading DB config from " . catfile($FindBin::Bin, 'stats.cfg'));
		$dbconf = PS::Conf->loadfile( catfile($FindBin::Bin, 'stats.cfg') );
	} else {
		;;; PS::Core->debug("Loading DB config from __DATA__");
		$dbconf = PS::Conf->loadfile( *DATA );
	}
} else {
	;;; PS::Core->debug("-noconfig specified, No DB config loaded.");
}

# Initialize the primary Database object
# Allow command line options to override settings loaded from config
$DBCONF = {
	dbtype		=> $opt->dbtype || $dbconf->{dbtype},
	dbhost		=> $opt->dbhost || $dbconf->{dbhost},
	dbport		=> $opt->dbport || $dbconf->{dbport},
	dbname		=> $opt->dbname || $dbconf->{dbname},
	dbuser		=> $opt->dbuser || $dbconf->{dbuser},
	dbpass		=> $opt->dbpass || $dbconf->{dbpass},
	dbtblprefix	=> $opt->dbtblprefix || $dbconf->{dbtblprefix},
	dbcompress	=> $opt->dbcompress || $dbconf->{dbcompress}
};
$db = new PS::DBI($DBCONF);
$db->do('SET time_zone = \'+00:00\'');	# Make sure Mysql treats all times as UTC/GMT

$conf = new PS::Conf($db, 'main', $opt);
$ERR = new PS::ErrLog($db, $conf);	# Errors will be logged to the DB
PS::Core->set_verbose($opt->verbose and !$opt->quiet);
PS::ErrLog->set_verbose($opt->verbose and !$opt->quiet);
#$ERR->set_verbose($opt->verbose and !$opt->quiet);

# Setup global helpers for important objects. This simplifies the initialization
# of these objects since they all use the same helpers (for the most part).
PS::Game::configure  (            CONF => $conf, OPT => $opt );
PS::Plr::configure   ( DB => $db, CONF => $conf, OPT => $opt );
PS::Map::configure   ( DB => $db, CONF => $conf, OPT => $opt );
PS::Role::configure  ( DB => $db, CONF => $conf, OPT => $opt );
PS::Weapon::configure( DB => $db, CONF => $conf, OPT => $opt );
PS::Feeder::configure( DB => $db, CONF => $conf, OPT => $opt );

#$ERR->info("PsychoStats v$VERSION initialized.");

# Handle some updates (not logs)
#if ($opt->update) {
#	my $update = $opt->update;
#	if ($update =~ /\b(all|ranks)\b/) {
#		$ERR->verbose('+ Preparing to update player ranks...');
#	}
#	&exit;
#}

# handle a 'stats reset' request
if (defined $opt->reset) {
	if (!$opt->gametype or !$opt->modtype) {
		$ERR->fatal("-gametype and -modtype paramaters are required to reset the database.", 1);
		undef $ERR; # prevent END block from saying anything
		exit;
	}
	
	my $res = $opt->reset;
	my $all = (index($opt->reset,'all') >= 0);
	my %del = (
		players 	=> ($all || (index($res,'player') >= 0)),
		clans   	=> ($all || (index($res,'clan') >= 0)),
		weapons 	=> ($all || (index($res,'weapon') >= 0)),
		heatmaps	=> ($all || (index($res,'heat') >= 0)),
	);
	my $game = new PS::Game($opt->gametype, $opt->modtype, $db);
	my $ok = $game->reset_game(%del);
	if ($ok) {
		$ERR->info('Stats database has been reset for ' . $opt->gametype . '::' . $opt->modtype);
	} else {
		
	}
	
	undef $ERR; # prevent END block from saying anything
	exit;
}

if ($opt->deleteclans and !$opt->scanclantags) {
	$ERR->info('Deleting clans ... ');
	my $game = new PS::Game($opt->gametype, $opt->modtype, $db);
	$game->delete_clans;
	exit;
}

if ($opt->scanclantags) {
	# load the game engine.
	my $game = new PS::Game($opt->gametype, $opt->modtype, $db);

	if ($opt->deleteclans) {
		$ERR->info('Deleting clans ... ');
		$game->delete_clans;
	}
	
	$ERR->info('Rescanning clantags ... ');
	

	# make sure clantags are loaded, regardless of 'clantag_detection' being
	# enabled or not.
	$game->load_clantags unless defined $game->{clantags} and @{$game->{clantags}};

	# scan all players for the gametype::modtype specified
	$game->rescan_clans($opt->gametype, $opt->modtype);
	exit;
}

# infinite main loop to process logs
while (!$opt->nologs) {
	my (@streams, @files, @sources);
	my @logsources = load_logsources();
	if (@logsources == 0) {
		$ERR->info("No more log sources to process.");
		last;
	}

	# We process a list of streams or non-stream sources, but not both at
	# the same time, since the logic involved is different for each type.
	# Processing files is handled synchronously (1 at a time and in order).
	# Processing streams is handled asynchronously (many at once).

	# separate our logsources into different lists
	foreach my $s (@logsources) {
		if ($s->type eq 'stream') {
			# if -files is specified then all streams are ignored
			push(@streams, $s) unless $opt->files;
		} else {
			# if -streams is specified then all files are ignored
			push(@files, $s) unless $opt->streams;
		}
	}

	# If we have stream and non-stream sources then error out! We can't
	# handle both at the same time.
	if (@streams and @files) {
		$ERR->fatal(
			"Stream and non-stream logsources can not be processed at the same time!\n" .
			"Use -files or -streams command line options to limit which type to process.",
			1 	# don't include a stack trace
		);
	}

	# Process the log sources ... 
	process_streams(@streams) if @streams;
	process_files(@files) if @files;

	# clear memory used by feeders
	@streams = ();
	@files = ();

	last;
}

# process the list of log streams
sub process_streams {
	my (@list) = @_;
	my (@feeders, %args, $ev, $had_event, $feed, $srv, $game);
	my $games = {};
	my $feeds = {};
	
	# setup some init parameters for the feeders (generic)
	$args{verbose}	= ($opt->verbose && !$opt->quiet);
	$args{maxlogs}	= $opt->maxlogs;
	$args{maxlines}	= $opt->maxlines;

	# stream specific paramaters
	$args{echo}	= $opt->echo;
	$args{bindip} 	= $opt->ipaddr;
	$args{bindport}	= $opt->port;

	# Initialize each stream that is configured
	foreach my $feed (@list) {
		if (!$feed->init(%args)) {
			$ERR->warn("Error initializing logsource $feed: " . $feed->error);
			next;
		}
		# restore the previous state, if there was one for this feed
		if (!$feed->restore_state) {
			$ERR->warn("Error loading state for logsource \"$feed\" (this is usually harmless): " . $feed->error);
			# no need to require -force for streams
		}
		push(@feeders, $feed);
	}
	
	# If no Feeders were initialized successfully, then there's no point in
	# continuing any further.
	main::exit() if @feeders == 0;
	
	# Infinite loop; process streams forever (or ^C is pressed)...
	while (1) {
		$had_event = 0;
		# process an event from each active feeder
		foreach $feed (@feeders) {
			next unless $feed->has_event;
			($ev, $srv) = $feed->next_event(0);
			next unless $ev;
			$had_event = 1;
	
			if (!exists $games->{$srv}) {
				# create a new game for the server
				$games->{$srv} = new PS::Game(
					$feed->gametype,
					$feed->modtype,
					$db->clone		# clone it
				);
				$games->{$srv}->restore_state($feed);
				# keep track of which feed each server is being
				# processed from.
				$feeds->{$srv} = $feed;
			}
			
			# process the game event for the server
			$games->{$srv}->event($ev, $feed);
			++$total_events;

			#if ($feed->curlog ne $last_log) {
			#	$last_log = $feed->curlog;
			#	++$total_logs;
			#}

			# update player ranks if the day has changed
			if ($games->{$srv}->get_event_last_day and
			    $games->{$srv}->get_event_last_day != $games->{$srv}->get_event_day
			    ) {
				$games->{$srv}->update_plrs( $games->{$srv}->get_event_last_timestamp );
			}
			
			# if ^C was pressed, stop processing.
			last if $GRACEFUL_EXIT > 0;
		}
		last if $GRACEFUL_EXIT > 0;

		# yield the CPU if no events occured, this prevents us from
		# taking up 100% CPU when no events are pending.
		# I need to test this and make sure it works on Windows.
		usleep(0) unless $had_event;
	}
	
	# Save state for each game. Although, chances are the state of a stream
	# won't be any good the next time an update is run. But if the script is
	# simply being restarted then only a couple of seconds will elapse.
	foreach $srv (keys %$games) {
		$feeds->{$srv}->save;
		$feeds->{$srv}->save_state;
		$games->{$srv}->save;	# save current stats first
		$games->{$srv}->save_state($feeds->{$srv}, $srv);
		# force ranks to be updated once before we exit
		$games->{$srv}->update_plrs;
	}

	# we're done... don't return to the caller.
	main::exit();
}

# process the list of file logsources (file, ftp, sftp)
sub process_files {
	my (@list) = @_;
	my ($ev, $had_event, $srv, $game, %args);
	my $saved = time;
	my $save_threshold = 60 * 1;		# interval to save game state

	# setup some init parameters for the feeders (generic)
	$args{verbose}	= ($opt->verbose && !$opt->quiet);
	$args{maxlogs}	= $opt->maxlogs;
	$args{maxlines}	= $opt->maxlines;
	$args{echo}	= $opt->echo;

	# Loop through each feed individually ...
	foreach my $feed (@list) {
		if (!$feed->init(%args)) {
			$ERR->warn("Error initializing logsource $feed: " . $feed->error);
			next;
		}
		# restore the previous state, if there was one for this feed
		if (!$feed->restore_state) {
			$ERR->warn("Error loading state for logsource \"$feed\": " . $feed->error);
			if (!$opt->force) {
				$ERR->warn("Use --force to force this logsource to start over.");
				next;
			}
			$feed->reset_state;
		}
		
		# instantiate the game
		$game = new PS::Game($feed->gametype, $feed->modtype, $db);
		# restore the game to its previous state for the logsource feed
		$game->restore_state($feed);

		#&lps_reset;
		#my $last = time - 3;
		#my $total = 0;
		my $last_log = '';
		while (defined($ev = $feed->next_event)) {
			next unless $ev;
			
			# process the event for the server
			$game->event($ev, $feed);
			++$total_events;

			if ($feed->curlog ne $last_log) {
				$last_log = $feed->curlog;
				++$total_logs;
			}

			# update player ranks if the day has changed
			if ($game->get_event_last_day and
			    $game->get_event_last_day != $game->get_event_day
			    ) {
				$game->update_plrs( $game->get_event_last_timestamp );
			}

			#&lps_prev($total);
			#++$total;
			#lps($total,1);
			#
			##print "LPS1=" . lps($total) . "\n";
			##print "LPS2=" . lps($total) . "\n";
			## wait until the LPS has lowered below our threshold
			#while ($opt->lps && lps($total) > $opt->lps) {
			#	last if $GRACEFUL_EXIT > 0;
			#	usleep(0); # YIELD
			#	#if (time - $last > 0.10) {
			#	#	print "LPS=" . lps($total) . "\n";
			#	#	$last = time;
			#	#}
			#}
			#if (time - $last > 1) {
			#	print "LPS=" . lps($total) . "\n";
			#	$last = time;
			#}

			last if $GRACEFUL_EXIT > 0;

			# stop processing the feed if we've reached the
			# configured resource limits.
			if (($args{maxlines} and $total_events >= $args{maxlines}) ||
			    ($args{maxlogs}  and $total_logs   >= $args{maxlogs})) {
				$ERR->verbose(sprintf(
					'Maximum events (%d) or logs (%d) reached. Exiting cleanly.',
					$args{maxlines}, $args{maxlogs}
				));
				last;
			}

			# save the game state every X minutes (real-time)
			if (time - $saved > $save_threshold) {
				$feed->debug4("Saving game state.", 0);
				$feed->save_state;
				$game->save_state($feed, scalar $feed->server);
				$saved = time;
			}
		}
		
		# save our logsource and game state
		$feed->save;
		$feed->save_state;
		$game->save; 	# save current stats first.
		$game->save_state($feed, scalar $feed->server);
		# force ranks to be updated once before we exit
		$game->update_plrs;
		
		last if $GRACEFUL_EXIT > 0;
	}

	# we're done... don't return to the caller.
	main::exit();	
}

# returns a list of log sources
sub load_logsources {
	my (@list, @feeders);
	if ($opt->logsource) {
		my $log = new PS::Feeder($opt->logsource, $opt->gametype, $opt->modtype, $db);
		if ($log->error) {
			$ERR->warn('Error loading logsource (' . $opt->logsource . '): ' . $log->error);
		} else {
			# force new logsources from the command line to be
			# disabled. Also save the logsource to the DB.
			if (!$log->id) {
				$log->enabled(0);
				$log->save;
			}
			push(@feeders, $log);
		}
	} else {
		@list = PS::Feeder::get_logsources(1, $db);
		# instantiate a PS::Feeder for each logsource
		foreach my $ls (@list) {
			my $log = new PS::Feeder($ls, $db);
			if ($log->error) {
				$ERR->warn("Error loading logsource ($ls): " . $log->error);
				next;
			}
			push(@feeders, $log);
		}
	}
	
	#my $log = new PS::Feeder({
	#	type		=> 'stream',
	#	host		=> 'localhost',
	#	port		=> '28001',
	#});
	#print_r($log);
	#print_r(@feeders);
	#exit;

	return wantarray ? @feeders : \@feeders;
}

{ # enclose local scope
	my $lasttime = time;
	my $prev = 0;
	sub lps {
		my ($total, $inctime) = @_;
		my $time_diff = time - $lasttime;
		my $line_diff = $total - $prev;
		my $lps = $time_diff ? sprintf('%0.2f', $line_diff / $time_diff) : $line_diff;
		$lasttime = time if $inctime;
		#$prev = $total;
		return $lps;
	}
	sub lps_prev  { $prev = shift }
	sub lps_reset { $lasttime = time; $prev = 0 }
}

# returns true if we're already running as a daemon under another process.
sub is_daemon_running {
	
}

# daemonizes the program into the background
sub run_in_background {
	my ($pid_file) = @_;
	defined(my $pid = fork) or die "Can't fork process: $!";
	exit if $pid;   # the parent exits

	# 1st generation child starts here
	
	# redirect the standard filehandles to nowhere (linux only)
	if ($^O !~ /mswin/) {
		open(STDIN, '/dev/null');
		open(STDOUT, '>>/dev/null') unless $DEBUG;
		open(STDERR, '>>/dev/null') unless $DEBUG;
		# run from the root directory so we don't lock other potential
		# mounts or directories.
		chdir('/');
	}
	
	setsid(); # POSIX; sets us as the process leader (our parent PID is 1)
	umask(0); # don't allow the running user umask affect the daemon's umask

	# 2nd generation child (for SysV; avoids re-acquiring a controlling
	# terminal). setsid() needs to be done before this, see above.
	defined($pid = fork) or die "Can't fork sub-process: $!";
	exit if $pid;
	# now we're no longer the process leader but are in process group 1.

	create_pid_file($pid_file) if $pid_file;
}

# creates a "pid" file with the process ID
sub create_pid_file {
	my ($pid_file, $pid) = @_;
	$pid ||= $$;
	if (open(F, ">$pid_file")) {
		print F $pid;
		close(F);
		chmod(0644, $pid_file);
	} else {
		warn("Can not write PID $pid to file: $pid_file: $!\n");
	}
}

# catch ^C so we can exit gracefully w/o losing any in-memory data
sub CATCH_CONTROL_C {
	$GRACEFUL_EXIT++;
	if ($GRACEFUL_EXIT == 0) {		# WONT HAPPEN (GRACEFUL_EXIT defaults to 0 now)
		if ($opt->daemon) {
		        $GRACEFUL_EXIT++;
			goto C_HERE;
		} 
		syswrite(STDERR, "Caught ^C -- Are you sure? One more will attempt a gracefull exit.\n");
	} elsif ($GRACEFUL_EXIT == 1) {
		C_HERE:
		syswrite(STDERR, "Caught ^C -- Please wait while I try to exit gracefully.\n");
	} else {
		syswrite(STDERR, "Caught ^C -- Alright! I'm done!!! (some data may have been lost)\n");
		main::exit();
	}
	$SIG{INT} = \&CATCH_CONTROL_C;
}

# PS::ErrLog points to this to actually exit on a fatal error, incase I need to
# do some cleanup.
sub main::exit { 
	CORE::exit(@_);
}

END {
	$ERR->info("PsychoStats v$VERSION exiting (elapsed: " . compacttime(time-$starttime) . ", logs: $total_logs, events: " . commify($total_events) . ")") if defined $ERR;
	;;; PS::Core->debug("DEBUG END: " . scalar(localtime) . " (level $DEBUG) File: $DEBUGFILE") if $DEBUGFILE and defined $opt;
	$db->disconnect if $db;
}


##############################################################################
__END__


# if a gametype was specified update the config
my $confupdated = 0;
if (defined $opt->gametype and $conf->getconf('gametype','main') ne $opt->gametype) {
	my $old = $conf->getconf('gametype', 'main');
	$db->update($db->{t_config}, { value => $opt->gametype }, [ conftype => 'main', section => undef, var => 'gametype' ]);
	$conf->set('gametype', $opt->gametype, 'main');
	$ERR->info("Changing gametype from '$old' to '" . $conf->getconf('gametype') . "' (per command line)");
	$confupdated = 1;
}

# if a modtype was specified update the config
if (defined $opt->modtype and $conf->getconf('modtype','main') ne $opt->modtype) {
	my $old = $conf->getconf('modtype', 'main');
	$db->update($db->{t_config}, { value => $opt->modtype }, [ conftype => 'main', section => undef, var => 'modtype' ]);
	$conf->set('modtype', $opt->modtype, 'main');
	$ERR->info("Changing modtype from '$old' to '" . $conf->getconf('modtype') . "' (per command line)");
	$confupdated = 1;
}

# reinitialize the tables if the config was updated above...
if ($confupdated) {
	$db->init_tablenames($conf);
	$db->init_database;	
}

# handle a 'stats reset' request
if (defined $opt->reset) {
	my $game = new PS::Game($conf, $db);
	my $res = $opt->reset;
	my $all = (index($opt->reset,'all') >= 0);
	my %del = (
		players 	=> ($all || (index($res,'player') >= 0)),
		clans   	=> ($all || (index($res,'clan') >= 0)),
		weapons 	=> ($all || (index($res,'weapon') >= 0)),
		heatmaps	=> ($all || (index($res,'heat') >= 0)),
	);
	$game->reset(%del);
	&main::exit();
}

$ERR->debug2("$total config settings loaded.");
$ERR->fatal("No 'gametype' configured.") unless $conf->get_main('gametype');
$ERR->info("PsychoStats v$VERSION initialized.");

# if -unknown is specified, temporarily enable report_unknown
if ($opt->unknown) {
	$conf->set('errlog.report_unknown', 1, 'main');
}

# ------------------------------------------------------------------------------
# rescan clantags
if (defined $opt->scanclantags) {
	my $game = new PS::Game($conf, $db);
	my $all = lc $opt->scanclantags eq 'all' ? 1 : 0;
	$::ERR->info("Rescanning clantags for ranked players.");
	if ($all) {
		$::ERR->info("Removing ALL player to clan relationships.");
		$::ERR->info("All clans will be deleted except profiles.");
		$game->delete_clans(0);
	}

	$game->rescan_clans;

	# force a daily 'clans' update to verify what clans rank
	$opt->set('daily', ($opt->daily || '') . ',clans');
}

# ------------------------------------------------------------------------------
# PERFORM DAILY OPERATIONS and exit if we did any (no logs should be processed)
if ($opt->daily) {
	&main::exit() if do_daily($opt->daily);
}

# ------------------------------------------------------------------------------
# process log sources ... the endless while loop is a placeholder.
my $more_logs = !$opt->nologs;
while ($more_logs) { # infinite loop
	my $logsource = load_logsources();
	if (!defined $logsource or @$logsource == 0) {
		$ERR->fatal("No log sources defined! You must configure a log source (or use -log on command line)!");
	}

	my @total;
	my $game = new PS::Game($conf, $db);
	foreach my $source (@$logsource) {
		my $feeder = new PS::Feeder($source, $game, $conf, $db);
		next unless $feeder;

		# Let Feeder initialize (read directories, establish remote connections, etc).
		my $type = $feeder->init;	# 1=wait; 0=error; -1=nowait;
		next unless $type;		# ERROR

		$conf->setinfo('stats.lastupdate', time) unless $conf->get_info('stats.lastupdate');
		@total = $game->process_feed($feeder);
		$total_logs  += $total[0];
		$total_lines += $total[1];
		$conf->setinfo('stats.lastupdate', time);
		$feeder->done;

		last if $GRACEFUL_EXIT > 0;
	}
	&main::exit() if $GRACEFUL_EXIT > 0;

	last;
}

# check to make sure we don't need to do any daily updates before we exit
check_daily($conf) unless $opt->nodaily;

END {
	$ERR->info("PsychoStats v$VERSION exiting (elapsed: " . compacttime(time-$starttime) . ", logs: $total_logs, lines: $total_lines)") if defined $ERR;
	$opt->debug("DEBUG END: " . scalar(localtime) . " (level $DEBUG) File: $DEBUGFILE") if $DEBUGFILE and defined $opt;
	$db->disconnect if $db;
}

# ------- FUNCTIONS ------------------------------------------------------------

# returns a list of log sources
sub load_logsources {
	my $list = [];
	if ($opt->logsource) {
		my $game = new PS::Game($conf, $db);
		my $log = new PS::Feeder($opt->logsource, $game, $conf, $db);
		if (!$log) {
			$ERR->fatal("Error loading logsource from command line.");
		}
		push(@$list, $log->{logsource});
	} else {
		$list = $db->get_rows_hash("SELECT * FROM $db->{t_config_logsources} WHERE enabled=1 ORDER BY idx");
	}
	return wantarray ? @$list : [ @$list ];
}

# do daily updates, if needed
sub check_daily {
	my ($conf) = @_;
	my @dodaily = ();
	do_daily(join(',', @PS::Game::DAILY));
}

sub do_daily {
	my ($daily) = @_;
	$daily = lc $opt->daily unless defined $daily;
	return 0 unless $daily;

	my %valid = map { $_ => 0 } @PS::Game::DAILY;
	my @badlist = ();
	foreach (split(/,/, $daily)) {
		if (exists $valid{$_}) {
			$valid{$_}++ 
		} else {
			push(@badlist, $_) if $_ ne '';
		}
	}
	$ERR->warn("Ignoring invalid daily options: " . join(',', map { "'$_'" } @badlist)) if @badlist;
	$daily = join(',', $valid{all} ? @PS::Game::DAILY[1..$#PS::Game::DAILY] : grep { $valid{$_} } @PS::Game::DAILY);

	if (!$daily) {
		$ERR->fatal("-daily was specified with no valid options. Must have at least one of the following: " . join(',', @PS::Game::DAILY), 1);
	}
	$ERR->info("Daily updates about to be performed: $daily");

	my $game = new PS::Game($conf, $db);
	foreach (split(/,/, $daily)) {
		my $func = "daily_" . $_;
		if ($game->can($func)) {
			$game->$func;
		} else {
			$ERR->warn("Ignoring daily update '$_': No game support");
		}
	}

	return 1;
}

sub run_as_daemon {
	my ($pid_file) = @_;
	defined(my $pid = fork) or die "Can't fork process: $!";
	exit if $pid;   # the parent exits

	# 1st generation child
	open(STDIN, '/dev/null');
	open(STDOUT, '>>/dev/null') unless $DEBUG;
	open(STDERR, '>>/dev/null') unless $DEBUG;
	chdir('/');     # run from root so we don't lock other potential mounts or directories
	setsid();       # POSIX; sets us as the process leader (our parent PID is 1)
	umask(0);

	# 2nd generation child (for SysV; avoids re-acquiring a controlling terminal)
	# setsid() needs to be done before this, see above.
	defined($pid = fork) or die "Can't fork sub-process: $!";
	exit if $pid;
	# now we're no longer the process leader but are in process group 1.

	if ($pid_file) {
		open(F, ">$pid_file") or warn("Can not write PID $$ to file: $pid_file: $!\n");
		print F $$;
		close(F);
		chmod 0644, $pid_file;
	}
}

# PS::ErrLog points to this to actually exit on a fatal error, incase I need to do some cleanup
sub main::exit { 
#	<> if iswindows();
	CORE::exit(@_) 
}

sub CATCH_CONTROL_C {
	$GRACEFUL_EXIT++;
	if ($GRACEFUL_EXIT == 0) {		# WONT HAPPEN (GRACEFUL_EXIT defaults to 0 now)
		if ($opt->daemon) {
		        $GRACEFUL_EXIT++;
			goto C_HERE;
		} 
		syswrite(STDERR, "Caught ^C -- Are you sure? One more will attempt a gracefull exit.\n");
	} elsif ($GRACEFUL_EXIT == 1) {
C_HERE:
		syswrite(STDERR, "Caught ^C -- Please wait while I try to exit gracefully.\n");
	} else {
		syswrite(STDERR, "Caught ^C -- Alright! I'm done!!! (some data may have been lost)\n");
		&main::exit();
	}
	$SIG{INT} = \&CATCH_CONTROL_C;
}

__DATA__

# If no stats.cfg exists then this config is loaded instead

dbtype = mysql
dbhost = localhost
dbport = 
dbname = psychostats
dbuser = 
dbpass = 
dbtblprefix = ps_
