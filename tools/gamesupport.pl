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
#	Example of usage:
#	./gamesupport.pl -gametype halflife -modtype cstrike
#
#       Add's support for the specified gametype:modtype to all tables in the
#       database that need it.
#
BEGIN { # FindBin isn't going to work on systems that run the stats.pl as SETUID
	use strict;
	use warnings;

	use FindBin; 
	use lib ( $FindBin::Bin, $FindBin::Bin . "/lib", $FindBin::Bin . "/../lib" );
}

our $VERSION = '4.00.' . (('$Rev$' =~ /(\d+)/) || '000')[0];

use util qw( compacttime print_r abbrnum commify trim );
use File::Spec::Functions qw( catfile );
use Text::Balanced qw( extract_delimited extract_multiple );
use List::Util qw( first );
use PS::SourceFilter;
use PS::Core;
use PS::CmdLine;
use PS::Config;
use PS::DBI;

our $DEBUG;
our $DBCONF = {};				# Global database config

my ($opt, $dbconf, $db);

$opt = new PS::CmdLine;				# Initialize command line
$DEBUG = int($ENV{PSYCHOSTATS_DEBUG}) || 0;	# sets global debugging

if (!$opt->gametype and !$opt->modtype) {
	die "No Gametype or Modtype specified! Use -gametype and/or -modtype parameters to add support.\n";
}

$dbconf = {};
if (!$opt->noconfig) {
	if ($opt->config) {
		;;; PS::Core->debug("Loading DB config from " . $opt->config);
		$dbconf = PS::Config->LOAD_FILE( $opt->config );
	} elsif (-f catfile($FindBin::Bin, 'stats.cfg')) {
		;;; PS::Core->debug("Loading DB config from " . catfile($FindBin::Bin, 'stats.cfg'));
		$dbconf = PS::Config->LOAD_FILE( catfile($FindBin::Bin, 'stats.cfg') );
	} elsif (-f catfile($FindBin::Bin, '..', 'stats.cfg')) {
		;;; PS::Core->debug("Loading DB config from " . catfile($FindBin::Bin, '..', 'stats.cfg'));
		$dbconf = PS::Config->LOAD_FILE( catfile($FindBin::Bin, '..', 'stats.cfg') );
	} else {
		#die "No DB configuration found. Use -config to specify file.\n";
	}
}

# Initialize the primary Database object
# Allow command line options to override settings loaded from config
$DBCONF = {
	dbtype		=> $opt->dbtype || $dbconf->{dbtype} || 'mysql',
	dbhost		=> $opt->dbhost || $dbconf->{dbhost} || 'localhost',
	dbport		=> $opt->dbport || $dbconf->{dbport},
	dbname		=> $opt->dbname || $dbconf->{dbname} || 'psychostats',
	dbuser		=> $opt->dbuser || $dbconf->{dbuser},
	dbpass		=> $opt->dbpass || $dbconf->{dbpass},
	dbtblprefix	=> $opt->dbtblprefix || $dbconf->{dbtblprefix},
	dbcompress	=> $opt->dbcompress || $dbconf->{dbcompress},
	fatal		=> 0
};
$db = new PS::DBI($DBCONF);

my @tables =
	map { $db->tbl($_) }	# add prefix
	grep { !/config/ }	# ignore config tables
	@{$db->{tables}};

warn 'Enabling PsychoStats DB support for ' . $opt->gametype . '::' . $opt->modtype . ".\n";
my $total = 0;
foreach my $tbl (@tables) {
	my $t;
	$t += add_type($tbl, 'gametype', trim($opt->gametype));
	$t += add_type($tbl, 'modtype',  trim($opt->modtype));
	$total += $t if $t;
	$db->optimize($tbl) if $t;
}
if (!$total) {
	warn "No changes required.\n";
}

# returns the columns from the table that need to be updated
sub add_type {
	my ($tbl, $key, $type) = @_;
	my $info = $db->table_info($tbl) || next;
	my $total = 0;
	return unless $type and exists $info->{$key};

	if ($info->{$key}{type} =~ /enum\((.+)\)/i) {
		my $str = $1;	# extract_multiple will attempt to modify var
		my @list =
			map { substr($_,1,-1) } # remove surrounding quotes
			extract_multiple($str,
				[ sub { extract_delimited($_[0],q{'"}) }, qr/([^,]+)(.*)/ ],
				undef, 1
			);
		if (!first { $_ eq $type } @list) {
			my $enum = join(',', map { $db->quote($_, 1) } @list, $type);
			my $cmd = sprintf("ALTER TABLE %s CHANGE %s %s ENUM( %s ) NOT NULL",
				$db->qi($tbl), $db->qi($key), $db->qi($key), $enum
			);
			if ($db->do($cmd)) {
				$total++;
				warn "Converted $tbl.$key to ENUM($enum)\n";
			} else {
				warn $db->errstr . "\n";
			}
		}
	} else {	# most likely a varchar(X)
		# get a distinct list of current values for this column
		my @list = uniq($type, $db->get_list("SELECT DISTINCT $key FROM $tbl"));
		my $enum = join(',', map { $db->quote($_, 1) } @list);
		my $cmd = sprintf("ALTER TABLE %s CHANGE %s %s ENUM( %s ) NOT NULL",
			$tbl, $key, $key, $enum
		);
		if ($db->do($cmd)) {
			$total++;
			warn "Converted $tbl.$key to ENUM($enum)\n";
		} else {
			warn $db->errstr . "\n";
		}
	}
	
	return $total;
}

sub uniq {
	my %uniq;
	return grep { !$uniq{$_}++ } @_;
}
