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
package PS::Plr;

use strict;
use warnings;
use base qw( PS::Core );

use overload
	'""' => 'signature',	# this is primarily used in debugging code
	fallback => 1;

use util qw( deep_copy print_r int2ip );
use PS::SourceFilter;
use Time::Local;
use Encode qw( encode_utf8 decode_utf8 );

our $VERSION = '4.00.' . (('$Rev$' =~ /(\d+)/)[0] || '000');

use constant {
	IDS_TOTALUSES	=> 0,
	IDS_FIRSTSEEN	=> 1,
	IDS_LASTSEEN	=> 2
};

# Global fields hash that determines what can be saved in the DB. Fields are
# created at COMPILE TIME.
our ($FIELDS, $HISTORY, $ALL, $ORDERED, $ORDERED_HISTORY);
BEGIN {
	$FIELDS = {};
	$FIELDS->{DATA} = { data => {
		(map { $_ => '+' } qw(
			kills		headshot_kills
			deaths 		headshot_deaths 	suicides
			games 		rounds
			connections	online_time
			bonus_points
		)),
		kill_streak	=> '>',
		death_streak	=> '>',
	}};
	$HISTORY->{DATA} = {
		(map { $_ => $FIELDS->{DATA}{data}{$_} } qw(
			kills		headshot_kills
			deaths
			games		rounds
			connections	online_time
		))
	};
	
	#$CALC->{DATA} = {
	#	kills_per_death		=> [ ratio 		=> qw( kills deaths ) 		],
	#	kills_per_minute	=> [ ratio_minutes 	=> qw( kills online_time ) 	],
	#	headshot_kills_pct	=> [ percent 		=> qw( headshot_kills kills ) 	],
	#	team_kills_pct		=> [ percent 		=> qw( team_kills kills ) 	],
	#	team_deaths_pct		=> [ percent 		=> qw( team_deaths deaths ) 	],
	#	accuracy		=> [ percent 		=> qw( hits shots ) 		],
	#	shots_per_kill		=> [ ratio 		=> qw( shots kills ) 		],
	#};

	$FIELDS->{MAPS} = { data => {
		# maps store the same as basic stats (almost)
		%{$FIELDS->{DATA}{data}},
		(map { $_ => '+' } qw(
		)),
	}};
	# remove some extra stats that are not saved to maps
	delete @{$FIELDS->{MAPS}{data}}{ qw( bonus_points kill_streak death_streak ) };
	$HISTORY->{MAPS} = { %{$HISTORY->{DATA}} };

	$FIELDS->{ROLES} = { data => {
		(map { $_ => '+' } qw(
			kills 		headshot_kills
			deaths		headshot_deaths		suicides
		)),
	}};
	$HISTORY->{ROLES} = {
		(map { $_ => $FIELDS->{ROLES}{data}{$_} } qw(
			kills 		headshot_kills
			deaths
		))
	};

	$FIELDS->{SESSIONS} = { data => {
		(map { $_ => '+' } qw(
			kills		headshot_kills
			deaths		headshot_deaths		suicides
		)),
	}};

	$FIELDS->{VICTIMS} = { data => {
		(map { $_ => '+' } qw(
			kills 		headshot_kills
			deaths		headshot_deaths
		)),
	}};
	$HISTORY->{VICTIMS} = {
		(map { $_ => $FIELDS->{VICTIMS}{data}{$_} } qw(
			kills 		headshot_kills
			deaths
		))
	};

	$FIELDS->{WEAPONS} = { data => {
		(map { $_ => '+' } qw(
			kills 		headshot_kills
			deaths 		headshot_deaths		suicides
		)),
	}};
	$HISTORY->{WEAPONS} = {
		(map { $_ => $FIELDS->{WEAPONS}{data}{$_} } qw(
			kills 		headshot_kills
			deaths
		))
	};
	# each gametype and gametype::modtype combination will create
	# their own tree of fields under each root key shown above.
	#'gametype' => {},
	#'gametype::modtype' => {}
}

# global helper objects for all PS::Plr objects (use methods to access)
our ($DB, $CONF, $OPT);

# cached base classes that have been loaded already
our $CLASSES = {};
our $PREPARED = {};

sub new {
	my $proto = shift;
	my $signature = shift;				# hash with name, guid, ipaddr, etc...
	my $gametype = shift;				# halflife
	my $modtype = shift || '';			# cstrike (do not allow undef)
	my $timestamp = shift || timegm(localtime);	# timestamp when plr was seen (game time)
	my $self = {
		gametype	=> $gametype,
		modtype		=> $modtype,
		type		=> $modtype ? $gametype . '_' . $modtype : $gametype,
		firstseen	=> $timestamp,	# when player was first seen
		timestamp	=> $timestamp,	# player timestamp
		
		plrid		=> 0,		# PRIMARY KEY
		eventsig	=> '', 		# current eventsig for player
		guid		=> '',		# current GUID (steamid)
		name		=> '',		# current name
		ipaddr		=> 0,		# current IP address (32bit int)
		team		=> '',		# current team
		role		=> '',		# current role
		weapon		=> '',		# current weapon
		uid		=> 0,		# current UID

		skill		=> undef,	# current skill
		points		=> 0,		# total points accumulated
		dead		=> 1,		# DEAD flag
		
		#plr		=> {},		# t_plr information (only defined once loaded)
		ids		=> {},		# track plr_ids_* usage
		data		=> {},		# core data stats
		weapons		=> {},		# weapon stats
		maps		=> {}, 		# map stats
		roles		=> {}, 		# role stats
		victims		=> {}, 		# victim stats
		#history	=> {},		# historical stats, keyed on date

		#chat		=> [],		# track chat messages, if enabled in config
		#track		=> {}, 		# track in-memory information (not saved to DB)
	};
	
	my $class;
	if (exists $CLASSES->{$self->{type}}) {
		# quickly return the subclass if it was loaded already
		$class = $CLASSES->{$self->{type}};
	} else {
		# Attempt to find a subclass of:
		# 	PS::Plr::GAMETYPE::MODTYPE
		# 	PS::Plr::GAMETYPE
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

		# If no suitable class is found then we fatal out
		if (!$class) {
			$self->fatal("No suitable " . __PACKAGE__ . " sub-class found. HALTING");
		}
	}
	$self->{class} = $class;

	bless($self, $class);
	return $self->init($signature);
}

# Returns a hash that allows this player's stateful information to be saved.
# Stats are not included, unless the player doesn't have a 'plrid' yet.
# To unfreeze: $plr = PS::Plr->unfreeze($state);
sub freeze {
	my ($self) = @_;
	my $state = {
		map { $_ => $self->{$_} }
		qw(
			gametype modtype firstseen timestamp
			plrid eventsig guid name ipaddr team role uid
			skill points dead
		)
	};
	
	# If the player does not have a 'plrid' yet then we should freeze the
	# stats collected so far, in hopes that they can be saved next time.
	if (!$state->{plrid}) {
		# copy hashes
		$state->{$_} = deep_copy($self->{$_}) for
			grep { defined $self->{$_} and scalar keys %{$self->{$_}} }
			qw( ids data weapons maps roles victims track );

		# copy arrays
		$state->{$_} = deep_copy($self->{$_}) for
				grep { defined $self->{$_} and @{$self->{$_}} > 0 }
				qw( chat );
	}
	
	return $state;
}

# unfreezes a previously frozen plr hash into a new PS::Plr instance.
sub unfreeze {
	my ($class, $state) = @_;
	return unless defined $state;
	$class = ref $class if ref $class;
	return unless ref $state eq 'HASH';
	my $plr = $class->new($state, @$state{qw( gametype modtype timestamp )});
	
	$plr->{$_} = $state->{$_} for qw( plrid firstseen role skill points dead );

	$plr->{$_} = deep_copy($state->{$_}) for
		grep { defined $state->{$_} }
		qw( ids data weapons maps roles victims chat track );

	return $plr;
}

