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
package PS::Map;

use strict;
use warnings;
use base qw( PS::Core );

use PS::SourceFilter;
use Time::Local;
use util qw( compacttime );

use overload
	'""' => 'name',		# this is primarily used in debugging code
	fallback => 1;


our $VERSION = '4.00.' . (('$Rev$' =~ /(\d+)/)[0] || '000');

# Global fields hash that determines what can be saved in the DB. Fields are
# created at COMPILE TIME.
our ($FIELDS, $HISTORY, $ALL, $ORDERED, $ORDERED_HISTORY);
BEGIN {
	$FIELDS = { };
	$FIELDS->{DATA} = { data => {
		(map { $_ => '+' } qw(
			kills		suicides
			games 		rounds
			connections
			online_time
		)),
	}};
	# include all data fields for history
	$HISTORY->{DATA} = { %{$FIELDS->{DATA}{data}} };
	#	(map { $_ => $FIELDS->{DATA}{data}{$_} } qw(
	#		kills		suicides
	#		games		rounds
	#		connections
	#		online_time
	#	))
	#};
}

# global helper objects for all PS::Map objects (use methods to access)
our ($DB, $CONF, $OPT);

# cached base classes that have been loaded already
our $CLASSES = {};
our $PREPARED = {};

# cached mapid's that have been loaded already
our $MAPIDS = {};

sub new {
	my $proto = shift;
	my $name = shift;				# map name
	my $gametype = shift;				# halflife
	my $modtype = shift || '';			# cstrike
	my $timestamp = shift || timegm(localtime);	# timestamp when map was used (game time)
	if (ref $name) {
		$gametype = $name->gametype;
		$modtype  = $name->modtype;
		$name = $name->name;
	}
	my $self = {
		gametype	=> $gametype,
		modtype		=> $modtype,
		type		=> $modtype ? $gametype . '_' . $modtype : $gametype,
		firstseen	=> $timestamp,	# when player was first seen
		timestamp	=> $timestamp,	# player timestamp

		mapid		=> 0,		# PRIMARY KEY
		name		=> '',		# map name

		data		=> {},		# core data stats
		#map		=> {},		# t_map information (only populated when there's a change)
		#history		=> {},		# historical stats, keyed on date
	};

	my $class;
	if (exists $CLASSES->{$self->{type}}) {
		# quickly return the subclass if it was loaded already
		$class = $CLASSES->{$self->{type}};
	} else {
		# Attempt to find a subclass of:
		# 	PS::Map::GAMETYPE::MODTYPE
		# 	PS::Map::GAMETYPE
		my $baseclass = ref($proto) || $proto;
		my @ary = $modtype ? ($modtype, $gametype) : ($gametype);
		while (@ary) {
			$class = join("::", $baseclass, reverse(@ary));
			eval "require $class";
			if ($@) {
				if ($@ !~ /^Can't locate/i) {
					__PACKAGE__->fatal("Compile error in class $class:\n$@\n");
				} 
				undef $class;
				shift @ary;
			} else {
				# class found, yeehaw!
				$CLASSES->{$self->{type}} = $class;
				$self->{$modtype} = $modtype;

				# prepare specialized statements for the game.
				# this is only going to happen once.
				if (!$PREPARED->{$self->{type}}) {
					__PACKAGE__->init_game_database($gametype, $modtype);
					__PACKAGE__->prepare_statements($gametype, $modtype);
				}
				
				last;
			}
		}

		# Still no class? Then use PS::Map instead.
		if (!$class) {
			$class = $baseclass;
			$CLASSES->{$self->{type}} = $class;
		}

		# If no suitable class is found then we fatal out
		if (!$class) {
			$self->fatal("No suitable " . __PACKAGE__ . " sub-class found. HALTING");
		}
	}
	$self->{class} = $class;

	bless($self, $class);
	return $self->init($name);
}

sub init {
	my ($self, $name) = @_;
	$self->{name} = $name;
	return $self;
}

# Save accumulated stats
sub save {
	my ($self) = @_;

	# don't save any stats if we don't have a timestamp.
	return unless $self->{timestamp};

	if (!$self->{mapid}) {
		# side effect; make sure a MAPID is assigned to this map.
		$self->id || return;
	}

	# calculate the total online time
	$self->{data}{online_time} = $self->onlinetime;

	$self->save_stats;

	# Use the last known timestamp for the statdate.
	my ($d, $m, $y) = (gmtime($self->{timestamp}))[3,4,5];
	my $statdate = sprintf('%04u-%02u-%02u', $y+1900, $m+1, $d);
	$self->save_history($statdate);

	%{$self->{data}} = ();

	# reset the timer start since we've calculated the online time above.
	$self->{timestart} = $self->{timestamp};
}

