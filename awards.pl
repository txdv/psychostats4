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
		exit 1;
	}
}

use PS::SourceFilter;
use PS::Core;
use PS::CmdLine::Awards;
use PS::DBI;
use PS::Config;
use PS::ErrLog;
use PS::Award;
use File::Spec::Functions qw( catfile );
use Time::Local;
use POSIX qw( strftime );
use util qw( compacttime print_r abbrnum commify date );

# The $VERSION and $PACKAGE_DATE are automatically updated via the packaging script.
our $VERSION = '4.0';
our $PACKAGE_DATE = time;
our $REVISION = ('$Rev$' =~ /(\d+)/)[0] || '000';

our $DEBUG = 0;					# Global DEBUG level
our $DEBUGFILE = undef;				# Global debug file to write debug info too
our $ERR;					# Global Error handler
our $DBCONF = {};				# Global database config
our $GRACEFUL_EXIT = 0; #-1;			# (used in CATCH_CONTROL_C)

my ($opt, $dbconf, $db, $conf);

$opt = new PS::CmdLine::Awards;			# Initialize command line
$DEBUG = int($ENV{PSYCHOSTATS_DEBUG}) || 0;	# sets global debugging

# display our version and exit
if ($opt->version) {
	print "PsychoStats Awards version $VERSION (rev $REVISION)\n";
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

# Load the basic stats.cfg for database settings (unless 'noconfig' is specified
# on the command line) The config filename can be specified on the commandline,
# otherwise stats.cfg is used. If that file does not exist then the config is
# loaded from the __DATA__ block of this file.
$dbconf = {};
if (!$opt->noconfig) {
	if ($opt->config) {
		;;; PS::Core->debug("Loading DB config from " . $opt->config);
		$dbconf = PS::Config->LOAD_FILE( $opt->config );
	} elsif (-f catfile($FindBin::Bin, 'stats.cfg')) {
		;;; PS::Core->debug("Loading DB config from " . catfile($FindBin::Bin, 'stats.cfg'));
		$dbconf = PS::Config->LOAD_FILE( catfile($FindBin::Bin, 'stats.cfg') );
	} else {
		;;; PS::Core->debug("Loading DB config from __DATA__");
		$dbconf = PS::Config->LOAD_FILE( *DATA );
	}
	if (!$dbconf) {
		die "Error loading DB config!\n";
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

# setup config and error reporting handlers.
$conf = new PS::Config($db);
$ERR = new PS::ErrLog($db, $conf);	# Errors will be logged to the DB
PS::Core->set_verbose($opt->verbose and !$opt->quiet);
PS::ErrLog->set_verbose($opt->verbose and !$opt->quiet);

my $gametype = $opt->gametype;
my $modtype  = $opt->modtype;

# The -gametype and -modtype must always be specified (unless there's only a
# single game/mod available in the system)
if (!$gametype or !$modtype) {
	# lookup available gametype/modtype and if there's only a single set
	# then use it instead of erroring.
	my $cmd = "SELECT gametype,modtype FROM $db->{t_plr} WHERE 1=1";
	my @bind;
	if (defined $gametype) {
		$cmd .= " AND gametype=?";
		push(@bind, $gametype);
	}
	if (defined $modtype) {
		$cmd .= " AND modtype=?";
		push(@bind, $modtype);
	}
	$cmd .= " GROUP BY gametype,modtype";
	my @list = $db->get_rows_array($cmd, @bind);
	if (@list == 1) {
		$gametype = $list[0][0];
		$modtype  = $list[0][1];
	} else {
		warn("Error: Valid -gametype and -modtype paramaters are required.\n");
		undef $ERR; # prevent END block from saying anything
		exit;
	}
}

# Reset all awards? This is useful when the awards config is changed.
if (defined $opt->reset) {
	# TODO: this should be changed to only delete awards that are part of
	# the gametype::modtype specified.
	$db->delete($db->{t_awards});
	$db->delete($db->{t_awards_plrs});
}

$ERR->verbose("Scanning $gametype\::$modtype database for awards calculations.");

# determine the available date ranges in the DB (oldest and newest)
# 12:00:00 is added to help avoid epoch math errors in the routines below this.
my ($oldest, $oldest_ymd, $newest, $newest_ymd, $date_diff) = $db->get_row_array(qq{
	SELECT
		UNIX_TIMESTAMP(CONCAT(MIN(statdate),' 12:00:00')),
		MIN(statdate),
		UNIX_TIMESTAMP(CONCAT(MAX(statdate),' 12:00:00')),
		MAX(statdate),
		DATEDIFF(MAX(statdate), MIN(statdate))+1
	FROM $db->{t_plr_data} d, $db->{t_plr} p
	WHERE p.plrid=d.plrid AND gametype=? AND modtype=?
}, $gametype, $modtype);
if (!$oldest) {
	$ERR->verbose("No historical stats available.");
	exit 2;
} else {
	$ERR->verbose("Date range available: $oldest_ymd - $newest_ymd ($date_diff days).");
}

# collect configuration settings to determine what ranges to calculate
my $dodaily 		= $conf->global->awards->daily;
my $doweekly 		= $conf->global->awards->weekly;
my $domonthly 		= $conf->global->awards->monthly;
my $fulldayonly 	= !$conf->global->awards->allow_partial_day;
my $fullweekonly 	= !$conf->global->awards->allow_partial_week;
my $fullmonthonly 	= !$conf->global->awards->allow_partial_month;

if (!$dodaily and !$doweekly and !$domonthly) {
	$ERR->verbose("Awards are disabled.");
	exit 3;
}

my $oneday = 60*60*24;
my $oneweek = $oneday * 7;

# fetch award configs
# TODO: allow use of: -award "award name"
my @awards = $db->get_rows_hash(qq{
	SELECT *
	FROM $db->{t_config_awards}
	WHERE
		enabled=1
		AND (gametype IS NULL OR gametype=?)
		AND (modtype IS NULL OR modtype=?)
	ORDER BY type,caption
}, $gametype, $modtype);

if (!@awards) {
	$ERR->verbose("No awards configured.");
	exit 4;
}

# loop through each award configuration and calculate who won each for any date
# range that does not already have the award calculated.
foreach my $award_conf (@awards) {
	# initialize award object
	my $award = new PS::Award($db, @$award_conf{qw(type class)}, $gametype, $modtype); 
	if (!$award) {
		# The award class didn't exist or had an error initializing...
		$ERR->warn("Error initializing award '$award_conf->{caption}': $@");
		next;
	}

	# setup common award values ...
	$award
		->min_value(1)
		->expr($award_conf->{expression})
		->where($award_conf->{where_clause})
		->order($award_conf->{order_by})
		->ranked_only($award_conf->{ranked_only})
		->limit($award_conf->{player_limit});

	
	# CALCULATE MONTHLY AWARDS
	if ($domonthly) {
		# curdate will start on the 1st day of the month
		my $curdate = timegm(0,0,12,1,(gmtime($oldest))[4,5]);
		my $range = 'month';
		$ERR->verbose("Monthly award for \"$award_conf->{caption}\" ...");
		while ($curdate <= $newest) {
			my $onemonth = $oneday * $award->days_in_month($curdate);
			my $is_partial = $curdate + $onemonth - $oneday > $newest;
			last if $fullmonthonly and $is_partial;

			# Ignore awards for this date if it was already complete
			if (!$award->is_complete($curdate, $range, $award_conf->{id})) {
				$ERR->verbose(strftime("  %b %Y", gmtime($curdate)));

				# calculate the award
				my $list = $award
					->date_range($curdate, $range)
					->calc();

				if (@$list) {
					save_award($award_conf, $range, $curdate, $is_partial, $list);
				} elsif (!$is_partial) {
					save_empty_award($award_conf, $range, $curdate);
				}
			}
			
			$curdate += $onemonth;
		}
	}


	# CALCULATE WEEKLY AWARDS
	if ($doweekly) {
		# curdate will start at the beginning of the week
		my $curdate = $oldest - ($oneday * (gmtime($oldest))[6]);
		my $range = 'week';
		# move to monday if configured to do so
		$curdate += $oneday if $startofweek eq 'monday';
		$ERR->verbose("Weekly award for \"$award_conf->{caption}\" ...");
		while ($curdate <= $newest) {
			my $is_partial = $curdate + $oneweek - $oneday > $newest;
			last if $fullweekonly and $is_partial;

			# Ignore awards for this date if it was already complete
			if (!$award->is_complete($curdate, $range, $award_conf->{id})) {
				$ERR->verbose(strftime("  %a, %b %d %Y", gmtime($curdate)));

				# calculate the award
				my $list = $award
					->date_range($curdate, $range)
					->calc();

				if (@$list) {
					save_award($award_conf, $range, $curdate, $is_partial, $list);
				} elsif (!$is_partial) {
					save_empty_award($award_conf, $range, $curdate);
				}
			}
			
			$curdate += $oneweek;
		}
	}

	# CALCULATE DAILY AWARDS
	if ($dodaily) {
		my $curdate = $oldest;
		my $range = 'day';
		$ERR->verbose("Daily award for \"$award_conf->{caption}\" ...");
		while ($curdate <= $newest) {
			my $is_partial = $curdate + $oneday > $newest;
			last if $fulldayonly and $is_partial;

			# Ignore awards for this date if it was already complete
			if (!$award->is_complete($curdate, $range, $award_conf->{id})) {
				$ERR->verbose(strftime("  %a, %b %d %Y", gmtime($curdate)));

				# calculate the award
				my $list = $award
					->date_range($curdate, $range)
					->calc();

				if (@$list) {
					save_award($award_conf, $range, $curdate, $is_partial, $list);
				} elsif (!$is_partial) {
					save_empty_award($award_conf, $range, $curdate);
				}
			}
			
			$curdate += $oneday;
		}
	}

	$award->done;
}

sub save_award {
	my ($award_conf, $range, $curdate, $is_partial, $list) = @_;
	my $id = $db->next_id($db->{t_awards});
	my $save = {
		id		=> $id,
		conf_id		=> $award_conf->{id},
		caption		=> $award_conf->{caption},
		award_range	=> $range,
		award_date	=> strftime('%Y-%m-%d', gmtime($curdate)),
		award_item	=> undef,
		completed	=> $is_partial ? undef : timegm(gmtime),
		interpolate	=> undef,
		plrid		=> $list->[0]{plrid},
		value		=> $list->[0]{value},
	};
	
	# delete the duplicate award and plrs					
	my $delete = $db->select($db->{t_awards}, 'id', [
		conf_id 	=> $save->{conf_id},
		award_range	=> $save->{award_range},
		award_date	=> $save->{award_date},
	]);
	$db->delete($db->{t_awards}, [ id => $delete ]);
	$db->delete($db->{t_awards_plrs}, [ award_id => $delete ]);
	
	# save the new award
	$db->insert($db->{t_awards}, $save);
	
	# save all players
	my $idx = 0;
	foreach my $plr (@$list) {
		$db->insert($db->{t_awards_plrs}, {
			id	=> $db->next_id($db->{t_awards_plrs}),
			award_id=> $save->{id},
			idx	=> ++$idx,
			plrid	=> $plr->{plrid},
			value	=> $plr->{value},
		});
	}
}

sub save_empty_award {
	my ($award_conf, $range, $curdate) = @_;
	# save an 'empty' award so it doesn't try and get calculated over and over
	$db->insert($db->{t_awards}, {
		id		=> $db->next_id($db->{t_awards}),
		conf_id		=> $award_conf->{id},
		caption		=> $award_conf->{caption},
		award_range	=> $range,
		award_date	=> strftime('%Y-%m-%d', gmtime($curdate)),
		completed	=> timegm(gmtime),
		empty		=> 1,
	});
}

# -----------------------------------------------------------------------------
END {
	;;;if ($DEBUGFILE) {
	;;;	PS::Core->debug("DEBUG END: " . scalar(localtime) . " (level $DEBUG) File: $DEBUGFILE")
	;;;}
	$db->disconnect if $db;
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