# returns a cloned object, w/o any stats, etc. Just the basics.
sub clone {
	my ($self) = @_;
	my $p;
	my $sig = {
		map { $_ => $self->{$_} }
		qw( eventsig name guid ipaddr team uid )
	};
	$p = new PS::Plr($sig, @$self{qw( gametype modtype timestamp )});
	$p->{$_} = $self->{$_} for qw( weapon role skill dead );

	return $p;
}

# initalize a basic player object using the signature provided.
sub init {
	my ($self, $signature) = @_;
	return $self unless $signature;

	# initialize the plr using the valid attributes in the signature
	for (qw( eventsig name guid ipaddr team uid )) {
		no strict 'refs';
		$self->$_($signature->{$_}) if exists $signature->{$_};
	}

	return $self;
}

# Initializes the basic t_plr information for the current player. But only if a
# valid plrid can be determined first. This is only called when certain stats
# need to be calculated (like during a kill; so the plr's current skill can be
# known and used in calculations).
sub init_plr {
	my ($self, $reset) = @_;
	return $self->{plr} if $self->{plr} and !$reset;
	if (!$self->{plrid}) {
		# determine our plrid, if possible. If we can't determine it
		# then we can't initialize the player yet.
		$self->assign_plrid or return;
	}
	$self->{plr} ||= {};
	my $plr = $self->db->execute_fetchrow('get_plr_basic', $self->{plrid});
	%{$self->{plr}} = %$plr if $plr;

	# assign the loaded skill to our public key for use
	$self->{skill} = ($plr and defined $plr->{skill})
		? $plr->{skill}
		: $self->conf->main->baseskill;

	$self->{rank} = $plr ? $plr->{rank} : undef;

	#$self->{points} = ($plr and $plr->{points})
	#	? $plr->{points} : 0;

	return $self->{plr};
}

# Save basic t_plr information.
sub save_plr {
	my ($self, $plr) = @_;
	return unless $self->id;
	$plr ||= $self->{plr};
	# TODO: ...
}

# Save accumulated stats and player information
sub save {
	my ($self, $game) = @_;
	my $uniqueid = $self->{ $self->conf->main->uniqueid };
	
	# First, we need a new PLRID if this player doesn't already have one.
	# If a PLRID can not be assigned then no data will be saved yet.
	if (!$self->{plrid}) {
		$self->assign_plrid || return;
	}

	# Make sure a profile exists for this player
	if (!$self->{has_profile} and !$self->db->execute_selectcol('plr_profile_exists', $uniqueid)) {
		if (!$self->db->execute('insert_plr_profile', $uniqueid, $self->{name})) {
			# not the end of the world if a profile didn't insert.
		} else {
			$self->{has_profile} = 1;
		}
	}
	
	# Save all plr_ids used by this player.
	foreach my $type (qw( guid ipaddr name )) {
		foreach my $id (keys %{$self->{ids}{$type}}) {
			$self->save_plr_id($type, $id, $self->{ids}{$type}{$id});
		}
	}
	%{$self->{ids}} = ();	# reset plr_ids in memory

	# Save player chat messages, if enabled.
	if (defined $self->{chat}) {
		$self->trim_plr_chat if $self->save_plr_chat($self->{chat});
	}

	# Update the player profile name [first, last, most] used as configured...
	if ($self->conf->main->plr_primary_name ne 'first') {
		my $pp = $self->db->execute_fetchrow('get_plr_profile_basic', $uniqueid);
		if ($pp and !$pp->{name_locked}) {
			my $name = decode_utf8($pp->{name});
			my $stname = sprintf('get_plr_%s_used_names', $self->conf->main->plr_primary_name);
			my ($newname) = $self->db->execute_selectall($stname, $self->{plrid}, 1);
			$newname = decode_utf8($newname);
			if (defined $newname and $name ne $newname) {
				#;;; warn "Changing PLRID $self->{plrid} name from \"$name\" TO \"$newname\"\n";
				$self->db->execute('update_plr_profile_name', $newname, $uniqueid);
			}
		}
	}

	# don't save any stats if the player doesn't have a timestamp.
	return unless $self->{timestamp};

	# Use the last known timestamp for the statdate. Regardless if the
	# player played 'yesterday' and it rolled passed midnight into 'today'.
	# Its just easier to maintain this way and for the most part this will
	# be sufficient.
	my ($d, $m, $y) = (gmtime($self->{timestamp}))[3,4,5];
	my $statdate = sprintf('%04u-%02u-%02u', $y+1900, $m+1, $d);

	# calculate the current online time for this player
	$self->{data}{online_time} = $self->onlinetime;

	# save session stats if a game object is passed in
	$self->save_session($game) if $game;

	# finally, save stats
	for (qw( data maps roles weapons victims )) {
		# save compiled stats
		$self->save_stats($_);
		
		# save historical stats, if configured
		$self->save_history($statdate, $_);
		
		# reset in-memory stats that were saved (keeping the same
		# reference is faster than assigning a new hash {})
		%{$self->{$_}} = ();
	}
	
	# reset the timer start since we've calculated the online time above.
	$self->{timestart} = $self->{timestamp};
	
	# reset points since they were saved in save_stats
	$self->{points} = 0;
}

# Quick save any stats that are pending.
# This will only save basic stats (kills, deaths, etc). This allows for a quick
# saving of basic stats to be seen in-game or online w/o waiting for the player
# to fully disconnect.
sub quick_save {
	my ($self, $game) = @_;
	# don't save if we don't have a timestamp
	return unless $self->{timestamp};

	# force a normal save if the player has no plrid.
	return $self->save unless $self->{plrid};
	
	# calculate the current online time for this player
	$self->{data}{online_time} = $self->onlinetime;

	# save session stats if a game object is passed in
	$self->save_session($game) if $game;

	$self->save_stats('data');

	my ($d, $m, $y) = (gmtime($self->{timestamp}))[3,4,5];
	my $statdate = sprintf('%04u-%02u-%02u', $y+1900, $m+1, $d);
	$self->save_history($statdate, 'data');
	
	# reset in-memory stats that were saved
	%{$self->{$_}} = () for qw( data );

	# reset the timer start since we've calculated the online time above.
	$self->{timestart} = $self->{timestamp};
}

# package level cache to help with DB efficiency when saving stats
my $_cache = {};		# helps reduce the number of SELECT's we have to do
my $_cache_max = 1024 * 2;	# max entries allowed in cache before its reset (power of 2)
keys(%$_cache) = $_cache_max;	# preset hash bucket size for efficiency

