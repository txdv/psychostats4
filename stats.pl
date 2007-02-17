#!/usr/bin/perl
#
#	$Id$
#

# FindBin isn't going to work on systems that run the stats.pl as SETUID
BEGIN { 
	use strict;
	use warnings;

	use FindBin; 
	use lib $FindBin::Bin;
	use lib $FindBin::Bin . "/lib";
}

BEGIN { # make sure we're running the minimum version of perl required
	my $minver = 5.8;
	my $curver = 0.0;
	my ($major,$minor,$release) = split(/\./,sprintf("%vd", $^V));
	$curver = "$major.$minor";
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
	my @modules = qw( DBI DBD::mysql );
	my @failed_at_life = ();
	foreach my $module (@modules) {
		my $V = '';
		eval "use $module; \$V = \$${module}::VERSION;";
		if ($@) {	# module not found
			push(@failed_at_life, $module);
		} else {	# module loaded ok; store for later, if -V is used for debugging purposes
			$PM_LOADED{$module} = $V;
		}
	}

	# if anything failed, kill ourselves, life isn't worth living.
	if (@failed_at_life) {
		print "PsychoStats failed initialization!\n";
		print "The following modules are required and could not be loaded.\n";
		print "\t" . join("\n\t", @failed_at_life) . "\n";
		print "\n";

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

use Data::Dumper;
use File::Spec::Functions qw(catfile);
use PS::CmdLine;
use PS::DB;
use PS::Config;					# use'd here only for the loadfile() function
use PS::ConfigHandler;
use PS::ErrLog;
use PS::Feeder;
use PS::Game;
use util qw( :win compacttime );

# The $VERSION and $PACKAGE_DATE are automatically updated via the packaging script.
our $VERSION = '3.0';
our $PACKAGE_DATE = time;
our $REVISION = ('$Rev$' =~ /(\d+)/)[0] || '0';

our $DEBUG = 0;					# Global DEBUG level
our $DEBUGFILE = undef;				# Global debug file to write debug info too
our $ERR;					# Global Error handler (PS::Debug uses this)
our $DBCONF = {};				# Global database config
our $GRACEFUL_EXIT = 0; #-1;			# (used in CATCH_CONTROL_C)

$SIG{INT} = \&CATCH_CONTROL_C;

my ($opt, $dbconf, $db, $conf, $game, $logsource, $feeder, @dodaily);
my $starttime = time;
my $totallogs = 0;

#binmode(STDOUT, ":utf8");

$opt = new PS::CmdLine;				# Initialize command line paramaters
$DEBUG = $opt->get('debug') || 0;		# sets global debugging for ALL CLASSES

# display our version and exit
if ($opt->get('version')) {
	print "PsychoStats version $VERSION (rev $REVISION)\n"; # (Perl " . sprintf("%vd", $^V) . ")\n";
	print "Packaged on " . scalar(localtime $PACKAGE_DATE) . "\n";
#	print "Author:  Jason Morriss <stormtrooper\@psychostats.com>\n";
	print "Website: http://www.psychostats.com/\n";
	print "Perl version " . sprintf("%vd", $^V) . " ($^O)\n";
	print "Loaded Modules:\n";
	my $len = 1;
	foreach my $pm (keys %PM_LOADED) {	# get max length first, so we can be pretty
		$len = length($pm) if length($pm) > $len;
	}
	$len += 2;
	foreach my $pm (keys %PM_LOADED) {
		printf("  %-${len}sv%s\n", $pm, $PM_LOADED{$pm});
	}
	exit;
}

if (defined(my $df = $opt->get('debugfile'))) {
	$df = 'debug.txt' unless $df;		# if filename is empty
	$DEBUGFILE = $df;
	$DEBUG = 1 unless $DEBUG;		# force DEBUG on if we're specifying a file
	$opt->debug("DEBUG START: " . scalar(localtime) . " (level $DEBUG) File: $DEBUGFILE");
}

# Load the basic stats.cfg for database settings (unless 'noconfig' is specified on the command line)
# The config filename can be specified on the commandline, otherwise stats.cfg is used. If that file 
# does not exist then the config is loaded from the __DATA__ block of this file.
$dbconf = {};
if (!$opt->get('noconfig')) {
	if ($opt->get('config')) {
		PS::Debug->debug("Loading DB config from " . $opt->get('config'));
		$dbconf = PS::Config->loadfile( $opt->get('config') );
	} elsif (-e catfile($FindBin::Bin, 'stats.cfg')) {
		PS::Debug->debug("Loading DB config from stats.cfg");
		$dbconf = PS::Config->loadfile( catfile($FindBin::Bin, 'stats.cfg') );
	} else {
		PS::Debug->debug("Loading DB config from __DATA__");
		$dbconf = PS::Config->loadfile( *DATA );
	}
} else {
	PS::Debug->debug("-noconfig specified, No DB config loaded.");
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
	dbtblprefix	=> $opt->dbtblprefix || $dbconf->{dbtblprefix}
};
$db = PS::DB->new($DBCONF);

$conf = new PS::ConfigHandler($opt, $db);
my $total = $conf->load(qw( main ));
$ERR = new PS::ErrLog($conf, $db);			# Now all error messages will be logged to the DB

$db->init_tablenames($conf);
$db->init_database;

if (defined $opt->get('reset')) {
	my $game = PS::Game->new($conf, $db);
	my %del = (
		players => (index($opt->get('reset'),'pl') >= 0 || index($opt->get('reset'),'all') >= 0),
		clans   => (index($opt->get('reset'),'cl') >= 0 || index($opt->get('reset'),'all') >= 0)
	);
	$game->reset(%del);
	exit;
}

$ERR->debug2("$total config settings loaded.");
$ERR->fatal("No 'gametype' configured.") unless $conf->get_main('gametype');
$ERR->info("PsychoStats v$VERSION initialized.");

# force 'daily' option if we're trying to calculate a single day award
if (defined $conf->get_opt('award') and $conf->get_opt('start')) {
	$opt->set('daily', 'awards');
}

# if -unknown is specified, temporarily enable report_unknown
if ($opt->get('unknown')) {
	$conf->set('errlog.report_unknown', 1, 'main');
}

# if a modtype was specified update the config
if (defined $opt->get('modtype') and $conf->getconf('modtype','main') ne $opt->get('modtype')) {
	$db->update($db->{t_config}, { value => $opt->get('modtype') }, [ conftype => 'main', section => '', var => 'modtype' ]);
	my $oldmod = $conf->getconf('modtype', 'main');
	$conf->set('modtype', $opt->get('modtype'), 'main');
	$ERR->info("Changing modtype from '$oldmod' to '" . $conf->get_main('modtype') . "' (per command line)");
}

# rescan clantags
if (defined $opt->get('scanclantags')) {
	my $all = lc $opt->get('scanclantags') eq 'all' ? 1 : 0;
	# remove all current clans and player relationships (profiles remain untouched)
	$::ERR->info("Rescanning clantags for ranked players.");
	if ($all) {
		$::ERR->info("Removing ALL player to clan relationships.");
		$db->query("UPDATE $db->{t_plr} SET clanid=0 WHERE clanid != 0");
		$::ERR->info("Deleting all clans (profiles will remain intact).");
		$db->query("DELETE FROM $db->{t_clan}");
	}

	my $total = $db->count($db->{t_plr}, [ allowrank => 1, clanid => 0 ]);
	$::ERR->info("$total ranked players will be scanned.");

	my $game = PS::Game->new($conf, $db);
	my $clanid;
	my $cur = 1;
	my $clans = {};
	my $members = 0;
	my $plrsth = $db->query("SELECT p.plrid,pp.uniqueid,pp.name FROM $db->{t_plr} p, $db->{t_plr_profile} pp WHERE p.uniqueid=pp.uniqueid and p.allowrank=1 and p.clanid = 0");
	while (my ($plrid,$uniqueid,$name) = $plrsth->fetchrow_array) {
		$::ERR->verbose(sprintf("%6.2f%% completed.\r", $cur++ / $total * 100), 1);
		$clanid = $game->scan_for_clantag($name) || next;
		$clans->{$clanid}++;
		$members++;
		$db->update($db->{t_plr}, { clanid => $clanid }, [ plrid => $plrid ]);
	}
	$::ERR->verbose("");
	$::ERR->info(sprintf("%d clans with %d members found.", scalar keys %$clans, $members));

	$opt->set('daily', ($opt->get('daily') || '') . ',clans');
}

# PERFORM DAILY OPERATIONS 
# This will exit afterwards (no logs are processed)
DODAILY:
if (my $daily = lc $opt->get('daily')) {
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

	$game = PS::Game->new($conf, $db);
	foreach (split(/,/, $daily)) {
		my $func = "daily_$_";
		if ($game->can($func)) {
			$game->$func;
		} else {
			$ERR->warn("Ignoring daily update '$_': No game support");
		}
	}

	&main::exit;
}

# --- Now we can get down to business! Initializing the proper game engine and log feeders
# endless while loop is a place holder for now ...
while (!$opt->get('nologs')) {
	$logsource = $conf->get_main('logsource');
	if (!defined $logsource) {
		$ERR->warn("No logsource defined! Aborting stats update!");
		last;
	}
	$logsource = [ $logsource ] unless ref $logsource;	# force it into an array ref

	$game = PS::Game->new($conf, $db);
	foreach my $source (@$logsource) {
		next unless $source;				# ignore empty sources
		# ignore feeder if it returns undef (undef = specified feeder is not implemented)
		$feeder = PS::Feeder->new($source, $game, $conf, $db) || next;

		# Let Feeder initialize (read directories, establish remote connections, etc)
		my $type = $feeder->init;			# the feeder will report any errors that occur
		next unless $type;

		$conf->setinfo('stats.lastupdate', time) unless $conf->get_info('stats.lastupdate');
		$totallogs += $game->process_feed($feeder);
		$conf->setinfo('stats.lastupdate', time);

		$feeder->done;

		&main::exit if $GRACEFUL_EXIT > 0;
	}
	&main::exit if $GRACEFUL_EXIT > 0;

	# If we're running as a daemon then we loop, 
	# otherwise we exit after a single iteration of the loop
	# a proper 'daemonize' function has not been added yet
	if ($opt->get('daemon')) {	# only accept 'daemon' from the command line, not the config
		sleep(5);		# sleep for a bit before we loop again
	} else {
		last;
	}
}

# check for auto.update_* options
foreach my $v (@PS::Game::DAILY) {
	my $lastupdate = $conf->getinfo("daily_$v.lastupdate") || 0;
	my $when = $conf->get_main("auto.update_$v");
	next unless $when;
	my $offset = (time - $lastupdate) / 60;		# number of minutes since last update
	if ($lastupdate) {
		push(@dodaily, $v) if
			($when eq 'all') ||
			($when eq 'hourly'  and $offset >= 60) || 
			($when eq 'daily'   and $offset >= 60*24) || 
			($when eq 'weekly'  and $offset >= 60*24*6) || 
			($when eq 'monthly' and $offset >= 60*24*28);
	} else {
		push(@dodaily, $v);
	}
}
if (@dodaily) {
	$opt->set('daily', join(',',@dodaily));
#	print $opt->get('daily'),"\n";
	goto DODAILY;
}

# PS::ErrLog points to this to actually exit on a fatal error, incase I need to do some cleanup
sub main::exit { 
#	<> if iswindows();
	CORE::exit(@_) 
}

END {
	$ERR->info("PsychoStats v$VERSION exiting (elapsed: " . compacttime(time-$starttime) . "; logs: $totallogs)") if defined $ERR;
	$opt->debug("DEBUG END: " . scalar(localtime) . " (level $DEBUG) File: $DEBUGFILE") if $DEBUGFILE and defined $opt;
}


sub CATCH_CONTROL_C {
	$GRACEFUL_EXIT++;
	if ($GRACEFUL_EXIT == 0) {		# WONT HAPPEN (GRACEFUL_EXIT defaults to 0 now)
		if ($opt->get('daemon')) {
		        $GRACEFUL_EXIT++;
			goto C_HERE;
		} 
		syswrite(STDERR, "Caught ^C -- Are you sure? One more will attempt a gracefull exit.\n");
	} elsif ($GRACEFUL_EXIT == 1) {
C_HERE:
		syswrite(STDERR, "Caught ^C -- Please wait while I try to exit gracefully.\n");
	} else {
		syswrite(STDERR, "Caught ^C -- Alright! I'm done!!! (some data may have been lost)\n");
		&main::exit;
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