my $_cache = {};		# helps reduce the number of SELECT's we have to do
my $_cache_max = 8;		# max entries allowed in cache before its reset (power of 2)
keys(%$_cache) = $_cache_max;	# preset hash bucket size for efficiency
sub save_stats {
	my ($self) = @_;
	my $tbl = sprintf('map_data_%s', $self->{type});
	my ($cmd, $exists, @bind, @updates);

	# don't allow the cache to consume a ton of memory
	%$_cache = () if keys %$_cache >= $_cache_max;
	
	# find out if a compiled row exists already...
	$exists = $_cache->{$self->{mapid}}
		|| ($_cache->{$self->{mapid}} =
		    $self->db->execute_selectcol('find_c' . $tbl, $self->{mapid}));

	if ($exists) {
		# UPDATE an existing row
		@bind = ();
		@updates = ();
		foreach my $key (grep { exists $self->{data}{$_} } @{$ORDERED->{DATA}}) {
			push(@updates, $self->db->expr(
				$key,			# stat key (kills, deaths, etc)
				$ALL->{DATA}{$key},	# expression '+'
				$self->{data}{$key},	# actual value
				\@bind			# arrayref to store bind values
			));
		}
		if (@updates) {
			$cmd = sprintf('UPDATE %s SET %s WHERE mapid=?',
				$self->db->ctbl($tbl), join(',', @updates)
			);
			if (!$self->db->do($cmd, @bind, $self->{mapid})) {
				$self->warn("Error updating compiled MAP data for \"$self\": " . $self->db->errstr . "\nCMD=$cmd");
			}
		}
	} else {
		# INSERT a new row
		@bind = map { exists $self->{data}{$_} ? $self->{data}{$_} : 0 } @{$ORDERED->{DATA}};
		$self->db->execute('insert_c' . $tbl, $self->{mapid}, @bind);
		$_cache->{$self->{mapid}} = 1;
	}
}

# Save a set of historic stats.
# This does not check the 'maxdays' configuration.
sub save_history {
	my ($self, $statdate) = @_;
	my $tbl = sprintf('map_data_%s', $self->{type});	# ie: map_data_halflife_cstrike
	my ($cache_key, $exists, @bind);
	$cache_key = $self->{mapid} . '@' . $statdate;

	# don't allow the cache to consume a ton of memory
	%$_cache = (); # if keys %$_cache >= $_cache_max;

	# find out if a row exists already...
	$exists = $_cache->{$cache_key}
		|| ($_cache->{$cache_key} =
		    $self->db->execute_selectcol('find_map_data', $self->{mapid}, $statdate));

	if ($exists) {
		# update the tables, using a single query:
		# map_data, map_data_gametype_modtype
		my $cmd = sprintf('UPDATE %s%s t1, %smap_data t2 SET lastseen=?',
			$self->db->{dbtblprefix},
			$tbl,
			$self->db->{dbtblprefix}
		);
		
		@bind = ( $self->{timestamp} );
		foreach my $key (grep { exists $self->{data}{$_} } @{$ORDERED_HISTORY->{DATA}}) {
			$cmd .= ',' . $self->db->expr(
				$key,			# stat key (kills, deaths, etc)
				$ALL->{DATA}{$key},	# expression '+'
				$self->{data}{$key},	# actual value
				\@bind			# arrayref to store bind values
			);
		}
		$cmd .= ' WHERE t1.dataid=? AND t2.dataid=t1.dataid';
		push(@bind, $exists);
		
		if (!$self->db->do($cmd, @bind)) {
			$self->warn("Error updating map data for \"$self\": " . $self->db->errstr . "\nCMD=$cmd");
		}

	} else {
		if (!$self->db->execute('insert_map_data',
			$self->{mapid},
			$statdate,
			$self->{firstseen},
			$self->{timestamp}	# lastseen
		)) {
			# report error? 
			return;
		}
		$exists = $self->db->last_insert_id || return;
		$_cache->{$cache_key} = $exists;
		
		@bind = map { exists $self->{data}{$_} ? $self->{data}{$_} : 0 } @{$ORDERED_HISTORY->{DATA}};
		if (!$self->db->execute('insert_' . $tbl, $exists, @bind)) {
			$self->warn("Error inserting $tbl row for \"$self\": " . ($self->db->errstr || ''));
		}
	}
}