# Save a set of "compiled" player stats (data, maps, roles, weapons, victims)
sub save_stats {
	my ($self, $stats_key, $field_key) = @_;
	my $list = $self->{$stats_key} || return;
	my $tbl = sprintf('plr_%s_%s', $stats_key, $self->{type});	# ie: plr_data_halflife_cstrike
	my ($cmd, $exists, @bind, @updates);
	$field_key ||= uc $stats_key;

	# don't allow the cache to consume a ton of memory
	%$_cache = () if keys %$_cache >= $_cache_max;

	if ($stats_key eq 'data') {
		# find out if a compiled row exists already...
		$exists = $_cache->{$stats_key . '-' . $self->{plrid}}
			|| ($_cache->{$stats_key . '-' . $self->{plrid}} =
			    $self->db->execute_selectcol('find_c' . $tbl, $self->{plrid}));

		if ($exists) {
			# UPDATE an existing row
			@bind = ();
			@updates = ( );
			foreach my $key (grep { exists $self->{data}{$_} } @{$ORDERED->{DATA}}) {
				push(@updates, $self->db->expr(
					$key,			# stat key (kills, deaths, etc)
					$ALL->{DATA}{$key},	# expression '+'
					$self->{data}{$key},	# actual value
					\@bind			# arrayref to store bind values
				));
			}

			# Make sure the player has a default skill assigned
			$self->{skill} = $self->conf->main->baseskill unless defined $self->{skill};
			
			# update some columns in ps_plr
			push(@updates, $self->db->expr('lastseen', '>', $self->{timestamp}, \@bind));
			push(@updates, $self->db->expr('skill', '=', $self->{skill}, \@bind));
			push(@updates, $self->db->expr('points', '+', $self->{points}, \@bind));
			
			$cmd = sprintf('UPDATE %s d, %s p SET %s WHERE d.plrid=? AND p.plrid=d.plrid',
				$self->db->ctbl($tbl),
				$self->db->{t_plr},
				join(',', @updates)
			);
			push(@bind, $self->{plrid});
			if (!$self->db->do($cmd, @bind)) {
				$self->warn("Error updating compiled PLR data for \"$self\": " . $self->db->errstr . "\nCMD=$cmd");
			}
			#;;;warn "CMD:  ", $self->db->lastcmd(\@bind), "\n";
			
		} else {
			# INSERT a new row
			@bind = map { exists $self->{data}{$_} ? $self->{data}{$_} : 0 } @{$ORDERED->{DATA}};
			$self->db->execute('insert_c' . $tbl, $self->{plrid}, @bind);
			$_cache->{$stats_key . '-' . $self->{plrid}} = 1;
			#;;;warn "CMD:  ", $self->db->lastcmd(\@bind), "\n";

			# Make sure the player has a default skill assigned
			$self->{skill} = $self->conf->main->baseskill unless defined $self->{skill};

			@bind = ();
			# update some columns in ps_plr
			push(@updates, $self->db->expr('lastseen', '>', $self->{timestamp}, \@bind));
			push(@updates, $self->db->expr('skill', '=', $self->{skill}, \@bind));
			push(@updates, $self->db->expr('points', '+', $self->{points}, \@bind));

			push(@bind, $self->{plrid});
			$self->db->do(sprintf('UPDATE %s SET %s WHERE plrid=?',
				$self->db->{t_plr},
				join(',', @updates)
			), @bind);
			#;;;warn "CMD:  ", $self->db->lastcmd(\@bind), "\n";
		}
	} else {
		# loop through each key in the hash and insert/update each row
		# as needed for this player.
		my $cacheid;
		foreach my $id (keys %$list) {
			# ignore any ID's that are ZERO (mainly for victims)
			next unless $id;
			$cacheid = $stats_key . '-' . $self->{plrid} . '-' . $id;
			
			# find out if a compiled row exists already...
			$exists = $_cache->{$cacheid}
				|| ($_cache->{$cacheid} =
				    $self->db->execute_selectcol('find_c' . $tbl, $self->{plrid}, $id));

			if ($exists) {
				# UPDATE the existing row
				@bind = ();
				@updates = ();
				foreach my $key (grep { exists $list->{$id}{$_} } @{$ORDERED->{$field_key}}) {
					push(@updates, $self->db->expr(
						$key,				# stat key (kills, deaths, etc)
						$ALL->{$field_key}{$key},	# expression '+'
						$list->{$id}{$key},		# actual value
						\@bind				# arrayref to store bind values
					));
				}
				if (@updates) {
					$cmd = sprintf('UPDATE %s SET %s WHERE plrid=? AND %s=?',
						$self->db->ctbl($tbl),
						join(',', @updates),
						substr($stats_key,0,-1) . 'id'
					);

					if (!$self->db->do($cmd, @bind, $self->{plrid}, $id)) {
						$self->warn("Error updating compiled PLR $stats_key for \"$self\": " . $self->db->errstr . "\nCMD=$cmd");
					}
				}
			} else {
				# INSERT a new row
				@bind = map { exists $list->{$id}{$_} ? $list->{$id}{$_} : 0 } @{$ORDERED->{$field_key}};
				$self->db->execute('insert_c' . $tbl, $self->{plrid}, $id, @bind);
				$_cache->{$cacheid} = 1;
			}
		}
	}
}

