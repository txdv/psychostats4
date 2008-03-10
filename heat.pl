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

use File::Spec::Functions qw( catfile splitpath );
use XML::Simple;
use Digest::SHA1 qw( sha1_hex );
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

# read in our map info XML file that defines heatmap dimensions, etc...
my $mapxml  = $opt->mapinfo || catfile($FindBin::RealBin, 'heat.xml');
my $mapinfo = XMLin($mapxml)->{map};
#use Data::Dumper; print Dumper($mapinfo); exit;

# Create a list of maps to generate heat images for. If no map is specified we assume 'all'
my $maplist = {};

if ($opt->mapname and lc $opt->mapname ne 'all') {
	my $mapname = $opt->mapname;
	my $mapid;
	if ($mapname !~ /^\d+$/) {
		$mapid = $db->select($db->{t_map}, 'mapid', "uniqueid=" . $db->quote($mapname));
	} else {
		$mapid = $mapname;
		$mapname = $db->select($db->{t_map}, 'uniqueid', "mapid=" . $db->quote($mapid));
		$mapid = undef unless $mapname;
	}
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

# do not echo SQL statements if quiet is specified
if ($opt->quiet and $opt->sql) {
	$opt->del('sql');
}

# STATDATE ALWAYS HAS TO BE SET TO SOMETHING 
# default the date to the newest date in the database
if (!defined $opt->statdate) {
	my $date = $db->max($db->{t_map_spatial}, 'statdate');
	$opt->statdate($date) if $date;
	die "No spatial stats are available! Aborting!\n" if !$date;
}

# if a weapon is specified, find its ID value
if ($opt->weapon and $opt->weapon !~ /^\d+$/) {
	my $weaponid = $db->select($db->{t_weapon}, 'weaponid', "uniqueid=" . $db->quote($opt->weapon));
	die("Weapon '" . $opt->weapon . "' not found in database.\n") unless $weaponid;
	$opt->weapon($weaponid);
}

if ($opt->player and ($opt->killer or $opt->victim)) {
	warn "Notice: parameter -player overrides -killer and -victim parameters.\n";
	$opt->del('killer');
	$opt->del('victim');
}

# if a player is specified and is not numeric, find their ID based off their uniqueid
if ($opt->player and $opt->player !~ /^\d+$/) {
	my $plrid = $db->select($db->{t_plr}, 'plrid', "uniqueid=" . $db->quote($opt->player));
	die("Player '" . $opt->player . "' not found in database.\n") unless $plrid;
	$opt->player($plrid);
}

# if a killer is specified and is not numeric, find their ID based off their uniqueid
if ($opt->killer and $opt->killer !~ /^\d+$/) {
	my $plrid = $db->select($db->{t_plr}, 'plrid', "uniqueid=" . $db->quote($opt->killer));
	die("Player '" . $opt->killer . "' not found in database.\n") unless $plrid;
	$opt->killer($plrid);
}

# if a victim is specified and is not numeric, find their ID based off their uniqueid
if ($opt->victim and $opt->victim !~ /^\d+$/) {
	my $plrid = $db->select($db->{t_plr}, 'plrid', "uniqueid=" . $db->quote($opt->victim));
	die("Player '" . $opt->victim . "' not found in database.\n") unless $plrid;
	$opt->victim($plrid);
}

# if 'team' is specified then override kteam and vteam
if ($opt->team and ($opt->kteam or $opt->vteam)) {
	warn "Notice: Parameter -team overrides -kteam and -vteam paramters.\n";
	$opt->del('kteam');
	$opt->del('vteam');
} 

# setup some defaults and command overrides for our config that will generate the heatmap
my $hc = $conf->get_main('heatmap');				# 'heatmap.*' config from database (hc = heatmap config)
delete @$hc{qw( SECTION IDX hourly )};				# remove some variables
$hc->{limit} 	= $opt->limit if $opt->exists('limit');
$hc->{brush} 	= $opt->brush if $opt->exists('brush');
$hc->{scale} 	= $opt->scale if $opt->exists('scale');
$hc->{format} 	= $opt->format if $opt->exists('format');
$hc->{overlay} 	= $opt->overlay if $opt->exists('overlay');
$hc->{statdate}	= $opt->statdate if $opt->exists('statdate');
$hc->{weaponid} = $opt->weapon if $opt->exists('weapon');
$hc->{pid} 	= $opt->player if $opt->exists('player');
$hc->{kid} 	= $opt->killer if $opt->exists('killer');
$hc->{vid} 	= $opt->victim if $opt->exists('victim');
$hc->{team} 	= $opt->team if $opt->exists('team');
$hc->{kteam} 	= $opt->kteam if $opt->exists('kteam');
$hc->{vteam} 	= $opt->vteam if $opt->exists('vteam');
$hc->{headshot} = defined $opt->headshot ? $opt->headshot : undef;	# allow for undef, 0, 1
$hc->{hourly} 	= 0 if $opt->exists('nohourly');

# if hourly is enabled, change this option to the hours to generate (all 24 hours by default)
if ($hc->{hourly}) {
	$hc->{hourly} = $opt->hourly || '0-23';
}

# if no format is specified default it to something useful (depending if hourly heatmaps are being created)
if (!$hc->{format}) {
	$hc->{format} = $hc->{hourly} ? "%m_%h.png" : "%m.png";
}

# 'who' defines what set of coordinates will be plotted on the heatmap (killer or victim)
$hc->{who} = $opt->who || 'victim';
if (substr($hc->{who},0,1) eq 'v') {
	$hc->{who} = 'victim';
	$hc->{who_x} = 'vx';
	$hc->{who_y} = 'vy';
} else {
	$hc->{who} = 'killer';
	$hc->{who_x} = 'kx';
	$hc->{who_y} = 'ky';
}

my $where = '';

# build a specific where clause if extra parameters are given
$where .= "AND statdate=" . $db->quote($hc->{statdate}) . " " if $hc->{statdate};
$where .= "AND (kid=$hc->{pid} OR vid=$hc->{pid}) " if $hc->{pid};
$where .= "AND kid=$hc->{kid} " if $hc->{kid};
$where .= "AND vid=$hc->{vid} " if $hc->{vid};
$where .= "AND (kteam=" . $db->quote($hc->{team}) . " OR vteam=" . $db->quote($hc->{team}) . ") " if $hc->{team};
$where .= "AND vteam=" . $db->quote($hc->{vteam}) . " " if $hc->{vteam};
$where .= "AND kteam=" . $db->quote($hc->{kteam}) . " " if $hc->{kteam};
$where .= "AND weaponid=$hc->{weaponid} " if $hc->{weaponid};
$where .= "AND headshot=$hc->{headshot} " if defined $hc->{headshot};

# loop through our map list and process each map
while (my ($mapname, $mapid) = each(%$maplist)) {
	my $idx = 0;
	my $info = $mapinfo->{$mapname};
	my $datax = [];
	my $datay = [];
	my $png;
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
#		my $path = $opt->file || catfile('.','');
#		die "Option -o must point to a directory when creating hourly heatmaps.\n" unless $path and -d $path;
		foreach my $hour (@hours) {
			$hc->{idx} = ++$idx;
			$hc->{hour} = sprintf('%02d', $hour);
			$w = "AND hour=$hour $where";
			get_data($hc, $datax, $datay, $w);
			$heat->data($datax, $datay);
#			my $file = catfile($path, file_format($hc->{format}, $hc));
			warn "Creating heatmap for $mapname (hour $hc->{hour}) ...\n" unless $opt->quiet;
			$png = $heat->render();
			save_png($png, $hc);
		}
	} else {
		$hc->{hour} = undef;
		$hc->{idx} = ++$idx;
		get_data($hc, $datax, $datay, $where);
		$heat->data($datax, $datay);
		warn "Creating heatmap for $mapname ...\n" unless $opt->quiet;
		$png = $heat->render();
		save_png($png, $hc);
	}
}

