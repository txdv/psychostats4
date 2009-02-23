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
our ($FIELDS, $ALL, $ORDERED);
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
		$self->id || return undef;
	}

	# calculate the total online time
	$self->{data}{online_time} = $self->onlinetime;

	# Use the last known timestamp for the statdate.
	my ($d, $m, $y) = (gmtime($self->{timestamp}))[3,4,5];
	my $statdate = sprintf('%04u-%02u-%02u', $y+1900, $m+1, $d);

	# NEXT: save stats
	$self->save_stats($statdate);

	%{$self->{data}} = ();

	# reset the timer start since we've calculated the online time above.
	$self->{timestart} = $self->{timestamp};
}

my $_cache = {};		# helps reduce the number of SELECT's we have to do
my $_cache_max = 8;		# max entries allowed in cache before its reset (power of 2)
keys(%$_cache) = $_cache_max;	# preset hash bucket size for efficiency
sub save_stats {
	my ($self, $statdate) = @_;
	my $tbl = sprintf('map_data_%s', $self->{type});
	my ($cmd, $history, $compiled, @bind, @updates);

	# don't allow the cache to consume a ton of memory
	%$_cache = () if keys %$_cache >= $_cache_max;
	
	# SAVE COMPILED STATS
	
	# find out if a compiled row exists already...
	$compiled = $_cache->{$self->{mapid}}
		|| ($_cache->{$self->{mapid}} =
		    $self->db->execute_selectcol('find_c' . $tbl, $self->{mapid}));

	if ($compiled) {
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

	# SAVE HISTORICAL STATS

	# get current dataid, if it exists.
	#$history = $_cache->{$self->{mapid} . '-' . $statdate}
	#	|| $self->db->execute_selectcol('find_plr_data', $self->{mapid}, $statdate);

}

my $_data_dataids = {};
sub save_data {
	my ($self, $statdate) = @_;
	my $fields = $FIELDS->{DATA};
	my $tbl = 'map_data_' . $self->{type};

	# check if the main data table already exists, if it does, we need its
	# dataid so the other tables can be related to it.
	my $dataid = $_data_dataids->{$self->{mapid} . '-' . $statdate}
		|| $self->db->execute_selectcol('find_map_data', $self->{mapid}, $statdate);

	if (!$dataid) {
		if (!$self->db->execute('insert_map_data',
			$self->{mapid},
			$statdate,
			$self->{firstseen},
			$self->{timestamp}	# lastseen
		)) {
			# If an error occurs it will be reported by the DB
			# object. This means we can't continue here, since we
			# won't have a valid dataid.
			return undef;
		}
		$dataid = $self->db->last_insert_id || return undef;

		# Insert a matching row for the game_mod data. We don't care
		# if this fails (technically, it never should fail).
		$self->db->execute('insert_' . $tbl,
			$dataid,
			# include all field keys
			map { exists $self->{data}{$_} ? $self->{data}{$_} : 0 } @{$ORDERED->{DATA}}
		);
	} else {
		# update the tables, using a single query:
		# map_data, map_data_gametype_modtype

		my $pref = $self->db->{dbtblprefix};
		my $cmd = sprintf('UPDATE %s%s t1, %smap_data t2 SET lastseen=?', $pref, $tbl, $pref);
		my @bind = ( $self->{timestamp} );
		foreach my $key (grep { exists $self->{data}{$_} } @{$ORDERED->{DATA}}) {
			my $expr = $ALL->{DATA}{$key};
			$cmd .= ",$key=";
			if ($expr eq '+' || $expr eq '=') {
				$cmd .= $key . $expr . '?';
				push(@bind, $self->{data}{$key});
			} elsif ($expr eq '>') {
				$cmd .= $self->db->expr_max($key, '?');
				# expr_max creates two '?' placeholders
				push(@bind, $self->{data}{$key}, $self->{data}{$key});
			} elsif ($expr eq '<') {
				$cmd .= $self->db->expr_min($key, '?');
				# expr_min creates two '?' placeholders
				push(@bind, $self->{data}{$key}, $self->{data}{$key});
			}
		}
		$cmd .= ' WHERE t1.dataid=? AND t2.dataid=t1.dataid';
		push(@bind, $dataid);
		
		if (!$self->db->do($cmd, @bind)) {
			$self->warn("Error updating MAP data for \"$self\": " . $self->db->errstr . "\nCMD=$cmd");
		}
	}
	$_data_dataids->{$self->{mapid} . '-' . $statdate} = $dataid;

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
	if (!$plr->is_bot or !$self->conf->main->ignore_bots_conn) {
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

# A new round started.
sub action_round {
	my ($self, $game, $props) = @_;
	
	$self->{data}{rounds}++;

	# make sure a game is recorded. Logs that do not start with a 'map
	# started' event will end up having 1 less game recorded than normal
	# unless we fudge it here.
	if (!$self->{data}{games}) {
		$self->{data}{games}++;
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
	$self->{data}{$team . '_wins'}++;
}

# generic team lost event for any map game.
sub action_teamlost {
	my ($self, $game, $trigger, $team, $props) = @_;	
	
	# terrorist_losses, ct_losses, red_losses, blue_losses, etc...
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
	my $db = $PS::Map::DB || return undef;
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
			join(',', @{$ORDERED->{DATA}}),
			',?' x @{$ORDERED->{DATA}}
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
	my ($class, $tbl, $fields, $primary, $order) = @_;
	my $created = 0;
	$primary ||= 'dataid';
	if (!ref $primary) {
		$primary = { $primary => 'uint' };
	}
	$order ||= [ sort keys %$primary ];
	
	# FIRST, make sure the data table exists for the game_mod.
	if (!$DB->table_exists($tbl)) {
		$class->info("* Creating table $tbl.");
		# only add the primary key(s) to the table, the rest will be
		# added afterwards.
		if (!$DB->create($tbl, $primary, $order)) {
			$class->fatal("Error creating table $tbl: " . $DB->errstr);
		}
		$DB->create_primary_index($tbl, $order);
		$created = 1;
	}
	
	# NEXT, Add any columns to the table that don't already exist. Note:
	# It's assumed that existing columns will be the correct type.
	my %uniq;	# filter out unique columns only
	my @cols = sort grep { !$uniq{$_}++ } map { keys %$_ } @$fields{ keys %$fields };
	my $info = $DB->table_info($tbl);
	my (@add, @missing);
	foreach my $col (@cols) {
		if (!exists $info->{$col}) {
			push(@missing, $col);
			push(@add, $DB->_type_int($col) . $DB->_attrib_null(0) . $DB->_default_int);
		}
	}
	if (@missing) {
		$class->info("* Adding " . scalar(@missing) . " missing columns to table $tbl (" . join(', ', @missing) . ")")
			if !$created;
		if (!$DB->alter_table_add($tbl, @add)) {
			$class->fatal("Error updating table $tbl with new columns (" . join(', ', @missing) . "): " . $DB->errstr);
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

	$class->_init_table($DB->tbl( 'map_data_' . $type), $FIELDS->{DATA});
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