# Save a set of historic stats (data, maps, roles, weapons, victims).
# This does not check the 'maxdays' configuration.
sub save_history {
	my ($self, $statdate, $stats_key, $field_key) = @_;
	my $list = $self->{$stats_key} || return;
	my $tbl = sprintf('plr_%s_%s', $stats_key, $self->{type});	# ie: plr_data_halflife_cstrike
	my ($cmd, $cache_key, $exists, @bind, @updates);
	$cache_key = $stats_key . '-' . $self->{plrid} . '@' . $statdate;
	$field_key ||= uc $stats_key;

	# don't allow the cache to consume a ton of memory
	%$_cache = () if keys %$_cache >= $_cache_max;

	if ($stats_key eq 'data') {
		# find out if a row exists already...
		$exists = $_cache->{$cache_key}
			|| ($_cache->{$cache_key} =
			    $self->db->execute_selectcol('find_plr_data', $self->{plrid}, $statdate));

		if ($exists) {
			# update the tables, using a single query:
			# plr_data, plr_data_gametype_modtype
			my $cmd = sprintf('UPDATE %s%s t1, %splr_data t2 SET lastseen=?,rank=?,skill=?',
				$self->db->{dbtblprefix},
				$tbl,
				$self->db->{dbtblprefix}
			);
			@bind = (
				$self->{timestamp},
				$self->{rank} || undef,
				$self->{skill} || $self->conf->main->baseskill
			);
			
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
				$self->warn("Error updating PLR data for \"$self\": " . $self->db->errstr . "\nCMD=$cmd");
			}
			#;;;warn "CMD:  ", $self->db->lastcmd(\@bind), "\n";

		} else {
			if (!$self->db->execute('insert_plr_data',
				$self->{plrid},
				$statdate,
				$self->{firstseen},
				$self->{timestamp},	# lastseen
				$self->{rank} || undef,
				defined $self->{skill} ? $self->{skill} : $self->conf->main->baseskill,
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
			#;;;warn "CMD:  ", $self->db->lastcmd(\@bind), "\n";
		}
	} else {
		# loop through each key in the hash and insert/update each row
		# as needed for this player.
		my $_key = $stats_key;
		foreach my $id (keys %$list) {
			# Ignore any ID's that are ZERO (mainly for victims)
			next unless $id;
			$_key = $cache_key . '@' . $id;
			
			# find out if a row exists already...
			$exists = $_cache->{$_key}
				|| ($_cache->{$_key} =
				    $self->db->execute_selectcol('find_plr_' . $stats_key,
								 $self->{plrid}, $id, $statdate));

			if ($exists) {
				# update the tables, using a single query:
				# plr_blah, plr_blah_gametype_modtype
				my $cmd = sprintf('UPDATE %s%s t1, %splr_%s t2 SET lastseen=?',
					$self->db->{dbtblprefix},
					$tbl,
					$self->db->{dbtblprefix},
					$stats_key
				);
				
				@bind = ( $self->{timestamp} );
				foreach my $key (grep { exists $list->{$id}{$_} } @{$ORDERED_HISTORY->{$field_key}}) {
					$cmd .= ',' . $self->db->expr(
						$key,				# stat key (kills, deaths, etc)
						$ALL->{$field_key}{$key},	# expression '+'
						$list->{$id}{$key},		# actual value
						\@bind				# arrayref to store bind values
					);
				}
				$cmd .= ' WHERE t1.dataid=? AND t2.dataid=t1.dataid';
				push(@bind, $exists);
				
				if (!$self->db->do($cmd, @bind)) {
					$self->warn("Error updating PLR $stats_key for \"$self\": " . $self->db->errstr . "\nCMD=$cmd");
				}
				#$cmd =~ s/\?/$_/ for @bind; print "$cmd\n";

			} else {
				if (!$self->db->execute('insert_plr_' . $stats_key,
					$self->{plrid},
					$id, 
					$statdate,
					$self->{firstseen},
					$self->{timestamp}	# lastseen
				)) {
					# report error? 
					return;
				}
				$exists = $self->db->last_insert_id || return;
				$_cache->{$_key} = $exists;
				
				@bind = map { exists $list->{$id}{$_} ? $list->{$id}{$_} : 0 } @{$ORDERED_HISTORY->{$field_key}};
				if (!$self->db->execute('insert_' . $tbl, $exists, @bind)) {
					$self->warn("Error inserting $tbl row for \"$self\": " . ($self->db->errstr || ''));
				}
			}
		}
	}
}

# Save the current stats for the player as part of a game session.
# Automatically adds the current stats to a previous session if the time
# difference is not that far apart.
sub save_session {
	my ($self, $game, $threshold) = @_;
	my $plrid = $self->id || return;
	my $m = $game->get_map->id;

	return unless $self->conf->main->plr_sessions_max > 0 && $self->{timestamp};
	return unless $self->onlinetime;

	$threshold = $self->conf->main->plr_sessions_time unless defined $threshold;
	$threshold ||= 60*15;	# default to 15 minutes if nothing is configured

	# get most recent session
	my ($dataid, $start, $end) = $self->db->execute_selectall('find_plr_session', $plrid, $m);
	if ($dataid and ($self->{timestamp} - $end > $threshold)) {
		$dataid = 0;
	}

	if (!$dataid) {
		# get skill from previous session
		my $prevskill = $self->db->execute_selectcol('get_plr_session_prev_skill',
							     $self->{plrid});
		
		# insert a new session
		$self->db->execute('insert_plr_sessions',
			$self->{plrid},
			$m,
			$self->{timestart},
			$self->{timestamp},
			$self->skill,
			defined $prevskill ? $prevskill : $self->{skill}
		);
		$dataid = $self->db->last_insert_id;
		
		# insert game specific session data using the new datid
		$self->db->execute('insert_plr_sessions_' . $self->{type},
			$dataid,
			map { exists $self->{data}{$_} ? $self->{data}{$_} : 0 }
				@{$ORDERED->{SESSIONS}}
		);
	} else {
		# update the session stats
		my (@bind, @updates);

		foreach my $key (grep { exists $self->{data}{$_} } @{$ORDERED->{SESSIONS}}) {
			push(@updates, $self->db->expr(
				$key,			# stat key (kills, deaths, etc)
				$ALL->{SESSIONS}{$key},	# expression '+'
				$self->{data}{$key},	# actual value
				\@bind			# arrayref to store bind values
			));
		}

		# update some columns in ps_plr_sessions
		push(@updates, $self->db->expr('session_end', '>', $self->{timestamp}, \@bind));
		push(@updates, $self->db->expr('skill', '=', $self->{skill}, \@bind));
		
		my $tbl = sprintf('plr_sessions_%s', $self->{type});
		my $cmd = sprintf('UPDATE %s d, %s s SET %s WHERE d.dataid=? AND s.dataid=d.dataid',
			$self->db->tbl($tbl),
			$self->db->{t_plr_sessions},
			join(',', @updates)
		);
		push(@bind, $dataid);
		if (!$self->db->do($cmd, @bind)) {
			$self->warn("Error updating PLR session for \"$self\": " .
				    $self->db->errstr . "\nCMD=$cmd");
		}
	}

}

# Save "plr_chat" messages. 1 or more messages can be saved at the same time.
# Returns the total messages saved.
sub save_plr_chat {
	my ($self, $messages) = @_;
	my ($st, @bind);
	return unless $self->{plrid} and defined $messages and @$messages;
	# Build an optimized insert statement that inserts 1..N messages with a
	# single query. The statement is cached so its only prepared once.
	if (!defined($st = $self->db->prepared('insert_plr_chat_' . @$messages))) {
		my $cmd = 'INSERT INTO t_plr_chat VALUES ';
		$cmd .= '(?,?,?,?,?,?),' x @$messages;
		$cmd = substr($cmd,0,-1);	# remove trailing comma
		$st = $self->db->prepare('insert_plr_chat_' . @$messages, $cmd);
	}
	
	foreach my $m (@$messages) {
		push(@bind, $self->{plrid}, map { $m->{$_} } qw( timestamp message team team_only dead ));
	}
	return 0 unless $st->execute(@bind);
	$st->finish;
	return scalar @$messages;
}

# trim the player chat messages to the configured limit.
sub trim_plr_chat {
	my ($self) = @_;
	my ($st, $total);
	my $max = $self->conf->main->plr_chat_max || return;
	
	if (!$self->db->prepared('total_plr_chat')) {
		$self->db->prepare('total_plr_chat', 'SELECT COUNT(*) FROM t_plr_chat WHERE plrid=?');
	}
	$total = $self->db->execute_selectcol('total_plr_chat', $self->{plrid});
	return unless $total > $max;

	if (!defined($st = $self->db->prepared('trim_plr_chat'))) {
		# MYSQL specific
		$st = $self->db->prepare('trim_plr_chat',
			'DELETE FROM t_plr_chat WHERE plrid=? ORDER BY timestamp LIMIT ?'
		);
	}
	$st->execute($self->{plrid}, $total - $max);
	$st->finish;
	return $st;
}

# save "plr_plr_ids" usage.
# $type = 'guid', 'ipaddr', 'name'
# $id   = type 'value'
# $set  = arrayref of values to insert/update
sub save_plr_id {
	my ($self, $type, $id, $set) = @_;
	# see the prepared statement at the bottom of the class to see what the
	# @bind vars below mean.
	if (!$self->db->execute('update_plr_ids_' . $type, $self->{plrid}, $id, @$set, @$set[0,2])) {
		# we don't care if this fails. DB will spit out a warning.		
	}
}

# Assign a new PLRID to this player and return the new PLRID. If the player
# doesn't have a valid signature then 0 is returned.
# Note: halflife subclass overrides this to prevent uniqueid's of
# STEAM_ID_PENDING or STEAM_ID_LAN from being used.
sub assign_plrid {
	my ($self, $check_only) = @_;
	return $self->{plrid} if $self->{plrid};
	my $uniqueid = $self->{ $self->conf->main->uniqueid };
	
	$self->{plrid} = $self->db->execute_selectcol('find_plrid', $uniqueid,
						      @$self{qw(gametype modtype)}) || 0;
	return $self->{plrid} if $self->{plrid};
	
	# create a new PLR record for this player since they don't exist yet.
	unless ($check_only) {
		if ($self->db->execute('insert_plr', $uniqueid,
			@$self{qw(gametype modtype firstseen timestamp)},
			$self->conf->main->baseskill)) {
			$self->{plrid} = $self->db->last_insert_id;
		} else {
			$self->warn("Error inserting new player record for $self");
		}
	}
	
	return $self->{plrid};
}

# return a fully qualified signature string for the player in a format that
# represents how the player would be seen in a game event. This method is
# overloaded for stringification. So its called automatically if a PS::Plr
# reference is used as a scalar in a string.
# It's important for sub classes to override this. This is not always the
# same as ->eventsig.
sub signature {
	my ($self) = @_;
	return $self->{class};
}

# returns true if the player is a BOT
sub is_bot {
	my ($self) = @_;
	return substr($self->guid, 0, 4) eq 'BOT_';
}

# returns true if the player is currently dead. Note: All new players are
# assumed to be dead when they are instantiated.
sub is_dead {
	my ($self, $new) = @_;
	if (defined $new) {
		$self->{dead} = $new;
	}
	return $self->{dead};
}

# get the unique player id (plrid). If the plrid is not set yet, the object
# will attempt to assign one and create a player record in the DB.
sub id {
	my ($self) = @_;
	return $self->{plrid} || $self->assign_plrid || 0;
}

# get/set the player's clanid. This will actually update the DB for every SET
sub clanid {
	my ($self, $new) = @_;
	$self->init_plr || return 0 unless defined $self->{plr};
	if (@_ >= 2) {
		$self->{plr}{clanid} = $new;
		$self->db->execute('update_plr_clanid', $new, $self->id);
	}
	return $self->{plr}{clanid};
}

# get/set the player's active guid (steamid)
# setting a new guid will increase the usage counter for it.
sub guid {
	my ($self, $new, $timestamp) = @_;
	if (defined $new) {
		$self->{guid} = $new;
		$self->{ids}{guid}{$new}[IDS_TOTALUSES]++;
		$self->{ids}{guid}{$new}[IDS_LASTSEEN] = $timestamp || $self->{timestamp} || timegm(localtime);
		if (!$self->{ids}{guid}{$new}[IDS_FIRSTSEEN]) {
			$self->{ids}{guid}{$new}[IDS_FIRSTSEEN] = $timestamp || $self->{timestamp} || timegm(localtime);
		}
	}
	return $self->{guid};
}

# get/set the player's active name.
# setting a new name will increase the usage counter for it.
sub name {
	my ($self, $new, $timestamp) = @_;
	if (defined $new) {
		$self->{name} = $new;
		$self->{ids}{name}{$new}[IDS_TOTALUSES]++;
		$self->{ids}{name}{$new}[IDS_LASTSEEN] = $timestamp || $self->{timestamp} || timegm(localtime);
		if (!$self->{ids}{name}{$new}[IDS_FIRSTSEEN]) {
			$self->{ids}{name}{$new}[IDS_FIRSTSEEN] = $timestamp || $self->{timestamp} || timegm(localtime);
		}
	}
	return $self->{name};
}

# get/set the player's active ipaddr.
# setting a new ipaddr will increase the usage counter for it.
sub ipaddr {
	my ($self, $new, $timestamp) = @_;
	if (defined $new) {
		$self->{ipaddr} = $new;
		if ($new ne '0') {
			# we don't record ip addresses that are 0
			$self->{ids}{ipaddr}{$new}[IDS_TOTALUSES]++;
			$self->{ids}{ipaddr}{$new}[IDS_LASTSEEN] = $timestamp || $self->{timestamp} || timegm(localtime);
			if (!$self->{ids}{ipaddr}{$new}[IDS_FIRSTSEEN]) {
				$self->{ids}{ipaddr}{$new}[IDS_FIRSTSEEN] = $timestamp || $self->{timestamp} || timegm(localtime);
			}
		}
	}
	return $self->{ipaddr};
}

# increments the current signature ID's of the player to represent usage.
sub used_ids {
	my ($self, @ids) = @_;
	@ids = qw( name guid ipaddr ) unless @ids;
	foreach (@ids) {
		$self->{ids}{$_}{ $self->{$_} }[IDS_TOTALUSES]++ if $self->{$_};
	}
}

# reset the in-memory player {ids} 
sub reset_ids {
	my ($self, @ids) = @_;
	@ids = qw( guid name ipaddr ) unless @ids;
	%{$self->{ids}{$_}} = () for @ids;
}

# get/set the player's active event signature. This is how the player was last
# seen from an event). This is not always the same as ->signature, and is
# useful for caching a player based on the event signature from the logs.
sub eventsig {
	my ($self, $new) = @_;
	if (defined $new and $self->{eventsig} ne $new) {
		$self->{eventsig} = $new;
	}
	return $self->{eventsig};
}

# get/set the player's active team.
sub team {
	my ($self, $new) = @_;
	if (defined $new) {
		$new = '' if $new eq 'unassigned';
		if ($self->{team} ne $new) {
			$self->{team} = $new;
		}
	}
	return $self->{team};
}

# get/set the player's active uid.
sub uid {
	my ($self, $new) = @_;
	if (defined $new and $self->{uid} ne $new) {
		$self->{uid} = $new;
	}
	return $self->{uid};
}

# get/set the player's active weapon.
sub weapon {
	my ($self, $new) = @_;
	if (defined $new and $self->{weapon} ne $new) {
		$self->{weapon} = $new;
	}
	return $self->{weapon};
}

# get/set the player's active role.
sub role {
	my ($self, $new) = @_;
	if (defined $new and $self->{role} ne $new) {
		$self->{role} = $new;
	}
	return $self->{role};
}

# get/set the player's skill value.
# If $inc is true the new value is added to the current, else $new is absolute
sub skill {
	my ($self, $new, $inc) = @_;
	if (defined $new and (($self->{skill} || '' ne $new) || $inc)) {
		$self->{skill} = $inc ? $self->{skill} + $new : $new;
	}
	return $self->{skill};
}

# get/set the player's points value
# if $abs is true then $new is an absolute value, else its added to the curent.
sub points {
	my ($self, $new, $abs) = @_;
	#$self->init_plr || return 0 unless defined $self->{plr};
	if (defined $new and ($self->{points} != $new || !$abs)) {
		$new = $self->{points} + $new unless $abs;
		$self->{points} = sprintf('%.0f', $new);
	}
	return $self->{points};
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

# return the total seconds the player has been online
sub onlinetime {
	my ($self) = @_;
	return 0 unless $self->{timestamp};
	$self->timestart($self->{timestamp}) unless $self->{timestart};
	return $self->{timestamp} - $self->{timestart};
}

# The player attacked another player and did damage to them. $props may
# contain hitgroup information on where they hit the victim on their body.
# This is not a kill.
sub action_attacked {
	my ($self, $game, $victim, $weapon, $map, $props) = @_;
	my $v = $victim->id;
	my $w = $weapon->id;
	my $m = $map->id;
	my $dmg = $props->{damage} || 0;
	my $absorbed = int($props->{damage_armor}) || 0;
	;;; $self->debug7("$self attacked $victim for $dmg dmg", 0);
	$self->timestamp($props->{timestamp});

	# this is overridden in the halflife subclass to provide more detail.
	$self->{data}{damage} += $dmg;
	$self->{data}{damage_absorbed} += $absorbed;

	$self->{maps}{$m}{damage} += $dmg;
	$self->{maps}{$m}{damage_absorbed} += $absorbed;

	# we (the killer) damaged a victim
	$self->{victims}{$v}{damage} += $dmg;
	$self->{victims}{$v}{damage_absorbed} += $absorbed;

	$self->{weapons}{$w}{damage} += $dmg;
	$self->{weapons}{$w}{damage_absorbed} += $absorbed;
}

# The player changed their name
sub action_changed_name {
	my ($self, $game, $name, $props) = @_;
	;;; $self->debug7("$self changed name to $name", 0);
	$self->timestamp($props->{timestamp});
	$self->name($name);
}

# The player changed their role
sub action_changed_role {
	my ($self, $game, $role, $props) = @_;
	;;; $self->debug7("$self changed role to $role", 0);
	$self->timestamp($props->{timestamp});
	$self->role($role->name);
	$self->{roles}{$role->id}{joined}++;
}

# The player said something
sub action_chat {
	my ($self, $game, $msg, $teamonly, $props) = @_;
	my $max = ($self->conf->main->plr_chat_max || 1) - 1;
	$self->timestamp($props->{timestamp});

	$self->{chat} ||= [];
	# add the new message to the FRONT of the array, so its easier to
	# shrink the array if its too large by lopping off the oldest ones.
	unshift(@{$self->{chat}}, {
		timestamp 	=> $props->{timestamp},
		message 	=> $msg,
		team 		=> $self->team || undef,
		team_only 	=> $teamonly ? 0 : 1,
		dead 		=> $props->{dead} ? 1 : 0
	});
	
	if (@{$self->{chat}} > $max) {
		# reduce the size of the array to the max
		$#{@{$self->{chat}}} = $max;
	}
}

# The player connected to the server. This doesn't always mean the player is
# actively in the game yet. This is only useful to record some initial
# information about the player.
sub action_connected {
	my ($self, $game, $ip, $props) = @_;
	my $map = $game->get_map;
	my $m = $map ? $map->id : undef;
	;;; $self->debug7("$self connected with IP " . int2ip($ip), 0);
	$self->timestamp($props->{timestamp});
	#$self->timestart($props->{timestamp});
	
	$self->ipaddr($ip);
	$self->{data}{connections}++;
	$self->{maps}{$m}{connections}++ if $m;
}

# The player was killed (either by another player or suicide)
# $self == victim
sub action_death {
	my ($self, $game, $killer, $weapon, $map, $props) = @_;
	my $vt = $self->team;
	my $kt = $killer->team;
	my $m = $map->id;
	my $w = $weapon->id;
	my $k = $killer->id;
	my $role = $game->get_role($self->role, $self->team);
	my $r = $role ? $role->id : undef;	# victim role (optional)
	$self->timestamp($props->{timestamp});
	;;; $self->debug7("$self was killed with '$weapon'" . ($props->{headshot} ? ' (headshot)' : ''), 0);

	$self->init_plr;
	$self->is_dead(1);

	$self->{data}{deaths}++;
	$self->{maps}{$m}{deaths}++;
	$self->{roles}{$r}{deaths}++ if $r;
	$self->{victims}{$k}{deaths}++;
	$self->{weapons}{$w}{deaths}++;

	if ($props->{headshot}) {
		$self->{data}{headshot_deaths}++;
		$self->{maps}{$m}{headshot_deaths}++;
		$self->{roles}{$r}{headshot_deaths}++ if $r;
		$self->{victims}{$k}{headshot_deaths}++;
		$self->{weapons}{$w}{headshot_deaths}++;
	}

	# track the death streak for this player
	$self->end_streak('kill_streak');
	$self->inc_streak('death_streak');

	# track team based stats if possible
	return unless $vt && $kt;
	my @vars = (
		'deathsby_' . $kt,	# victim was killed by the opposing team
		$vt . '_deaths',	# victim deaths while on his team
	);
	push(@vars, 'team_deaths') if $vt eq $kt;	# friendly fire
	for (@vars) {
		$self->{data}{$_}++;
		$self->{maps}{$m}{$_}++;
		$self->{roles}{$r}{$_}++ if $r;
		$self->{victims}{$k}{$_}++;
		$self->{weapons}{$w}{$_}++;
	}
}

# the player disconnected from the server fully. They are no longer in the game.
sub action_disconnect {
	my ($self, $game, $map, $props) = @_;
	;;; $self->debug7("$self disconnected", 0);
	$self->timestamp($props->{timestamp});

	$self->end_streaks;

	# reset the timer timestamp.
	# DONT! Or we won't be able to calculate the current onlinetime.
	#$self->timestart(0);
}

# The player entered the game.
sub action_entered {
	my ($self, $game, $map, $props) = @_;
	my $m = $map->id;
	$self->firstseen(0);	# reset firstseen
	$self->timestamp($props->{timestamp});
	;;; $self->debug7("$self entered the game (map $map)", 0);
	
	# track how many games this player has played.
	$self->{data}{games}++;
	
	$self->{maps}{$m}{games}++;
	$self->{maps}{$m}{lasttime} = $props->{timestamp};

	# every time a player 'enters' a game we increment the usage counter for
	# their signature ID's, which represents how many times they've used
	# that signature.
	$self->used_ids;
}

# The player was injured by another player
# $self = victim
sub action_injured {
	my ($self, $game, $killer, $weapon, $map, $props) = @_;
	my $k = $killer->id;
	my $w = $weapon->id;
	my $m = $map->id;
	my $dmg = int($props->{damage}) || 0;
	my $absorbed = int($props->{damage_armor}) || 0;
	$self->timestamp($props->{timestamp});
	;;; $self->debug7("$self was injured by $killer for $dmg dmg ($absorbed absorbed)", 0);
	
	$self->{data}{damage_taken} += $dmg;	
	$self->{data}{damage_mitigated} += $absorbed;

	$self->{maps}{$m}{damage_taken} += $dmg;	
	$self->{maps}{$m}{damage_mitigated} += $absorbed;

	# we (the victim) took damage from killer
	$self->{victims}{$k}{damage_taken} += $dmg;	
	$self->{victims}{$k}{damage_mitigated} += $absorbed;

	$self->{weapons}{$w}{damage_taken} += $dmg;	
	$self->{weapons}{$w}{damage_mitigated} += $absorbed;
}

sub action_joined_team {
	my ($self, $game, $team, $map, $props) = @_;
	my $m = $map->id;
	;;; $self->debug7("$self joined team $team", 0);
	$self->timestamp($props->{timestamp});
	
	$self->team($team);
	$self->{data}{'joined_' . $team}++;
	$self->{maps}{$m}{'joined_' . $team}++;
}

# The player killed another player. This action should work for most mod's w/o
# needing to be overidden.
sub action_kill {
	my ($self, $game, $victim, $weapon, $map, $props) = @_;
	my $kt = $self->team;
	my $vt = $victim->team;
	my $m = $map->id;
	my $w = $weapon->id;
	my $v = $victim->id;
	my $role = $game->get_role($self->role, $self->team);
	my $r = $role ? $role->id : undef;	# killer role (optional)
	$self->timestamp($props->{timestamp});
	;;; $self->debug7("$self killed $victim with '$weapon'" . ($props->{headshot} ? ' (headshot)' : ''), 0);

	$self->init_plr;

	$self->{data}{kills}++;
	$self->{maps}{$m}{kills}++;
	$self->{roles}{$r}{kills}++ if $r;
	$self->{victims}{$v}{kills}++;
	$self->{weapons}{$w}{kills}++;

	# track headshot kills
	if ($props->{headshot}) {
		$self->{data}{headshot_kills}++;
		$self->{maps}{$m}{headshot_kills}++;
		$self->{roles}{$r}{headshot_kills}++ if $r;
		$self->{victims}{$v}{headshot_kills}++;
		$self->{weapons}{$w}{headshot_kills}++;
	}

	# track the kill streak for this player
	$self->end_streak('death_streak');
	$self->inc_streak('kill_streak');
	
	# track team based stats if possible
	return unless $vt && $kt;
	my @vars = (
		'killed_' . $vt,	# killer killed the opposing team
		$kt . '_kills',		# killer kills while on his team
	);
	push(@vars, 'team_kills') if $vt eq $kt;	# friendly fire
	for (@vars) {
		$self->{data}{$_}++;
		$self->{maps}{$m}{$_}++;
		$self->{roles}{$r}{$_}++ if $r;
		$self->{victims}{$v}{$_}++;
		$self->{weapons}{$w}{$_}++;
	}
}

# Misc action that will be used for odd-ball events (mainly from 3rd party plugins)
sub action_misc {
	#my ($self, $trigger, $map, $props) = @_;
}

# Misc action that involves another player (the victim usually)
sub action_misc_plr {
	#my ($self, $trigger, $victim, $map, $props) = @_;
	#my $m = $map ? $map->id : undef;
}

# occurs for each player at the start of every round.
sub action_round {
	my ($self, $game, $map, $props) = @_;
	my $m = $map->id;
	$self->timestamp($props->{timestamp});
	
	# reset the player's DEAD flag since the round restarted.
	$self->is_dead(0);
	
	$self->{data}{rounds}++;
	$self->{maps}{$m}{rounds}++;
}

# The player commited suicide... oops!
sub action_suicide {
	my ($self, $game, $map, $weapon, $props) = @_;
	my $m = $map->id;
	my $w = $weapon->id;
	#my $r = $self->role ? $self->get_role($self->role, $self->team) : undef;
	$self->timestamp($props->{timestamp});
	;;; $self->debug7("$self committed suicide with '$weapon'", 0);

	$self->{data}{deaths}++;
	$self->{data}{suicides}++;

	$self->{maps}{$m}{deaths}++;
	$self->{maps}{$m}{suicides}++;

	$self->{weapons}{$w}{deaths}++;
	$self->{weapons}{$w}{suicides}++;

	# track the death streak for this player
	$self->end_streak('kill_streak');
	$self->inc_streak('death_streak');
}

# generic team win event for any map game.
sub action_teamwon {
	my ($self, $game, $trigger, $team, $map, $props) = @_;	
	my $m = $map->id;
	
	# terrorist_wins, ct_wins, red_wins, blue_wins, etc...
	$self->{data}{'wins'}++;
	$self->{data}{$team . '_wins'}++;
	$self->{maps}{$m}{'wins'}++;
	$self->{maps}{$m}{$team . '_wins'}++;
}

# generic team lost event for any map game.
sub action_teamlost {
	my ($self, $game, $trigger, $team, $map, $props) = @_;	
	my $m = $map->id;
	
	# terrorist_losses, ct_losses, red_losses, blue_losses, etc...
	$self->{data}{'losses'}++;
	$self->{data}{$team . '_losses'}++;
	$self->{maps}{$m}{'losses'}++;
	$self->{maps}{$m}{$team . '_losses'}++;
}

# allow remote callers to add arbitrary data stats to the player.
# NOTE: Don't think I actually need this now...
sub data {
	my ($self, $data, $inc) = @_;
	$inc ||= 1;
	$self->{data}{$data} += $inc;
}

# track a certain variable for the player to be fetched and compared later.
sub track {
	my ($self, $var, $value) = @_;
	$self->{track}{$var} = $value;
}

# return a tracked variable
sub tracked { exists $_[0]->{track}{$_[1]} ? $_[0]->{track}{$_[1]} : undef }

# untrack 1 or more variables that were previously tracked.
sub untrack {
	my $self = shift;
	while (@_) {
		delete $self->{track}{$_};
	}
}

# short-cut to untrack all variables in one op.
sub untrack_all { %{$_[0]->{track}} = () }

# return the current value of a streak (w/o affecting current stats)
sub streak {
	my ($self, $type) = @_;
	return exists $self->{streaks}{$type} ? $self->{streaks}{$type} : 0;
}

# increment a streak value and return the new value
# TODO: inc_streak and end_streak need to be updated to allow streaks to be
# stored in other data hashes (like maps and weapons)
sub inc_streak {
	my $self = shift;
	my $type = shift;
	++$self->{streaks}{$type};
}

# end the specified streak
sub end_streak {
	my $self = shift;
	my $type;
	while (@_) {
		$type = shift;
		next unless defined $self->{streaks}{$type};
		next unless $self->{streaks}{$type} || 0 > $self->{data}{$type} || 0;
		$self->{data}{$type} = $self->{streaks}{$type};
		delete $self->{streaks}{$type};
	}
}

# ends all active streaks
sub end_streaks {
	my $self = shift;
	$self->end_streak(keys %{$self->{streaks}});
}

sub db () { $DB }
sub conf () { $CONF }
sub opt () { $OPT }

# Package method to return a $FIELDS hash for database columns
sub FIELDS {
	my ($self, $rootkey) = @_;
	my $group = ($self =~ /^PS::Plr::(.+)/)[0];
	my $root = $FIELDS;
	$group =~ s/::/_/g;
	if ($rootkey) {
		$rootkey =~ s/::/_/g;
		$FIELDS->{$rootkey} ||= {};
		$root = $FIELDS->{$rootkey};
		$root->{$group} ||= {};
	}
	return $root;
}

sub HISTORY {
	my ($self, $rootkey) = @_;
	if (!exists $HISTORY->{$rootkey}) {
		$HISTORY->{$rootkey} = {};
	}
	return $HISTORY->{$rootkey};
}

# Package method to return a $CALC hash for database columns
#sub CALC {
#	my ($self, $rootkey) = @_;
#	my $group = ($self =~ /^PS::Plr::(.+)/)[0];
#	my $root = $CALC;
#	$group =~ s/::/_/g;
#	if ($rootkey) {
#		$rootkey =~ s/::/_/g;
#		$CALC->{$rootkey} ||= {};
#		$root = $CALC->{$rootkey};
#		$root->{$group} ||= {};
#	}
#	return $root;
#}

# Package method. 
# Prepares some SQL statements for use by all objects of this class to speed
# things up a bit. These statements require a valid PS::Plr object to be created
# already. This is only called once, per sub-class.
sub prepare_statements {
	my ($class, $gametype, $modtype) = @_;
	my $db = $PS::Plr::DB || return;
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
	for my $F (qw( DATA MAPS ROLES SESSIONS WEAPONS VICTIMS )) {
		$ALL->{$F} = { map { %{$FIELDS->{$F}{$_}} } keys %{$FIELDS->{$F}} };
	}

	# find a matching plrid based on their uniqueid
	$db->prepare('find_plrid', 'SELECT plrid FROM t_plr WHERE uniqueid=? AND gametype=? AND modtype=?')
		if !$db->prepared('find_plrid');

	# fetch the basic player information matching the plrid
	$db->prepare('get_plr_basic', 'SELECT clanid,skill,points,rank,activity FROM t_plr WHERE plrid=?')
		if !$db->prepared('get_plr_basic');

	# insert a new player record based on their uniqueid.
	# plrid is auto_increment.
	$db->prepare('insert_plr', 'INSERT INTO t_plr (uniqueid,gametype,modtype,firstseen,lastseen,skill) VALUES (?,?,?,?,?,?)')
		if !$db->prepared('insert_plr');

	# update a specific t_plr variable.
	for (qw( clanid )) {	# update_plr_clanid
		$db->prepare('update_plr_' . $_, 'UPDATE t_plr SET ' . $_ . '=? WHERE plrid=?')
			if !$db->prepared('update_plr_' . $_);
	}
	
	# update_plr_ids_guid, update_plr_ids_ipaddr, update_plr_ids_name
	for (qw( guid ipaddr name )) {
		next if $db->prepared('update_plr_ids_' . $_);
		$db->prepare('update_plr_ids_' . $_,
			'INSERT INTO t_plr_ids_' . $_ . 
			' VALUES (?,?,?,?,?) ' .
			'ON DUPLICATE KEY UPDATE totaluses=totaluses+?, lastseen=?'
		);
		#$db->prepare('update_plr_ids_' . $_,
		#	'INSERT INTO t_plr_ids_' . $_ . 
		#	' VALUES (?,?,?,FROM_UNIXTIME(?),FROM_UNIXTIME(?)) ' .
		#	'ON DUPLICATE KEY UPDATE totaluses=totaluses+?, lastseen=FROM_UNIXTIME(?)'
		#);
	}

	# fetch the names most used for the plrid
	$db->prepare('get_plr_most_used_names',
		     'SELECT name FROM t_plr_ids_name WHERE plrid=? ORDER BY totaluses DESC, lastseen DESC LIMIT ?')
		if !$db->prepared('get_plr_most_used_names');

	# fetch the names most used for the plrid
	$db->prepare('get_plr_last_used_names',
		     'SELECT name FROM t_plr_ids_name WHERE plrid=? ORDER BY lastseen DESC, totaluses DESC LIMIT ?')
		if !$db->prepared('get_plr_last_used_names');
	
	# fetch the basic player profile information matching the uniqueid
	$db->prepare('get_plr_profile_basic', 'SELECT name,name_locked,cc FROM t_plr_profile WHERE uniqueid=?')
		if !$db->prepared('get_plr_profile_basic');

	# insert a new player profile record based on their uniqueid 
	$db->prepare('insert_plr_profile', 'INSERT INTO t_plr_profile (uniqueid,name) VALUES (?,?)')
		if !$db->prepared('insert_plr_profile');

	# update a player profile name
	$db->prepare('update_plr_profile_name', 'UPDATE t_plr_profile SET name=? WHERE uniqueid=?')
		if !$db->prepared('update_plr_profile_name');
	
	# returns 1 if the plr_profile record exists based on the uniqueid
	$db->prepare('plr_profile_exists', 'SELECT 1 FROM t_plr_profile WHERE uniqueid=?')
		if !$db->prepared('plr_profile_exists');

	# returns the dataid of the t_plr_data table that matches the plrid and
	# statdate.
	$db->prepare('find_plr_data', 'SELECT dataid FROM t_plr_data WHERE plrid=? AND statdate=?')
		if !$db->prepared('find_plr_data');
	#print $db->prepared('find_plr_data')->{Statement}, "\n";

	# returns plrid (true) if the compiled player ID already exists in the
	# compiled table.
	$db->prepare('find_cplr_data_' . $type, 'SELECT plrid FROM ' . $cpref . 'plr_data_' . $type . ' WHERE plrid=?')
		if !$db->prepared('find_cplr_data_' . $type);
	#print $db->prepared('find_cplr_data_' . $type)->{Statement}, "\n";

	# returns the dataid of the t_plr_* table...
	# find_plr_maps, find_plr_roles, find_plr_victims, find_plr_weapons
	for (qw( map role victim weapon )) {
		if (!$db->prepared('find_plr_' . $_ . 's')) {
			$db->prepare('find_plr_' . $_ . 's',
				sprintf('SELECT dataid FROM t_plr_%ss WHERE plrid=? AND %sid=? AND statdate=?', $_, $_)
			);
			#print $db->prepared('find_plr_' . $_ . 's')->{Statement}, "\n";
		}

		$db->prepare('find_cplr_' . $_ . 's_' . $type,
			sprintf('SELECT 1 FROM %splr_%ss_%s WHERE plrid=? AND %sid=?', $cpref, $_, $type, $_)
		);
		#print $db->prepared('find_cplr_' . $_ . 's_' . $type)->{Statement}, "\n";
	}
	
	# returns the latest session info for the plrid given...
	if (!$db->prepared('find_plr_session')) {
		$db->prepare('find_plr_session', 
			"SELECT dataid,session_start,session_end FROM $db->{t_plr_sessions} " .
			"WHERE plrid=? AND mapid=? " . 
			"ORDER BY session_end DESC LIMIT 1"
		);
	}

	# insert a new plr_data row
	$db->prepare('insert_plr_data',
		'INSERT INTO t_plr_data (plrid,statdate,firstseen,lastseen,rank,skill) ' .
		'VALUES (?,?,?,?,?,?)'
	) if !$db->prepared('insert_plr_data');
	#print $db->prepared('insert_plr_data')->{Statement}, "\n";

	if (!$db->prepared('insert_plr_data_' . $type)) {
		$db->prepare('insert_plr_data_' . $type, sprintf(
			'INSERT INTO %splr_data_%s (dataid,%s) ' .
			'VALUES (?%s)',
			$pref, $type, 
			join(',', @{$ORDERED_HISTORY->{DATA}}),
			',?' x @{$ORDERED_HISTORY->{DATA}}
		));
		#print $db->prepared('insert_plr_data_' . $type)->{Statement}, "\n";

		# COMPILED STATS
		$db->prepare('insert_cplr_data_' . $type, sprintf(
			'INSERT INTO %splr_data_%s (plrid,%s) ' .
			'VALUES (?%s)',
			$cpref, $type,
			join(',', @{$ORDERED->{DATA}}),
			',?' x @{$ORDERED->{DATA}}
		));
		#print $db->prepared('insert_cplr_data_' . $type)->{Statement}, "\n";
	}

	# insert a new plr_* row for each of the listed tables below.
	# insert_plr_maps, insert_plr_roles, insert_plr_weapons, insert_plr_victims
	foreach my $t (qw( map role weapon victim )) {
		my $name = 'insert_plr_'. $t . 's';
		my $cname = 'insert_cplr_'. $t . 's_' . $type;
		my $F = uc $t . 's';
		if (!$db->prepared($name)) {
			$db->prepare($name, sprintf(
				'INSERT INTO t_plr_%ss (plrid,%sid,statdate,firstseen,lastseen) ' .
				'VALUES (?,?,?,?,?)',
				$t, $t
			));
		}

		# insert a new plr_*_gametype_modtype row
		$name .= '_' . $type;
		if (!$db->prepared($name)) {
			if (@{$ORDERED_HISTORY->{$F}}) {
				$db->prepare($name, sprintf(
					'INSERT INTO %splr_%ss_%s (dataid,%s) ' .
					'VALUES (?%s)',
					$pref, $t, $type, 
					join(',', @{$ORDERED_HISTORY->{$F}}),
					',?' x @{$ORDERED_HISTORY->{$F}}
				));
				#print "$name: ", $db->prepared($name)->{Statement}, "\n";
			}
			if (@{$ORDERED->{$F}}) {
				# COMPILED STATS
				$db->prepare($cname, sprintf(
					'INSERT INTO %splr_%ss_%s (plrid,%sid,%s) ' .
					'VALUES (?,?%s)',
					$cpref, $t, $type, $t, 
					join(',', @{$ORDERED->{$F}}),
					',?' x @{$ORDERED->{$F}}
				));
				#print "$cname: ", $db->prepared($cname)->{Statement}, "\n";
			}
		}
	}

	# get skill from most recent session for a player
	$db->prepare('get_plr_session_prev_skill',
		'SELECT skill FROM t_plr_sessions ' .
		'WHERE plrid=? ' .
		'ORDER BY session_start DESC ' .
		'LIMIT 1'
	) if !$db->prepared('get_plr_session_prev_skill');

	# insert a new plr_sessions row
	$db->prepare('insert_plr_sessions',
		'INSERT INTO t_plr_sessions ' .
		'VALUES (NULL,?,?,?,?,?,?)'
	) if !$db->prepared('insert_plr_sessions');
	#print $db->prepared('insert_plr_sessions')->{Statement}, "\n";

	if (!$db->prepared('insert_plr_sessions_' . $type)) {
		$db->prepare('insert_plr_sessions_' . $type, sprintf(
			#'INSERT INTO %splr_sessions_%s (dataid,%s) ' .
			#'VALUES (?%s)',
			#$pref, $type, 
			#join(',', @{$ORDERED->{SESSIONS}}),
			#',?' x @{$ORDERED->{SESSIONS}}
			'INSERT INTO %splr_sessions_%s ' .
			'VALUES (?%s)',
			$pref, $type, 
			',?' x @{$ORDERED->{SESSIONS}}
		));
		#print $db->prepared('insert_plr_sessions_'.$type)->{Statement}, "\n";
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
		return 1;
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
# mod specified. This is automatically called once when a new player type is
# instantiated.
# ::configure should have already been called with a valid $DB handle to use.
sub init_game_database {
	my ($class, $gametype, $modtype) = @_;
	my $type = $modtype ? $gametype . '_' . $modtype : $gametype;

	# init all game_mod stat tables for the player. The 'sessions' table is
	# handled slightly different (its not a history table).
	for my $t (qw( data maps roles sessions weapons victims )) {
		my $F = uc $t;
		# sessions use all fields configured, the rest of the history
		# tables use only the history fields configured.
		my $cols = $t eq 'sessions' ? $FIELDS->{$F} : { data => $HISTORY->{$F} };
		
		$class->_init_table($DB->tbl('plr_' . $t . '_' . $type), $cols);
		
		# sessions do not have a 'compiled' table
		next if $t eq 'sessions';

		my $primary = { plrid => 'uint' };
		if ($t eq 'data') {
			$class->_init_table($DB->ctbl('plr_' . $t . '_' . $type), $FIELDS->{$F}, $primary);
		} else {
			my $key = substr($t, 0, -1) . 'id';
			my $order = [ 'plrid', $key ];
			$primary->{$key} = 'uint';
			my $created = $class->_init_table($DB->ctbl('plr_' . $t . '_' . $type), $FIELDS->{$F}, $primary, $order);

			# create an index on just the secondary key
			if ($created and grep { $t eq $_ } qw( maps roles weapons victims )) {
				$DB->create_index($DB->ctbl('plr_' . $t . '_' . $type), $key, $key);
			}
		}
	}
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