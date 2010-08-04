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
package PS::Feeder;

use strict;
use warnings;
use base qw( PS::Core );

use util qw( :numbers compacttime );
use serialize;
use Time::Local;
use Time::HiRes qw( time );	# needed for more accurate BPS/LPS reporting

use overload
	'""' => 'string',
	fallback => 1;

our $VERSION = '4.00.' . (('$Rev$' =~ /(\d+)/)[0] || '000');

# global helper objects for all objects
our ($DB, $CONF, $OPT, $CLASSES, $DATETIME);

BEGIN {
	# try to load DateTime, do not die if its not available.
	eval { require DateTime };
	$DATETIME = $@ ? 0 : 1;
	__PACKAGE__->warn(
		"DateTime module not available for log sources. " .
		"GMT offset will be calculated from local time only."
	) unless $DATETIME;
}

sub new {
	my $proto = shift;
	my $logsource = shift;

	if (ref $logsource eq 'HASH') {		# HASH
		return $proto->new_from_hash($logsource, @_);
	} elsif ($logsource =~ /^\d+$/) {	# ID
		return $proto->new_from_id($logsource, @_);
	} elsif (!ref $logsource) {		# STRING
		return $proto->new_from_string($logsource, @_);
	} else {				# ERROR
		# anything else is invalid, so return an error.
		return bless({ error => "Invalid $proto class instantiated" }, $proto);
	}
}

# Instaniates a PS::Feeder object via the logsource ID given, or returns an
# error object if the logsource ID doesn't exist.
sub new_from_id {
	my $proto = shift;
	my ($id, $db) = @_;
	my $baseclass = ref($proto) || $proto;
	my $class = $baseclass;
	my $self = {};
	$db ||= $DB;

	# must have an active DB handle
	if (!$db) {
		return bless({ error => 'No DB handle available to lookup logsource.' }, $proto);
	}

	my $logsource = $proto->load_logsource($id, $db);
	if (!$logsource) {
		return bless({ error => "Logsource ID $id does not exist." }, $proto);
	}


	# determine the proper class to load
	$class = $proto->_load_class(@$logsource{qw( type gametype modtype )});
	# If a reference was returned then an error occured loading the class.
	return $class if ref $class;

	$self->{logsource} = $logsource;
	$self->{class} = $class;
	bless($self, $class);
	return $self;
}

sub new_from_string {
	my $proto = shift;
	my $str = shift;
	my $db;
	$db = pop if ref $_[ scalar @_ - 1 ];		# db is last param, optionally
	my ($gametype, $modtype) = @_;			# only used for non-existing logsources
	my $baseclass = ref($proto) || $proto;
	my $class = $baseclass;
	my $type = 'file';
	my $logsource;
	my $self = {};
	$db ||= $DB;

	# must have an active DB handle
	#if (!$db) {
	#	return bless({ error => 'No DB handle available to lookup logsource.' }, $proto);
	#}

	# try to get logsource type from string, if available.
	my $src = $proto->parse($str);
	if (!$src) {
		return bless({ error => 'Logsource syntax error.' }, $proto);
	}

	# only do a lookup if we have a DB handle
	if ($db) {
		$logsource = $proto->load_logsource_from_hash($src, $db);
		#if (!$logsource or exists $logsource->{error}) {
		#	return bless({ error => $logsource ? $logsource->{error} : 'Logsource does not exist.' }, $proto);
		#}
	}

	if (!$logsource) {
		# gametype is required for new logsources
		if (!$gametype) {
			return bless({ error => 'A GAMETYPE & MODTYPE are required.' }, $proto);
		}

		# start a new logsource record based on what was parsed out.
		$logsource = { %$src };
		$logsource->{gametype} = $gametype;
		$logsource->{modtype}  = $modtype || '';	# optional
	}

	# determine the proper class to load
	$class = $proto->_load_class(@$logsource{qw( type gametype modtype )});
	# If a reference was returned then an error occured loading the class.
	return $class if ref $class;

	$self->{logsource} = $logsource;
	$self->{class} = $class;
	bless($self, $class);
	return $self;
}