# get the unique mapid (read only). If the id is not set yet, the object will
# attempt to assign one and create a record in the DB.
sub id {
	my ($self) = @_;
	return $self->{mapid} if $self->{mapid};
	return $self->{mapid} = $MAPIDS->{$self} if $MAPIDS->{$self};
	my $id = $DB->execute_selectcol('find_mapid', @$self{qw( name gametype modtype )});
	if (!$id) {
		$DB->execute('insert_map', @$self{qw( name gametype modtype )});
		$id = $DB->last_insert_id || 0;
	}
	$self->{mapid} = $MAPIDS->{$self} = $id;
	return $self->{mapid};
}

# get/set the maps name.
sub name {
	my ($self, $new) = @_;
	if (defined $new) {
		$self->{name} = $new;
	}
	return $self->{name};
}

# get/set the last action timestamp
sub timestamp {
	my ($self, $new) = @_;
	if (defined $new) {
		$self->{timestamp} = $new;
		# set the firstseen and timer timestamps if needed
		$self->firstseen($new) unless $self->{firstseen};
		$self->timestart($new) unless $self->{timestart};
	}
	return $self->{timestamp};
}

# get/set the start timestamp (used for onlinetime tracking)
sub timestart {
	my ($self, $new) = @_;
	if (defined $new) {
		$self->{timestart} = $new;
	}
	return $self->{timestart};
}

# get/set the first seen timestamp
sub firstseen {
	my ($self, $new) = @_;
	if (defined $new) {
		$self->{firstseen} = $new;
	}
	return $self->{firstseen};
}

# return the total seconds the map has been online
sub onlinetime {
	my ($self) = @_;
	return 0 unless $self->{timestamp};
	$self->timestart($self->{timestamp}) unless $self->{timestart};
	return $self->{timestamp} - $self->{timestart};
}

# a player connected to the map while a game was active.
sub action_connected {
	my ($self, $game, $plr, $props) = @_;
	$self->timestamp($props->{timestamp});
	if (!$plr->is_bot or !$self->conf->ignore_bots_connect) {
		$self->{data}{connections}++;
		#$self->hourly('connections', $props->{timestamp});
	}
}

# a player joined a team on the map.
sub action_joined_team {
	my ($self, $game, $team, $props) = @_;
	$self->timestamp($props->{timestamp});
	
	$self->{data}{'joined_' . $team}++;
}

# a player killed someone else... duh.
sub action_kill {
	my ($self, $game, $killer, $victim, $weapon, $props) = @_;
	my $map = $self->name;
	my $kt = $killer->team;
	my $vt = $victim->team;
	$self->timestamp($props->{timestamp});

	$self->{data}{kills}++;

	return unless $vt && $kt;

	$self->{data}{'killed_' . $vt}++;
	$self->{data}{$kt . '_kills'}++;

	if ($vt eq $kt) {	# record a team kill
		$self->{data}{team_kills}++;
	}
}

# The map has officially started.
sub action_mapstarted {
	my ($self, $game, $props) = @_;
	#$self->timestamp($props->{timestamp});
	#warn "Map started '$self'\n\n";
	
	$self->{data}{games}++;
}

sub action_mapended {
	my ($self, $game, $props) = @_;
	#my $time = compacttime($self->onlinetime);
	#$self->timestamp($props->{timestamp});
	#warn "Map ended '$self' ($time)\n";
}

# A new round started/ended
sub action_round {
	my ($self, $game, $trigger, $props) = @_;
	
	if ($trigger eq 'round_start') {
		$self->{data}{rounds}++;
	
		# make sure a game is recorded. Logs that do not start with a
		# 'map started' event will end up having 1 less game recorded
		# than normal unless we fudge it here.
		if (!$self->{data}{games}) {
			$self->{data}{games}++;
		}
	}
}

# A player committed suicide
sub action_suicide {
	my ($self, $game, $victim, $weapon, $props) = @_;
	
	# we don't need to record deaths for maps, it'll always equal kills
	#$self->{data}{deaths}++;
	
	$self->{data}{suicides}++;
}

# generic team win event for any map game.
sub action_teamwon {
	my ($self, $game, $trigger, $team, $props) = @_;	

	# terrorist_wins, ct_wins, red_wins, blue_wins, etc...
	$self->{data}{'wins'}++;
	$self->{data}{$team . '_wins'}++;
}

