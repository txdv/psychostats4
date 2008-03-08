#!/usr/bin/perl
#
#	$Id$
#

BEGIN { # FindBin isn't going to work on systems that run the stats.pl as SETUID
	use FindBin; 
	use lib $FindBin::Bin;
	use lib $FindBin::Bin . "/lib";
}

use strict;
use warnings;

use File::Spec::Functions qw(catfile);
use XML::Simple;
use PS::CmdLine::Heatmap;
use PS::DB;
use PS::Config;					# use'd here only for the loadfile() function
use PS::ConfigHandler;
use PS::ErrLog;
use PS::Heatmap;
use util qw( expandlist print_r );

our $VERSION = '1.00.' . (('$Rev$' =~ /(\d+)/) || '000')[0];

our $DEBUG = 0;					# Global DEBUG level
our $DEBUGFILE = undef;				# Global debug file to write debug info too
our $ERR;					# Global Error handler (PS::Debug uses this)
our $DBCONF = {};				# Global database config
our $GRACEFUL_EXIT = 0; #-1;			# (used in CATCH_CONTROL_C)

my ($opt, $dbconf, $db, $conf);

$opt = new PS::CmdLine::Heatmap;		# Initialize command line paramaters
$DEBUG = $opt->get('debug') || 0;		# sets global debugging for ALL CLASSES