# assume the $src hash has enough information for a logsource.
sub new_from_hash {
	my $proto = shift;
	my ($src, $db) = @_;
	my $baseclass = ref($proto) || $proto;
	my $class = $baseclass;
	my $logsource;
	my $self = {};
	$db ||= $DB;

	# only do a lookup if we have a DB handle
	if ($db) {
		$logsource = $proto->load_logsource_from_hash($src, $db);
		$logsource = $src unless $logsource;
	} else {
		$logsource = $src;
	}

	# a valid type must be present
	if (!$logsource->{type}) {
		return bless({ error => 'Unknown logsource type.' }, $proto);
	}

	# gametype is required for logsources
	if (!exists $logsource->{gametype}) {
		return bless({ error => 'A GAMETYPE is required.' }, $proto);
	}

	# determine the proper class to load
	$class = $proto->_load_class(@$logsource{qw( type gametype modtype )});
	# If a reference was returned then an error occured loading the class
	return $class if ref $class;

	$self->{logsource} = $logsource;
	$self->{class} = $class;
	bless($self, $class);
	return $self;
}

# Package function. Internal use only.
# Factory function that loads the proper sub-class for the feeder based on the
# gametype/modtype/type.
sub _load_class {
	my $proto = shift;
	my $type = shift;			# file, stream, ftp, etc...
	my @ary = grep { $_ } @_;		# gametype, modtype
	my $baseclass = ref($proto) || $proto;
	my $cached = join('::', $baseclass, @ary, $type);
	my @classes;
	my $class;

	return $CLASSES->{$cached} if exists $CLASSES->{$cached};

	# build a list of classes to try
	while (@ary) {
		push(@classes, join("::", $baseclass, @ary, $type));
		pop @ary;
	}
	push(@classes, join('::', $baseclass, $type));

	# try to load each class until we find one that works
	while (defined($class = shift @classes)) {
		eval "require $class";
		# if there's no error then we found our class
		last if !$@;

		# If an error occured and the file was found then we failed
		if ($@ !~ /^Can't locate/i) {
			return bless({ error => "Compile error in class $class:\n$@\n" }, $proto);
		}
	}

	# return the class found, or an error.
	if ($class) {
		# cache the loaded class for repeated use
		$CLASSES->{$cached} = $class;
		return $class;
	} else {
		return bless({ error => "Class PS::Feeder::$type not found." }, $proto);
	}
}

# init is called by the caller before any events are processed. This is no
# longer called by new() automatically.
sub init {
	my $self = shift;
	my %args = @_;

	# setup some generic parameters to change our behavior
	$self->{_verbose}  = $args{verbose}  || 0;
	$self->{_maxlogs}  = $args{maxlogs}  || 0;
	$self->{_maxlines} = $args{maxlines} || 0;
	$self->{_echo}     = $args{echo}     || 0;

	$self->{_curlog} = '';
	$self->{_curline} = 0;

	# tracking variables for BPS and LPS calculations
	$self->{_lasttimebytes} = time;
	$self->{_lasttimelines} = time;
	$self->{_lastprint} = time;
	$self->{_lastprint_threshold} = 5;
	$self->{_lastprint_bytes} = 0;
	$self->{_totallogs} = 0;
	$self->{_prevlines} = 0;
	$self->{_totallines} = 0;
	$self->{_totalbytes} = 0;
	$self->{_prevbytes} = 0;
	$self->{_offsetbytes} = 0;

	$self->{_iniized} = 1;

	return 1;
}

sub bytes_per_second {
	my ($self, $tail) = @_;
	return unless defined $self->{_lasttimebytes};
	my $time_diff = time - $self->{_lasttimebytes};
	my $byte_diff = $self->{_totalbytes} - $self->{_prevbytes};
	my $total = $time_diff ? sprintf("%.0f", $byte_diff / $time_diff) : $byte_diff;
	$self->{_prevbytes} = $self->{_totalbytes};
	$self->{_lasttimebytes} = time;
	return $tail ? abbrnum($total,0) . 'ps' : $total;
}