# generic team lost event for any map game.
sub action_teamlost {
	my ($self, $game, $trigger, $team, $props) = @_;	
	
	# terrorist_losses, ct_losses, red_losses, blue_losses, etc...
	$self->{data}{'losses'}++;
	$self->{data}{$team . '_losses'}++;
}

sub gametype { $_[0]->{gametype} }
sub modtype  { $_[0]->{modtype} }

sub db () { $DB }
sub conf () { $CONF }
sub opt () { $OPT }

# Package method to return a $FIELDS hash for database columns
sub FIELDS {
	my ($self, $rootkey) = @_;
	my $group = ($self =~ /^PS::Map::(.+)/)[0];
	my $root = $FIELDS;
	$group =~ s/::/_/g;
	if ($rootkey) {
		$rootkey =~ s/::/_/g;
		$FIELDS->{$rootkey} ||= {}; # if !exists $FIELDS->{$rootkey};
		$root = $FIELDS->{$rootkey};

		# Make sure the sub-key for this root is created ahead of time.
		$root->{$group} ||= {};
		#$root->{$group}{data} ||= {};
	}
	return $root;
}

# Package method. 
# Prepares some SQL statements for use by all objects of this class to speed
# things up a bit. These statements require a valid PS::Plr object to be created
# already. This is only called once, per sub-class.
sub prepare_statements {
	my ($class, $gametype, $modtype) = @_;
	my $db = $PS::Map::DB || return;
	my $cpref = $db->{dbtblcompiledprefix};
	my $pref = $db->{dbtblprefix};
	my $type = $modtype ? $gametype . '_' . $modtype : $gametype;
	$PREPARED->{$type} = 1;

	# setup ordered columns for each set of fields, combining the 'data',
	# 'gametype' and 'modtype' fields into a single list.
	foreach my $F (sort keys %$FIELDS) {
		my %uniq;	# filter out unique columns
		$ORDERED->{$F} = [
			sort
			grep { !$uniq{$_}++ }
			map { keys %$_ }
			@{$FIELDS->{$F}}{ keys %{$FIELDS->{$F}} }
		];
		# keep the fields for historical values ordered separately
		$ORDERED_HISTORY->{$F} = [
			grep { exists $HISTORY->{$F}{$_} } @{$ORDERED->{$F}}
		];
	}

	# setup a list of all columns for each set of stats, so we don't have
	# to calculate this at each call to save().
	for my $F (qw( DATA )) {
		$ALL->{$F} = { map { %{$FIELDS->{$F}{$_}} } keys %{$FIELDS->{$F}} };
	}
	
	# finding a matching mapid based on the uniqueid
	$db->prepare('find_mapid', 'SELECT mapid FROM t_map WHERE name=? AND gametype=? AND modtype=?')
		if !$db->prepared('find_mapid');
	
	# insert a new map record based on the uniqueid.
	# mapid is auto_increment.
	$db->prepare('insert_map', 'INSERT INTO t_map (name, gametype, modtype) VALUES (?,?,?)')
		if !$db->prepared('insert_map');

	# returns the dataid of the t_map_data table that matches the mapid and
	# statdate.
	$db->prepare('find_map_data', 'SELECT dataid FROM t_map_data WHERE mapid=? AND statdate=?')
		if !$db->prepared('find_map_data');

	# returns mapid (true) if the compiled map ID already exists in the
	# compiled table.
	$db->prepare('find_cmap_data_' . $type, 'SELECT mapid FROM ' . $cpref . 'map_data_' . $type . ' WHERE mapid=?')
		if !$db->prepared('find_cmap_data_' . $type);

	# insert a new map_data row
	$db->prepare('insert_map_data',
		'INSERT INTO t_map_data (mapid,statdate,firstseen,lastseen) ' .
		'VALUES (?,?,?,?)'
	) if !$db->prepared('insert_map_data');

	if (!$db->prepared('insert_map_data_' . $type)) {
		$db->prepare('insert_map_data_' . $type, sprintf(
			'INSERT INTO ' . $pref . 'map_data_' . $type . ' (dataid,%s) ' .
			'VALUES (?%s)',
			join(',', @{$ORDERED_HISTORY->{DATA}}),
			',?' x @{$ORDERED_HISTORY->{DATA}}
		));
		#print $db->prepared('insert_map_data_' . $type)->{Statement}, "\n";

		# COMPILED STATS
		$db->prepare('insert_cmap_data_' . $type, sprintf(
			'INSERT INTO %smap_data_%s (mapid,%s) ' .
			'VALUES (?%s)',
			$cpref, $type,
			join(',', @{$ORDERED->{DATA}}),
			',?' x @{$ORDERED->{DATA}}
		));
		#print $db->prepared('insert_cmap_data_' . $type)->{Statement}, "\n";
	}

}