# save the PNG data into the DB or as a file
sub save_png {
	my ($data, $hc) = @_;
	my $out = $opt->file || 'DB';
	my @vars = qw(mapid weaponid statdate hour headshot who pid kid vid team kteam vteam);	
	my $set = { map {$_ => $hc->{$_}} @vars };
	if (uc $out eq 'DB') {
		$set->{datatype} = 'blob';
		$set->{datablob} = $data;
		warn "Saving heatmap for $hc->{mapname} directly to database\n";
	} else {
		my $file = $opt->file || file_format($hc->{format}, $hc);
		if (-d $file) {
			$file = catfile($file, file_format($hc->{format}, $hc));
		}
		warn "Saving heatmap for $hc->{mapname} to $file\n";
		if (open(OUT, ">$file")) {
			print OUT $data;
			close(OUT);
		} else {
			warn "Error opening file '$file' for output: $!";
			exit;
		}
		$set->{datatype} = 'file';
		$set->{datafile} = $file;
	}

	# delete any heatmap already matching the current criteria
	my $key = heatmap_key($hc);
	warn "$hc->{mapname} heatkey='$key'\n" unless $opt->quiet;
	$db->do(sprintf("DELETE FROM $db->{t_heatmaps} WHERE heatkey=%s AND statdate=%s AND hour%s", 
		$db->quote($key),
		$db->quote($set->{statdate}),
		defined $set->{hour} ? '='.$set->{hour} : ' IS NULL'
	));
	warn $db->lastcmd . "\n" if $opt->sql;

	# insert the new heatmap, since we're inserting binary data I have to roll my own insert here
	$set->{heatkey} = $key;
	my @keys = keys %$set;
	my $cmd = "INSERT INTO $db->{t_heatmaps} (" . join(',',@keys) . ") VALUES (". substr('?,' x @keys,0,-1) .")";
	my $st = $db->{dbh}->prepare($cmd);
#	warn $cmd . "\n" if $opt->sql and !$opt->quiet;

	if (!$st->execute(map($set->{$_}, @keys))) {
		warn "Error saving heatmap to database: " . $st->errstr . "\n";
	}
}

# generate a unique heatmap key based on the criteria given.
# the key must be easily reproducable. so PHP code can lookup heatmaps on this key too
# a SHA1 key is used and should be sufficient
sub heatmap_key {
	my ($hc) = @_;
	my $key = join('-', map { defined $_ ? $_ : 'NULL' } 
		# this order must be maintained! (its the same order as the DB fields, so its easy to remember)
		# note: statdate and hour are not included
		@$hc{qw(mapid weaponid who pid kid team kteam vid vteam headshot)}
	);
	$key = sha1_hex($key);
	return $key;
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
	$str =~ s/%h/defined $hc->{hour} ? $hc->{hour} : ''/ge;
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