sub lines_per_second {
	my ($self, $tail) = @_;
	return unless defined $self->{_lasttimelines};
	my $time_diff = time - $self->{_lasttimelines};
	my $line_diff = $self->{_totallines} - $self->{_prevlines};
	my $total = $time_diff ? sprintf("%.0f", $line_diff / $time_diff) : $line_diff;
	$self->{_prevlines} = $self->{_totallines};
	$self->{_lasttimelines} = time;
	return $tail ? abbrnum($total,0) . 'ps' : $total;
}

sub percent_complete {
	my ($self, $fmt) = @_;
	$fmt ||= '%0.02f';
	if ($self->{_filesize}) {
		return sprintf($fmt, ($self->{_offsetbytes} + $self->{_lastprint_bytes}) / $self->{_filesize} * 100);
	}
	return;
}

sub echo_processing {
	my ($self, $include_pct) = @_;
	if ($self->{_filesize} and $include_pct) {
		my $bps = $self->bytes_per_second;
		my $eta = '';
		if ($bps) {
			#$eta = '; ' . compacttime(($self->{_filesize} - $self->{_lastprint_bytes}) / $bps);
			$eta = '; ' . sprintf('%d', ($self->{_filesize} - $self->{_lastprint_bytes}) / $bps) . ' secs remaining';
		}
		$self->verbose("Processing $self->{_curlog} (" . $self->lines_per_second . " lps / " .
			       $self->bytes_per_second(1) . ") [" . $self->percent_complete . "%$eta]"
		);
	} else {
		$self->verbose("Processing $self->{_curlog} (" . $self->lines_per_second . " lps / " .
			       $self->bytes_per_second(1) . ")"
		);
	}
}

# Capture the current state of the object.
sub capture_state {
	my ($self) = @_;
	my $state = {
		id		=> $self->id,			# logsource ID
		last_update	=> timegm(localtime),
		file		=> $self->curlog,
		line		=> $self->curline,
		pos		=> 0
	};
	return $state;
}

# save the state of the feeder. This allows certain feeders to start where
# they left off from a previous run.
sub save_state {
	my ($self, $db) = @_;
	my $state = $self->capture_state;
	my ($st, $set);
	$db ||= $self->db;

	# do not allow the feeder to change the game state.
	delete $state->{game_state};

	$set = join(', ', map { $_ . '=' . $db->quote($state->{$_}) } grep { $_ ne 'id' } keys %$state);

	$st = $db->prepare(
		'INSERT INTO t_state SET id=' . $db->quote($state->{id}) . ', ' .
		$set .
		' ON DUPLICATE KEY UPDATE ' .
		$set
	);
	#print $st->{Statement}, "\n";
	if (!$st->execute) {
		$self->{error} = $st->errstr;
		return 0;
	}
	return 1;
}

# Restores the logsource feeder to its previous state. Returns true if the
# state was restored successfully.
# sub-classes need to override to properly restore the state.
sub restore_state { 1 }

# reset/delete the current state for this feeder.
sub reset_state {
	my ($self, $db) = @_;
	my $state = $self->capture_state;
	return unless $state->{id};
	$db ||= $self->db;

	my $st = $db->prepare('DELETE FROM t_state WHERE id=?');
	$st->execute($state->{id});
	$st->finish;

	return;
}

# save the logsource of the current object and return the ID.
sub save {
	my ($self, $db) = @_;
	my $id = $self->save_logsource($self->{logsource}, $db);
	$self->id($id) if $id;
	return $id;
}