# display our version and exit
if ($opt->get('version')) {
	print "PsychoHeat version $VERSION\n";
	print "Website: http://www.psychostats.com/\n";
	print "Perl version " . sprintf("%vd", $^V) . " ($^O)\n";
	exit;
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
$db = new PS::DB($DBCONF);

$conf = new PS::ConfigHandler($opt, $db);
my $total = $conf->load(qw( main ));
$ERR = new PS::ErrLog($conf, $db);			# Now all error messages will be logged to the DB

# -------------------------------- HEATMAP CODE STARTS HERE -----------------------------------------------------

my $mapxml  = $opt->mapinfo || catfile($FindBin::RealBin, 'heat.xml');
my $mapinfo = XMLin($mapxml)->{map};
#use Data::Dumper; print Dumper($mapinfo); exit;

# Create a list of maps to generate heat images for. If no map is specified we assume 'all'
my $maplist = {};

if ($opt->mapname and lc $opt->mapname ne 'all') {
	my $mapname = $opt->mapname;
	my $mapid   = $db->select($db->{t_map}, 'mapid', "uniqueid=" . $db->quote($mapname));
	die("Map '$mapname' not found in database.\n") unless $mapid;
	$maplist->{$mapname} = $mapid;
} else {
	my @list = $db->get_rows_hash("SELECT mapid,uniqueid FROM $db->{t_map} WHERE uniqueid <> 'unknown' ORDER BY uniqueid");
	foreach my $m (@list) {
		$maplist->{$m->{uniqueid}} = $m->{mapid};
	}
}

{ # private scope
	my @ignored = ();
	foreach my $mapname (keys %$maplist) {
		unless (exists $mapinfo->{$mapname}) {
			delete $maplist->{$mapname};
			push(@ignored, $mapname);
		}
	}
	if (@ignored) {
		warn("Ignoring maps with no mapinfo available: " . join(", ", map { "'$_'" } @ignored) . ".\n") unless $opt->quiet;
	}
}

# make sure we have at least 1 map to process
if (keys %$maplist) {
	my $total = scalar keys(%$maplist);
	warn(sprintf("%d map%s ready to be processed.\n", $total, $total == 1 ? '' : 's')) unless $opt->quiet;
} else {
	die("No maps available. Exiting.\n") unless $opt->quiet;
}

# if a weapon is specified, find it's ID value
my $weaponid = undef;
if ($opt->weapon) {
	$weaponid = $db->select($db->{t_weapon}, 'weaponid', "uniqueid=" . $db->quote($opt->weapon));
	die("Weapon '" . $opt->weapon  . "' not found in database.\n") unless $weaponid;
}

my $headshot = undef;
if (defined $opt->headshot) {
	$headshot = defined $opt->headshot ? 1 : 0;
}

my $team = undef;
my $q_team = undef;
if (defined $opt->team) {
	$team = $opt->team;
	$q_team = $db->quote($team);
}

# setup some defaults and command overrides for our config
my $hc = $conf->get_main('heatmap');				# 'heatmap.*' config from database
delete @$hc{qw( SECTION IDX )};					# remove useless variables
$hc->{limit} = $opt->limit if $opt->exists('limit');
$hc->{brush} = $opt->brush if $opt->exists('brush');
$hc->{scale} = $opt->scale if $opt->exists('scale');
$hc->{format} = $opt->format if $opt->exists('format');
$hc->{hourly} = 1 if $opt->exists('hourly');
$hc->{overlay} = $opt->overlay if $opt->exists('overlay');
if ($hc->{hourly} and !$opt->exists('hourly') and !$opt->nohourly) {
	$opt->set('hourly', '0-23');
}

if ($hc->{hourly} and !$hc->{format}) {
	$hc->{format} = "%m_%h.png";
}

$hc->{who} = $opt->who || 'victim';
if (substr($hc->{who},0,1) eq 'v') {
	$hc->{who_x} = 'vx';		# victims
	$hc->{who_y} = 'vy';
} else {
	$hc->{who_x} = 'kx';		# killers
	$hc->{who_y} = 'ky';
}
my $where = '';

#$opt->statdate(date('%Y-%m-%d')) unless defined $opt->statdate;

# build a specific where clause if extra parameters are given
$where .= "AND statdate=" . $db->quote($opt->statdate) . " " if $opt->statdate;
$where .= "AND vteam=" . $db->quote($opt->vteam) . " " if $opt->vteam;
$where .= "AND kteam=" . $db->quote($opt->kteam) . " " if $opt->kteam;
$where .= "AND (kteam=$q_team OR vteam=$q_team) " if $team;
$where .= "AND weaponid=$weaponid " if $weaponid;
$where .= "AND heatshot=$headshot " if defined $headshot;

# loop through our map list and process each map
while (my ($mapname, $mapid) = each(%$maplist)) {
	my $idx = 0;
	my $info = $mapinfo->{$mapname};
	my $datax = [];
	my $datay = [];
	my @res = split(/x/, $info->{res});
	my $heat = new PS::Heatmap( 
		width	=> $res[0] || 100,
		height	=> $res[1] || 100,
		scale	=> $hc->{scale} || 2,
		brush	=> $hc->{brush} || 'medium',
		background => $hc->{overlay}, 
	);
	$heat->boundary($info->{minx}, $info->{miny}, $info->{maxx}, $info->{maxy});
	$heat->flip($info->{flipv}, $info->{fliph});

	$hc->{mapname} = $mapname;
	$hc->{mapid} = $mapid;
	if ($opt->hourly) {
		my @hours = expandlist($opt->hourly);
		my $w;
		foreach my $hour (@hours) {
			$hc->{idx} = ++$idx;
			$hc->{hour} = sprintf('%02d', $hour);
			$w = "AND hour=$hour $where";
			get_data($hc, $datax, $datay, $w);
			$heat->data($datax, $datay);
			my $file = file_format($hc->{format}, $hc);
			warn "Creating heatmap #$idx (h$hc->{hour}) $file\n" unless $opt->quiet;
			print $heat->render($file);
		}
	} else {
		$hc->{idx} = ++$idx;
		get_data($hc, $datax, $datay, $where);
		$heat->data($datax, $datay);
		warn "Creating heatmap #$idx for $hc->{mapname} \n" unless $opt->quiet;
		print $heat->render($opt->file);
	}
}

sub get_data {
	my ($hc, $datax, $datay, $where) = @_;
	$where ||= '';
	@$datax = ();
	@$datay = ();
	my $limit = $hc->{limit} || 5500;
	my $cmd = "SELECT $hc->{who_x},$hc->{who_y} FROM $db->{t_map_spatial} WHERE mapid=$hc->{mapid} ";

	$cmd .= $where if $where;

	$cmd .= "LIMIT $limit";

	warn "$cmd\n" if $opt->sql;
	my $st = $db->query($cmd);
	while (my ($x1,$y1) = $st->fetchrow_array) {
		push(@$datax, $x1);
		push(@$datay, $y1);
	}
	undef $st;
}

sub file_format {
	my ($fmt, $hc) = @_;
	my $str = $fmt;
	$str =~ s/%%/%z/g;
	$str =~ s/%m/$hc->{mapname}/ge;
	$str =~ s/%i/$hc->{idx}/ge;
	$str =~ s/%h/$hc->{hour}/ge;
	$str =~ s/%z/%/g;
	return $str;
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
