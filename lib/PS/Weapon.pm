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
package PS::Weapon;

use strict;
use warnings;
use base qw( PS::Core );

use PS::SourceFilter;
use Time::Local;

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
			kills 		headshot_kills
			suicides
		)),
	}};
}

# global helper objects for all PS::Weapon objects (use methods to access)
our ($DB, $CONF, $OPT);

# cached base classes that have been loaded already
our $CLASSES = {};
our $PREPARED = {};

# cached weaponid's that have been loaded already
our $WEAPONIDS = {};

sub new {
	my $proto = shift;
	my $name = shift;				# weapon name 'm4a1', 'awp', etc...
	my $gametype = shift;				# halflife
	my $modtype = shift || '';			# cstrike
	my $timestamp = shift || timegm(localtime);	# timestamp when weapon was used (game time)
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

		weaponid	=> 0,		# PRIMARY KEY
		name		=> '',		# weapon name

		data		=> {},		# core data stats
		#weapon		=> {},		# t_weapon information (only populated when there's a change)
		#history		=> {},		# historical stats, keyed on date
	};

	my $class;
	if (exists $CLASSES->{$gametype . ':' . $modtype}) {
		# quickly return the subclass if it was loaded already
		$class = $CLASSES->{$gametype . ':' . $modtype};
	} else {
		# Attempt to find a subclass of:
		# 	PS::Weapon::GAMETYPE::MODTYPE
		# 	PS::Weapon::GAMETYPE
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
				$CLASSES->{$gametype . ':' . $modtype} = $class;
				$self->{$modtype} = $modtype;

				# prepare specialized statements for the game.
				# this is only going to happen once.
				if (!$PREPARED->{$gametype . ':' . $modtype}) {
					__PACKAGE__->init_game_database($gametype, $modtype);
					__PACKAGE__->prepare_statements($gametype, $modtype);
				}

				last;
			}
		}

		# Still no class? Then use PS::Weapon instead.
		# Currently, there isn't much need to have sub-classes of
		# PS::Weapon. This may change as new stats are added.
		if (!$class) {
			$class = $baseclass;
			$CLASSES->{$gametype . ':' . $modtype} = $class;
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

	if (!$self->{weaponid}) {
		# side effect; make sure a WEAPONID is assigned to this weapon.
		$self->id || return undef;
	}

	# Use the last known timestamp for the statdate.
	my ($d, $m, $y) = (gmtime($self->{timestamp}))[3,4,5];
	my $statdate = sprintf('%04u-%02u-%02u', $y+1900, $m+1, $d);

	# NEXT: save stats
	$self->save_stats($statdate);
	
	%{$self->{data}} = ();
}

my $_cache = {};
sub save_stats {
	my ($self, $statdate) = @_;
	my $tbl = sprintf('weapon_data_%s', $self->{type});
	my ($cmd, $history, $compiled, @bind, @updates);
	
	# SAVE COMPILED STATS
	
	# find out if a compiled row exists already...
	$compiled = $_cache->{'data-' . $self->{weaponid}}
		|| $self->db->execute_selectcol('find_c' . $tbl, $self->{weaponid});

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
			$cmd = sprintf('UPDATE %s SET %s WHERE weaponid=?',
				$self->db->ctbl($tbl), join(',', @updates)
			);
			if (!$self->db->do($cmd, @bind, $self->{weaponid})) {
				$self->warn("Error updating compiled WEAPON data for \"$self\": " . $self->db->errstr . "\nCMD=$cmd");
			}
		}
		
	} else {
		# INSERT a new row
		@bind = map { exists $self->{data}{$_} ? $self->{data}{$_} : 0 } @{$ORDERED->{DATA}};
		$self->db->execute('insert_c' . $tbl, $self->{weaponid}, @bind);
		$_cache->{$self->{weaponid}} = 1;
	}

	# SAVE HISTORICAL STATS

	# get current dataid, if it exists.
	#$history = $_cache->{$self->{weaponid} . '-' . $statdate}
	#	|| $self->db->execute_selectcol('find_plr_data', $self->{weaponid}, $statdate);

}

my $_data_dataids = {};
sub save_data {
	my ($self, $statdate) = @_;
	my $fields = $FIELDS->{DATA};
	my $tbl = 'weapon_data_' . $self->{type};

	# check if the main data table already exists, if it does, we need its
	# dataid so the other tables can be related to it.
	my $dataid = $_data_dataids->{$self->{weaponid} . '-' . $statdate}
		|| $self->db->execute_selectcol('find_weapon_data', $self->{weaponid}, $statdate);

	if (!$dataid) {
		if (!$self->db->execute('insert_weapon_data',
			$self->{weaponid},
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
		# weapon_data, weapon_data_gametype_modtype

		my $pref = $self->db->{dbtblprefix};
		my $cmd = sprintf('UPDATE %s%s t1, %sweapon_data t2 SET lastseen=?', $pref, $tbl, $pref);
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
			$self->warn("Error updating WEAPON data for \"$self\": " . $self->db->errstr . "\nCMD=$cmd");
		}
	}
	$_data_dataids->{$self->{weaponid} . '-' . $statdate} = $dataid;
	
}

# get the unique weaponid (read only). If the id is not set yet, the object will
# attempt to assign one and create a record in the DB.
sub id {
	my ($self) = @_;
	return $self->{weaponid} if $self->{weaponid};
	return $self->{weaponid} = $WEAPONIDS->{$self} if $WEAPONIDS->{$self};
	my $id = $DB->execute_selectcol('find_weaponid', @$self{qw( name gametype modtype )});
	if (!$id) {
		$DB->execute('insert_weapon', @$self{qw( name gametype modtype )});
		$id = $DB->last_insert_id || 0;
	}
	$self->{weaponid} = $WEAPONIDS->{$self} = $id;
	return $self->{weaponid};
}

# get/set the weapons name.
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
	}
	return $self->{timestamp};
}