# save a logsource to the config. Will update or insert a record depending if
# the logsource has an ID already. Returns the 'id' of the logsource saved.
sub save_logsource {
	my ($self, $logsource, $db) = @_;
	my ($st, $exists);
	return unless ref $logsource eq 'HASH' and keys %$logsource;
	$db ||= $self->db || return;

	# NULL certain empty fields before we save the logsource
	$logsource->{$_} = undef for (
		grep { defined $logsource->{$_} and !$logsource->{$_} }
		qw( tz path host port username password depth options lastupdate )
	);

	# capture the state
	my $state = delete $logsource->{state};

	# if an ID is already assigned then we assume it exists in the DB.
	# This allows us to avoid using any DB calls prematurely, instead of
	# attempting to search for a matching logsource in the DB.
	$exists = exists $logsource->{id} ? $logsource->{id} : 0;
	if ($exists) {
		$db->update($db->{t_config_logsources},
			{ %$logsource, lastupdate => timegm(localtime) },
			[ 'id' => $exists ]
		);
	} else {
		$db->insert($db->{t_config_logsources}, $logsource);
		$exists = $db->last_insert_id;
	}
	return $exists;
}

# load logsource via its 'id'
sub load_logsource {
	my ($self, $id, $db) = @_;
	my ($st, $logsource);
	$db ||= $self->db || return;

	if (!$db->prepared('load_logsource')) {
		$db->prepare('load_logsource',"SELECT * FROM t_config_logsources WHERE id=?");
	}

	$st = $db->execute('load_logsource', $id);
	$logsource = $st->fetchrow_hashref;
	$st->finish;

	return $logsource;
}

# load logsource via a hash of information. All keys found in the hash are
# used to find the logsource in the database.
sub load_logsource_from_hash {
	my ($self, $src, $db) = @_;
	my ($st, $logsource, @bind);
	$db ||= $self->db || return;

	my $cmd = 'SELECT * FROM t_config_logsources WHERE';
	while (my($key,$val) = each(%$src)) {
		next if $key =~ /^(?:pass)$/;
		if (defined $val) {
			$cmd .= ' ' . $key . '=? AND';
			push(@bind, $val);
		} else {
			$cmd .= ' ' . $key . ' is NULL AND ';
		}
	}
	$cmd = substr($cmd, 0, -4);	# remove trailing ' AND'
	$st = $db->prepare($cmd);
	if (!$st->execute(@bind)) {
		$logsource->{error} = $st->errstr;
		return $logsource;
	}
	$logsource = $st->fetchrow_hashref;
	$st->finish;

	return $logsource;
}

sub defaultmap {
	my $self = shift;
	# Use the map from the previous state if it's available, otherwise
	# fallback to the defaultmap configured within the logsource. There's no
	# good way to determine between log files if a log was started due to a
	# server restart or just the mapcycle, some servers generate 2 or 3 logs
	# per map but only one log for each map cycled will contain the "started
	# map" event.
	if ($self->{state}{map}) {
		return $self->{state}{map};
	} else {
		return $self->{logsource}{defaultmap};
	}
}

# returns the GMT offset that this logsource is configured for; defaults to the
# local timezone. Will auto-compensate for DST if a proper timezone name is
# configured.
sub gmt_offset {
	my ($self) = @_;
	return $self->{dt}->offset if defined $self->{dt};	# DST adjusts automatically.
	return $self->{offset} if defined $self->{offset};	# no DST adjustments.
	if ($DATETIME) {
		my $tz = $self->{logsource}{tz} || 'local';
		eval {
			$self->{dt} = DateTime->now( time_zone => $tz );
			$self->{offset} = $self->{dt}->offset;
		};
		if ($@) {
			$self->warn("Invalid timezone '$tz' for log source ($@). Using local time instead.");
			goto INVALID_TZ;	# I hate 'goto' but owell...
		}
	} else {
		# determine our local timezone offset from GMT.
		# we 'int time' since we're using Time::HiRes
		INVALID_TZ:
		my $o = timegm(localtime) - int time;
		#my $h = $o / 3600;
		#my $m = $o % 3600 * 60;
		$self->{offset} = $o;
	}
	return $self->{offset};
}