sub _init_table {
	my ($class, $tbl, $fields, $primary, $primary_order) = @_;
	my $created = 0;
	$primary ||= 'dataid';
	if (!ref $primary) {
		$primary = { $primary => 'uint' };
	}
	$primary_order ||= [ sort keys %$primary ];
	
	# FIRST, make sure the data table exists for the game_mod.
	if (!$DB->table_exists($tbl)) {
		$class->info("* Creating table $tbl.");
		# only add the primary key(s) to the table, the rest will be
		# added afterwards.
		if (!$DB->create($tbl, $primary, $primary_order)) {
			$class->fatal("Error creating table $tbl: " . $DB->errstr);
		}
		$DB->create_primary_index($tbl, $primary_order);
		$created = 1;
	}
	
	my (%add, @rem, %uniq);
	my $i = 0;
	
	# All columns that are actually in the table currently
	my ($actual_cols, $actual) = $DB->table_info($tbl);
	
	# All fields that SHOULD be configured
	my $configured = [ @$primary_order, sort grep { !$uniq{$_}++ } map { keys %$_ } @$fields{ keys %$fields } ];
	my %configured_cols = ( map { $_ => $i++ } @$configured );

	# short-circuit. If the table was created above, then there's no reason
	# to build the table 1 column at a time (Very slow)
	if ($created) {
		my @cols;
		foreach (grep { !exists $primary->{$_} } @$configured) {
			push(@cols, $DB->_type_int($_) . $DB->_attrib_null(0) . $DB->_default_int);
		}
		if (!$DB->alter_table_add($tbl, \@cols)) {
			$class->fatal("Error initializing columns in table $tbl: " . $DB->errstr);
		}
		return;
	}

	# remove any columns that are in the table but not configured
	foreach (@$actual) {
		if (!exists $configured_cols{$_}) {
			push(@rem, $_);
			delete $actual_cols->{$_};
		}
	}
	if (@rem) {
		$class->info("* Removing " . @rem . " unused columns from table $tbl (" . join(', ', @rem) . ")");
		if (!$DB->alter_table_drop($tbl, @rem)) {
			$class->fatal("Unable to DROP unused table columns from $tbl.");
		}
	}

	# add any columns that are configured but not in the actual table
	foreach (@$configured) {
		my ($after, $idx);
		if (!exists $actual_cols->{$_}) {
			# determine where the column should be added within
			# the sorted index list.
			$idx = $configured_cols{$_};
			if ($idx) {
				# technically, $idx should always be > 0
				$after = $configured->[ $idx-1 ];
			}
			$add{$_} = $after;
		}
	}
	if (%add) {
		$class->info("* Adding " . keys(%add) . " missing columns to table $tbl (" . join(', ', sort keys %add) . ")")
			if !$created;
		foreach (sort keys %add) {
			my $col = $DB->_type_int($_) . $DB->_attrib_null(0) . $DB->_default_int;
			if (!$DB->alter_table_add($tbl, $col, $add{$_})) {
				$class->fatal("Error adding new column ($_) to table $tbl: " . $DB->errstr);
			}
		}
	}
}

# Package method to make sure the database is properly setup for the game and
# mod specified. This is automatically called once when a new map type is
# instantiated.
# ::configure should have already been called with a valid $DB handle to use.
sub init_game_database {
	my ($class, $gametype, $modtype) = @_;
	my $type = $modtype ? $gametype . '_' . $modtype : $gametype;

	$class->_init_table($DB->tbl( 'map_data_' . $type), { data => $HISTORY->{DATA} });
	$class->_init_table($DB->ctbl('map_data_' . $type), $FIELDS->{DATA}, 'mapid');
}

# setup global helper objects (package method)
# PS::Plr::configure( ... )
sub configure {
	my %args = @_;
	foreach my $k (keys %args) {
		no strict 'refs';
		my $var = __PACKAGE__ . '::' . $k;
		$$var = $args{$k};
	}
}

1;