# get/set the first seen timestamp
sub firstseen {
	my ($self, $new) = @_;
	if (defined $new) {
		$self->{firstseen} = $new;
	}
	return $self->{firstseen};
}

# a player attacked someone. Attacks are game specific and are overridden in
# subclasses usually.
sub action_attacked {
	my ($self, $killer, $victim, $map, $props) = @_;
	my $dmg = $props->{damage} || 0;
	$self->timestamp($props->{timestamp});

	$self->{data}{hits}++;
	$self->{data}{shots}++;
	$self->{data}{damage} += $dmg;

	# HL2 started recording the hitbox information on attacked events
	if ($props->{hitgroup}) {
		my $hit_loc = 'hit_' . $props->{hitgroup};
		my $dmg_loc = 'dmg_' . $props->{hitgroup};

		$self->{data}{$hit_loc}++;
		$self->{data}{$dmg_loc} += $dmg;
	}	
}

# a player killed someone
sub action_kill {
	my ($self, $killer, $victim, $map, $props) = @_;
	$self->timestamp($props->{timestamp});
	
	$self->{data}{kills}++;
}

# A player commited suicide with the weapon...
sub action_suicide {
	my ($self, $player, $map, $props) = @_;
	$self->timestamp($props->{timestamp});

	$self->{data}{suicides}++;
}

# allow remote callers to add arbitrary data stats.
sub data {
	my ($self, $data, $inc) = @_;
	$inc ||= 1;
	if (ref $data eq 'HASH') {
		my ($key, $val);
		while (($key, $val) = each %$data) {
			$self->{$key} += $val;
		}
	} elsif (ref $data eq 'ARRAY') {
		$self->{$_} += $inc for @$data;
	} else {
		$self->{data}{$data} += $inc;
	}
}

sub gametype { $_[0]->{gametype} }
sub modtype  { $_[0]->{modtype} }

sub db () { $DB }
sub conf () { $CONF }
sub opt () { $OPT }

# Package method to return a $FIELDS hash for database columns
sub FIELDS {
	my ($self, $rootkey) = @_;
	my $group = ($self =~ /^PS::Weapon::(.+)/)[0];
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
	my $db = $PS::Weapon::DB || return undef;
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
	
	# finding a matching weaponid based on the uniqueid
	$db->prepare('find_weaponid', 'SELECT weaponid FROM t_weapon WHERE name=? AND gametype=? AND modtype=?')
		if !$db->prepared('find_weaponid');
	
	# insert a new weapon record based on the uniqueid.
	# weaponid is auto_increment.
	$db->prepare('insert_weapon', 'INSERT INTO t_weapon (name, gametype, modtype) VALUES (?,?,?)')
		if !$db->prepared('insert_weapon');

	# returns the dataid of the t_weapon_data table that matches the weaponid and
	# statdate.
	$db->prepare('find_weapon_data', 'SELECT dataid FROM t_weapon_data WHERE weaponid=? AND statdate=?')
		if !$db->prepared('find_weapon_data');

	# returns plrid (true) if the compiled player ID already exists in the
	# compiled table.
	$db->prepare('find_cweapon_data_' . $type, 'SELECT weaponid FROM ' . $cpref . 'weapon_data_' . $type . ' WHERE weaponid=?')
		if !$db->prepared('find_cweapon_data_' . $type);

	# insert a new weapon_data row
	$db->prepare('insert_weapon_data',
		'INSERT INTO t_weapon_data (weaponid,statdate,firstseen,lastseen) ' .
		'VALUES (?,?,?,?)'
	) if !$db->prepared('insert_weapon_data');

	if (!$db->prepared('insert_weapon_data_' . $type)) {
		$db->prepare('insert_weapon_data_' . $type, sprintf(
			'INSERT INTO ' . $pref . 'weapon_data_' . $type . ' (dataid,%s) ' .
			'VALUES (?%s)',
			join(',', @{$ORDERED->{DATA}}),
			',?' x @{$ORDERED->{DATA}}
		));
		#print $db->prepared('insert_weapon_data_' . $type)->{Statement}, "\n";

		# COMPILED STATS
		$db->prepare('insert_cweapon_data_' . $type, sprintf(
			'INSERT INTO %sweapon_data_%s (weaponid,%s) ' .
			'VALUES (?%s)',
			$cpref, $type,
			join(',', @{$ORDERED->{DATA}}),
			',?' x @{$ORDERED->{DATA}}
		));
		#print $db->prepared('insert_cweapon_data_' . $type)->{Statement}, "\n";
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
# mod specified. This is automatically called once when a new weapon type is
# instantiated. ::configure should have already been called with a valid $DB
# handle to use.
sub init_game_database {
	my ($class, $gametype, $modtype) = @_;
	my $type = $modtype ? $gametype . '_' . $modtype : $gametype;

	$class->_init_table($DB->tbl( 'weapon_data_' . $type), $FIELDS->{DATA});
	$class->_init_table($DB->ctbl('weapon_data_' . $type), $FIELDS->{DATA}, 'weaponid');
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