# attempts to parse a string that represents the logsource into useful pieces.
# can be called as a class or object method. If the string is invalid undef is
# returned.
sub parse {
	my $self = shift;
	my $str = shift;
	my $logsource = {};

	if ($str =~ m|^(s?ftp)://(?:([^:@]+):{0,1}(.*)@)?([\w\-\.]+)(?::([0-9]+))?/?([^;\s]*)(;.*)?$|) {
		# type://[user[:pass]@]host[:port][/path][;option1=value;option2=value]
		my ($type,$user,$pass,$host,$port,$path,$opts) = ($1,$2,$3,$4,$5,$6,$7);
		$logsource->{type} = $type;
		$logsource->{username} = $user if defined $user;
		$logsource->{password} = $pass if defined $pass;
		$logsource->{host} = $host;
		$logsource->{port} = $port if defined $port;
		$logsource->{path} = $path if $path;
		if ($opts) {
			my @list = grep { $_ ne '' } split(/;/, $opts);
			if (@list) {
				$logsource->{options} = {};
				foreach my $piece (@list) {
					my ($key,$val) = map { s/^\s+//; s/\s+$//; $_ } split(/=/,$piece);
					$logsource->{options}{$key} = $val;
				}
			}
		}

	} elsif ($str =~ m|^(?:([^:]+)://)?(.+)|) {
		# [type://]absolute/path/
		my ($type, $path) = ($1, $2);
		$logsource->{type} = $type || 'file';
		$logsource->{path} = $path || '';	# don't allow undef

	} else {
		return;
	}

	return $logsource;
}

# Package function.
# returns a list of available logsources from the database.
sub get_logsources {
	my ($enabled, $db);
	my @list;
	$db ||= $DB;
	if ($db) {
		$enabled = 1 unless defined $enabled;
		$enabled = $enabled ? 1 : 0;
		@list = $db->get_list("SELECT id FROM $db->{t_config_logsources} WHERE enabled=$enabled ORDER BY idx");
	}
	return wantarray ? @list : \@list;
}

sub logsort {
	my $self = shift;
	my $list = shift;		# array ref to a list of log filenames
	#return [ sort { $self->logcompare($a, $b) } @$list ];
	return [ sort { $a cmp $b } @$list ];
}

sub logcompare {
	my ($self, $x, $y) = @_;
	return $x cmp $y;
}

# each sub-class will override to return a properly formated logsource string.
# self->parse( self->string ) should always work as expected.
sub string {
	my ($self) = @_;
	# this is not really a valid logsource string, subclasses must override
	# to provide proper functionality.
	return '[' . $self->{class} . ']';
}

sub db () { $DB }
sub conf () { $CONF }
sub opt () { $OPT }

# setup global helper objects (package method)
# PS::Feeder::configure( ... )
sub configure {
	my %args = @_;
	foreach my $k (keys %args) {
		no strict 'refs';
		my $var = __PACKAGE__ . '::' . $k;
		$$var = $args{$k};
	}
}

sub error { exists $_[0]->{error} ? $_[0]->{error} : undef }
sub total_logs { $_[0]->{_totallogs} }
sub total_lines { $_[0]->{_totallines} }
sub curlog { $_[0]->{_curlog} }
sub curline { $_[0]->{_curline} }
sub server { wantarray ? qw( 127.0.0.1 27015 ) : '127.0.0.1:27015' }
sub done { $_[0]->save }
sub has_event { undef }
sub next_event { undef }
sub normalize_line { $_[1] }
sub idle { undef }
sub logsource_exists { 0 }

BEGIN {
	# Create GETTERS/SETTERS for logsource attributes
	for my $get (qw( id type tz path host port passive username password
		     recursive depth skiplast skiplastline delete options
		     gametype modtype enabled idx lastupdate)) {
		no strict 'refs';
		no warnings;
		*$get = sub {
			if (@_ == 2) {
				$_[0]->{logsource}{$get} = $_[1];
				return $_[0];	# return self for chaining
			} else {
				return exists $_[0]->{logsource}{$get} ? $_[0]->{logsource}{$get} : '';
			}
		};
	}
}

1;
