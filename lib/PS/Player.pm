# Base Player class. This is a basic factory class that creates a Player object based on the current gametype.
# If a subclass is detected for the current gametype it will be created and returned.
# Order of class detection (first class to be found is used):
#	PS::Player::{gametype}::{modtype}
#	PS::Player::{gametype}
#	PS::Player
# The first time a player object is created it's baseclass is saved so all other player objects will be created
# in the same way w/o trying to search for subclasses (small performance gain).
#
package PS::Player;

use strict;
use warnings;
use base qw( PS::Debug );
use Data::Dumper;
use POSIX;
use util qw( :date );

our $VERSION = '1.00';
our $BASECLASS = undef;

our $GAMETYPE = '';
our $MODTYPE = '';

# variable types that can be stored in the table. Any variable not defined here is ignored when saving to the DB
our $TYPES = {
	dataid		=> '=', 
	plrid		=> '=',
	dayskill	=> '=',
	dayrank		=> '=',
	statdate	=> '=',
	onlinetime	=> '+',
	kills		=> '+',
	deaths		=> '+', 
	killsperdeath	=> [ ratio => qw( kills deaths ) ],
	killsperminute	=> [ ratio_minutes => qw( kills onlinetime ) ],
	headshotkills	=> '+',
	headshotkillspct=> [ percent => qw( headshotkills kills ) ],
	headshotdeaths	=> '+',
	ffkills		=> '+',
	ffkillspct	=> [ percent => qw( ffkills kills ) ],
	ffdeaths	=> '+',
	ffdeathspct	=> [ percent => qw( ffdeaths deaths ) ],
	kills_streak	=> '>',
	deaths_streak	=> '>',
	damage		=> '+',
	shots		=> '+',
	hits		=> '+',
	shotsperkill	=> [ ratio => qw( shots kills ) ],
	accuracy	=> [ percent => qw( hits shots ) ],
	suicides	=> '+', 
	games		=> '+',
	rounds		=> '+',
	kicked		=> '+',
	banned		=> '+',
	cheated		=> '+',
	connections	=> '+',
	totalbonus	=> '+',
	lasttime	=> '>',
};

our $TYPES_PLRSESSIONS = {
	plrid		=> '=',
	sessionstart	=> '=',
	sessionend	=> '=',
	skill		=> '=',
	kills		=> '+',
	deaths		=> '+', 
#	killsperdeath	=> [ ratio => qw( kills deaths ) ],
#	killsperminute	=> [ ratio_minutes => qw( kills onlinetime ) ],
	headshotkills	=> '+',
#	headshotkillspct=> [ percent => qw( headshotkills kills ) ],
	headshotdeaths	=> '+',
	ffkills		=> '+',
#	ffkillspct	=> [ percent => qw( ffkills kills ) ],
	ffdeaths	=> '+',
#	ffdeathspct	=> [ percent => qw( ffdeaths deaths ) ],
	damage		=> '+',
	shots		=> '+',
	hits		=> '+',
#	shotsperkill	=> [ ratio => qw( shots kills ) ],
#	accuracy	=> [ percent => qw( hits shots ) ],
	suicides	=> '+', 
	totalbonus	=> '+',
};

our $TYPES_WEAPONS = {
	dataid		=> '=',
	plrid		=> '=',
	weaponid	=> '=',
	statdate	=> '=',
	kills		=> '+',
	deaths		=> '+',
	headshotkills	=> '+',
	headshotkillspct=> [ percent => qw( headshotkills kills ) ],
	headshotdeaths	=> '+',
	damage		=> '+',
	shots		=> '+',
	hits		=> '+',
	shot_head	=> '+',
	shot_chest	=> '+',
	shot_stomach	=> '+',
	shot_leftarm	=> '+',
	shot_rightarm	=> '+',
	shot_leftarm	=> '+',
	shot_rightleg	=> '+',
	shot_leftleg	=> '+',
	shotsperkill	=> [ ratio => qw( shots kills ) ],
	accuracy	=> [ percent => qw( hits shots ) ],
};

our $TYPES_MAPS = {
	dataid		=> '=',
	plrid		=> '=',
	mapid		=> '=',
	statdate	=> '=',
	games		=> '+',
	rounds		=> '+',
	kills		=> '+',
	deaths		=> '+', 
	killsperdeath	=> [ ratio => qw( kills deaths ) ],
	killsperminute	=> [ ratio_minutes => qw( kills onlinetime ) ],
	ffkills		=> '+',
	ffkillspct	=> [ percent => qw( ffkills kills ) ],
	ffdeaths	=> '+',
	ffdeathspct	=> [ percent => qw( ffdeaths deaths ) ],
	connections	=> '+',
	onlinetime	=> '+',
	lasttime	=> '>',
};

our $TYPES_VICTIMS = {
	dataid		=> '=',
	plrid		=> '=',
	victimid	=> '=',
	statdate	=> '=',
	kills		=> '+',
	deaths		=> '+', 
	killsperdeath	=> [ ratio => qw( kills deaths ) ],
	headshotkills	=> '+',
	headshotkillspct=> [ percent => qw( headshotkills kills ) ],
	headshotdeaths	=> '+',
};

our $TYPES_ROLES = { };

our $_config_cache = {};

sub new {
	my ($proto, $plrids, $game) = @_;
	my $baseclass = ref($proto) || $proto;
	my $self = { 
		plrid => 0, plrids => $plrids, uniqueid => undef, 
		game => $game, conf => $game->{conf}, db => $game->{db},
		debug => 0, 
		saved => 0,			# has the plr been saved since marked active?
		active => 0			# is the plr active?
	};
	my $class = _determine_class($self, $baseclass);

	$self->{class} = $class;
	bless($self, $class);

	if (!$BASECLASS) {
		$self->_init_table;
		$self->_init_table_maps;
		$self->_init_table_roles;
		$self->_init_table_victims;
		$self->_init_table_weapons;
		$BASECLASS = $class;
	}

	return $self->_init;
}

# Not a class method; private use only.
sub _determine_class {
	my $self = shift;
	my $baseclass = shift;
	my $class = '';

	# determine what kind of player we are going to be using the first time we're created
	if (!$BASECLASS) {
		$GAMETYPE = $self->{conf}->get('gametype');
		$MODTYPE = $self->{conf}->get('modtype');

		my @ary = $MODTYPE ? ($MODTYPE, $GAMETYPE) : ($GAMETYPE);
		while (@ary) {
			$class = join("::", $baseclass, reverse(@ary));
			eval "require $class";
			if ($@) {
				if ($@ !~ /^Can't locate/i) {
					$::ERR->warn("Compile error in class $class:\n$@\n");
					return undef;
				} 
				undef $class;
				shift @ary;
			} else {
				last;
			}
		}

		# STILL nothing? -- We give up, nothing more to try (using PS::Player directly will do us no good) ...
#		$::ERR->fatal("No suitable Player class found. HALTING") if !$class;
		$class = $baseclass if !$class;
	} else {
		$class = $BASECLASS;
	}
	return $class;
}

sub _init { 
	my $self = shift;
	my $db = $self->{db};

	$self->{conf_plr_primary_name} = $self->{conf}->get_main('plr_primary_name');
	$self->{conf_uniqueid} = $self->{conf}->get_main('uniqueid');
	$self->{conf_maxdays} = $self->{conf}->get_main('maxdays');
	$self->{conf_plr_sessions_max} = $self->{conf}->get_main('plr_sessions_max');
	$self->{conf_plr_save_victims} = $self->{conf}->get_main('plr_save_victims');
	$self->{conf_baseskill} = $self->{conf}->get_main('baseskill');
	
	$self->{decay_type} = $self->{conf}->get_main('players.decay_type');
	$self->{decay_hours} = $self->{conf}->get_main('players.decay_hours');
	$self->{decay_value} = $self->{conf}->get_main('players.decay_value');
	$self->{decay} = ($self->{decay_type});		# if it's non-zero and not empty

	# set some common defaults for all players (any game)
#	$self->{plrid} = 0;
	$self->{team} = '';
	$self->{isdead} = 0;
	$self->{streaks} = {};
	$self->{pq} = 0;		# player IQ (strength) used in skill calculations

	# don't do anything else if we have no plrids.
	# this will mean that a blank player object was created (mainly used in Game::daily_maxdays)
	return $self unless defined $self->{plrids};

	$self->uniqueid( $self->{plrids}{ $self->{conf_uniqueid} } );

	$self->{plrid} = $db->select($db->{t_plr}, 'plrid', [ uniqueid => $self->uniqueid ]);
	# player does not exist so we create a new record for them
	if (!$self->{plrid}) {
		$db->begin;
		$self->{plrid} = $db->next_id($db->{t_plr}, 'plrid');
		$self->skill( $self->{conf_baseskill} );
		my $res = $db->insert($db->{t_plr}, { 
			plrid 		=> $self->plrid,
			uniqueid 	=> $self->uniqueid,
			firstseen	=> $self->{game}->{timestamp},
			lastdecay	=> $self->{game}->{timestamp},
			skill 		=> $self->skill,
			prevskill 	=> $self->skill,
		});
		$self->fatal("Error adding player to database: " . $db->errstr) unless $res;

		# make sure the players profile is present
		if (!$db->select($db->{t_plr_profile}, 'uniqueid', [ uniqueid => $self->uniqueid ])) {
#			print "_init: ",$self->name,"\n" if $self->worldid eq 'STEAM_0:0:7702999';
			$db->insert($db->{t_plr_profile}, { 
				uniqueid => $self->uniqueid,
				name => $self->name,
				logo => ''
			});
		}
		$db->commit;
	}

#	do not do this automatically. It's now up to the calling code to do it instead
#	$self->plrids;

	# load the players basic information 
	$self->{_plr} = $db->get_row_hash("SELECT clanid,prevrank,rank,prevskill,skill,allowrank,lastdecay FROM " . 
		$db->{t_plr} . " WHERE plrid=" . $db->quote($self->{plrid})
	);

	# load current stats for player
	$self->load_stats;

	if ($self->{decay}) {
		$self->decay();
	}

	return $self; 
}

sub decay {
	my ($self, $_type, $_hours, $_value) = @_;
	return if $self->skill < $self->{conf_baseskill};	# don't do anything if we are already too low
	my $type 	= $_type  || $self->{decay_type}  || return;
	my $maxhours 	= $_hours || $self->{decay_hours} || return;
	my $value 	= $_value || $self->{decay_value} || return;
	my $seconds = $maxhours * 60 * 60;
	my $diff = ($self->{game}{timestamp} - $self->{_plr}{lastdecay});
	my $length = $diff / $seconds;
	return unless $length >= 1.0;
	$value *= $length;

#	print "diff: $diff, len: $length, val: $value\n";

	my $set = { lastdecay => $self->{game}{timestamp} };
	$type = lc $type;
	if ($type eq 'flat') {
		$set->{skill} = $self->skill - $value;
	} else { # $type eq 'percent'
		$set->{skill} = $self->skill - ($self->skill * $value / 100);
	}

	# don't let the decay go below the base skill
	$set->{skill} = $self->{conf_baseskill} if $set->{skill} < $self->{conf_baseskill};

	# update the players skill value
	$self->{db}->update($self->{db}{t_plr}, $set, [ plrid => $self->{plrid} ]);
#	print "before: " , $self->skill, " :: ", $self->{db}->lastcmd,"\n";
}

# loads the current player stats from the _compiled_ player data
# This data is generally used as a snapshot for other various routines.
sub load_stats {
	my $self = shift;
	my $db = $self->{db};
	$self->{_stats} = $db->get_row_hash("SELECT * FROM $db->{c_plr_data} WHERE plrid=" . $db->quote($self->plrid)) || {};	
}

sub load_profile {
	my ($self) = @_;
	my $db = $self->{db};
	$self->{_profile} = $db->get_row_hash("SELECT * FROM $db->{t_plr_profile} WHERE uniqueid=" . $db->quote($self->uniqueid));
}

sub load_day_stats {
	my $self = shift;
	my $statdate = shift || $self->{statdate} || time;
	my $db = $self->{db};
	my $cmd;
	$self->{_daystats} = {};

	$cmd  = "SELECT * FROM $db->{t_plr_data}";
	$cmd .=	" LEFT JOIN $db->{t_plr_data_mod} USING (dataid)" if $db->{t_plr_data_mod};
	$cmd .= " WHERE plrid=" . $db->quote($self->{plrid}) . " AND statdate=" . $db->quote($statdate);
	$self->{_daystats} = $db->get_row_hash($cmd) || {};
	return $self->{_daystats};
}

# Updates the plr_ids information in the database for this player.
# every call to this function results in the matching plr_ids row to have it's `totaluses` value incremented.
# if $inc is 0 then `totaluses` will not be incremented but a row for the $plrids given will still be created if needed.
sub plrids {
	my $self = shift;
	my $db = $self->{db};

	my $plrids = {
		%{$self->{plrids}},			# default to current plrids
		(@_ ? %{(shift)} : ())			# allow the caller to send a full/partial ids hash to override values
	};
	my $inc = @_ ? shift : 1;

	$self->{plrids} = $plrids;

	my ($id,$totaluses) = $db->select($db->{t_plr_ids}, [qw( id totaluses )], [
		name => $plrids->{name},
		worldid => $plrids->{worldid},
		ipaddr => $plrids->{ipaddr}
	]);

#	use Data::Dumper; print "plrids($id): ", Dumper($plrids) if $plrids->{worldid} eq 'STEAM_0:0:7702999';

	# If there was no id then we need to add a new row for this player's ids
	if (!$id) {
		$db->insert($db->{t_plr_ids}, { 
			id => $db->next_id($db->{t_plr_ids}),
			plrid => $self->{plrid},
			name => $plrids->{name}, 
			worldid => $plrids->{worldid}, 
			ipaddr => $plrids->{ipaddr}, 
			totaluses => $inc > 0 ? $inc : 1
		});
	} elsif ($inc > 0) {
		$totaluses ||= 0;
		$db->update($db->{t_plr_ids}, { totaluses => $totaluses + $inc }, [ id => $id ]);
	}
#	print "plrids($id): ",$db->lastcmd,"\n" if $plrids->{worldid} eq 'STEAM_0:0:7702999';
}


# makes sure the compiled player data table is already setup
sub _init_table {
	my $self = shift;
	my $conf = $self->{conf};
	my $db = $self->{db};
	my $basetable = 'plr_data';
	my $table = $db->ctbl($basetable);
	my $tail = '';
	my $fields = {};
	my @order = ();
	$tail .= "_$GAMETYPE" if $GAMETYPE;
	$tail .= "_$MODTYPE" if $GAMETYPE and $MODTYPE;
	return if $db->table_exists($table);

	# get all keys used in the 2 tables so we can combine them all into a single table
	$fields->{$_} = 'int' foreach keys %{$db->tableinfo($db->tbl($basetable))};
	if ($tail and $self->has_mod_tables) {
		$fields->{$_} = 'int' foreach keys %{$db->tableinfo($db->tbl($basetable . $tail))};
	}

	# remove unwanted/special keys
	delete @$fields{ qw( statdate dayskill dayrank firstdate lastdate ) };

	# add extra keys
	my $alltypes = $self->get_types;
	$fields->{$_} = 'date' foreach qw( firstdate lastdate ); 
	$fields->{$_} = 'uint' foreach qw( dataid plrid ); 	# unsigned
	$fields->{$_} = 'float' foreach grep { ref $alltypes->{$_} } keys %$alltypes;

	# build the full set of keys for the table
	@order = (qw( dataid plrid firstdate lastdate ), sort grep { !/^((data|plr)id|(first|last)date)$/ } keys %$fields );

	$db->create($table, $fields, \@order);
	$db->create_primary_index($table, 'dataid');
	$db->create_unique_index($table, 'plrid', 'plrid');
	$self->info("Compiled table $table was initialized.");
}

sub _init_table_maps {
	my $self = shift;
	my $conf = $self->{conf};
	my $db = $self->{db};
	my $basetable = 'plr_maps';
	my $table = $db->ctbl($basetable);
	my $tail = '';
	my $fields = {};
	my @order = ();
	$tail .= "_$GAMETYPE" if $GAMETYPE;
	$tail .= "_$MODTYPE" if $GAMETYPE and $MODTYPE;
	return if $db->table_exists($table);

	# get all keys used in the 2 tables so we can combine them all into a single table
	$fields->{$_} = 'int' foreach keys %{$db->tableinfo($db->tbl($basetable))};
	if ($tail and $self->has_mod_tables) {
		$fields->{$_} = 'int' foreach keys %{$db->tableinfo($db->tbl($basetable . $tail))};
	}

	# remove unwanted/special keys
	delete @$fields{ qw( statdate firstdate lastdate ) };

	# add extra keys
	my $alltypes = $self->get_types_maps;
	$fields->{$_} = 'date' foreach qw( firstdate lastdate ); 
	$fields->{$_} = 'uint' foreach qw( dataid plrid mapid );	# unsigned
	$fields->{$_} = 'float' foreach grep { ref $alltypes->{$_} } keys %$alltypes;

	# build the full set of keys for the table
	@order = (qw( dataid plrid mapid firstdate lastdate ), sort grep { !/^((data|plr|map)id|(first|last)date)$/ } keys %$fields );

	$db->create($table, $fields, \@order);
	$db->create_primary_index($table, 'dataid');
	$db->create_unique_index($table, 'plrmaps', qw( plrid mapid ));
	$self->info("Compiled table $table was initialized.");
}

sub _init_table_victims {
	my $self = shift;
	my $conf = $self->{conf};
	my $db = $self->{db};
	my $basetable = 'plr_victims';
	my $table = $db->ctbl($basetable);
	my $tail = '';
	my $fields = {};
	my @order = ();
	$tail .= "_$GAMETYPE" if $GAMETYPE;
	$tail .= "_$MODTYPE" if $MODTYPE;
	return if $db->table_exists($table);

	# get all keys used in the 2 tables so we can combine them all into a single table
	$fields->{$_} = 'int' foreach keys %{$db->tableinfo($db->tbl($basetable))};
# victims do not currently allow for game/modtype extensions
#	if ($tail and $self->has_mod_tables) {
#		$fields->{$_} = 'int' foreach keys %{$db->tableinfo($basetable . $tail)};
#	}

	# remove unwanted/special keys
	delete @$fields{ qw( statdate firstdate lastdate ) };

	# add extra keys
	my $alltypes = $self->get_types_victims;
	$fields->{$_} = 'date' foreach qw( firstdate lastdate ); 
	$fields->{$_} = 'uint' foreach qw( dataid plrid victimid );	# unsigned
	$fields->{$_} = 'float' foreach grep { ref $alltypes->{$_} } keys %$alltypes;

	# build the full set of keys for the table
	@order = (qw( dataid plrid victimid firstdate lastdate ), sort grep { !/^((data|plr|victim)id|(first|last)date)$/ } keys %$fields );

	$db->create($table, $fields, \@order);
	$db->create_primary_index($table, 'dataid');
	$db->create_unique_index($table, 'plrvictims', qw( plrid victimid ));
	$self->info("Compiled table $table was initialized.");
}

sub _init_table_roles { }

sub _init_table_weapons {
	my $self = shift;
	my $conf = $self->{conf};
	my $db = $self->{db};
	my $basetable = 'plr_weapons';
	my $table = $db->ctbl($basetable);
	my $tail = '';
	my $fields = {};
	my @order = ();
	$tail .= "_$GAMETYPE" if $GAMETYPE;
	$tail .= "_$MODTYPE" if $MODTYPE;
	return if $db->table_exists($table);

	# get all keys used in the 2 tables so we can combine them all into a single table
	$fields->{$_} = 'int' foreach keys %{$db->tableinfo($db->tbl($basetable))};
# weapons do not currently allow for game/modtype extensions
#	if ($tail and $self->has_mod_tables) {
#		$fields->{$_} = 'int' foreach keys %{$db->tableinfo($basetable . $tail)};
#	}

	# remove unwanted/special keys
	delete @$fields{ qw( statdate firstdate lastdate ) };

	# add extra keys
	my $alltypes = $self->get_types_weapons;
	$fields->{$_} = 'date' foreach qw( firstdate lastdate ); 
	$fields->{$_} = 'uint' foreach qw( dataid plrid weaponid );	# unsigned
	$fields->{$_} = 'float' foreach grep { ref $alltypes->{$_} } keys %$alltypes;

	# build the full set of keys for the table
	@order = (qw( dataid plrid weaponid firstdate lastdate ), sort grep { !/^((data|plr|weapon)id|(first|last)date)$/ } keys %$fields );

	$db->create($table, $fields, \@order);
	$db->create_primary_index($table, 'dataid');
	$db->create_unique_index($table, 'plrweapons', qw( plrid weaponid ));
	$self->info("Compiled table $table was initialized.");
}


sub plrid { $_[0]->{plrid} }
sub name { @_==1 ? $_[0]->{plrids}{name} : ($_[0]->{plrids}{name} = $_[1]) }
sub worldid { @_==1 ? $_[0]->{plrids}{worldid} : ($_[0]->{plrids}{worldid} = $_[1]) }
sub ipaddr { @_==1 ? $_[0]->{plrids}{ipaddr} : ($_[0]->{plrids}{ipaddr} = $_[1]) }
sub uniqueid { @_==1 ? $_[0]->{uniqueid} : ($_[0]->{uniqueid} = $_[1]) }
sub uid { @_==1 ? $_[0]->{uid} : ($_[0]->{uid} = $_[1]) }

sub statdate {
	return $_[0]->{statdate} if @_ == 1;
	my $self = shift;
	my ($d,$m,$y) = (localtime(shift))[3,4,5];
	my $newdate = sprintf("%04d-%02d-%02d",$y+1900,$m+1,$d);
#	if ($newdate ne $self->{statdate}) {
#		$self->save;
		$self->{statdate} = $newdate;
#	}
}

sub isDead { 
	if (@_ == 1) {
		return $_[0]->{isdead} || 0;
	} else {
		my $old = $_[0]->{isdead} || 0;
		$_[0]->{isdead} = $_[1];
		return $old;
	}
}

sub timerstart {
	my $self = shift;
	my $timestamp = shift;
	my $prevtime = 0;
#	no warnings;						# don't want any "undef" or "uninitialized" errors

	# a previous timer was already started, get it's elapsed value
	if ($self->active) { # && $self->{firsttime} && $self->{firsttime} != $self->{basic}{lasttime}) {
		$prevtime = $self->timer; #$self->{basic}{lasttime} - $self->{firsttime};
	}
	$self->{firsttime} = $self->{basic}{lasttime} = $timestamp;	# start new timer with current timestamp
	$self->statdate($timestamp) unless $self->statdate;		# set the statdate if it wasn't set already
	return $prevtime;
}

# return the total time that has passed since the timer was started
sub timer {
	my $self = shift;
	return 0 unless $self->{firsttime} and $self->{basic}{lasttime};
	my $t = $self->{basic}{lasttime} - $self->{firsttime};
	# If $t is negative then there's a chance that DST "fall back" just occured, so the timestamp is going to be -1 hour.
	# I try to compensate for this here by fooling the routines into thinking the time hasn't actually changed. this will
	# cause minor timing issues but the result is better then the player receiving NO time at all.
	if ($t < 0) {
		$t += 3600;	# add 1 hour. 
	}
	return $t > 0 ? $t : 0;
}

# returns the total online time for the player; current time + compiled time. used in skill calculations
sub totaltime {
	my $self = shift;
#	no warnings;		# we don't care about un-init errors in the line below
	return $self->timer + ($self->{_stats}{onlinetime} || 0);
}

sub update_streak {
	my $self = shift;
	my $type = shift;
	$self->end_streak(@_) if @_;
	$self->{streaks}{$type}++;
}

sub end_streak {
	my $self = shift;
	my $type;
	no warnings;
	while (@_) {
		$type = shift;
		next unless defined $self->{streaks}{$type};
		next unless $self->{streaks}{$type} > $self->{basic}{"${type}_streak"};
		$self->{basic}{"${type}_streak"} = $self->{streaks}{$type};
		delete $self->{streaks}{$type};
	}
}

sub end_all_streaks {
	my $self = shift;
	$self->end_streak(keys %{$self->{streaks}});
}

sub clanid {
	my $self = shift;
	my $old = $self->{_plr}{clanid};
	$self->{_plr}{clanid} = shift if @_;
	return $old;
}

sub prevskill {
	my $self = shift;
	my $old = $self->{_plr}{prevskill} || 0;
	$self->{_plr}{prevskill} = shift if @_;
	return $old;
}

sub skill {
	my $self = shift;
	my $old = $self->{_plr}{skill} || 0;
	$self->{_plr}{skill} = shift if @_;
	return $old;
}

sub prevrank {
	my $self = shift;
	my $old = $self->{_plr}{prevrank} || 0;
	$self->{_plr}{prevrank} = shift if @_;
	return $old;
}

sub rank {
	my $self = shift;
	my $old = $self->{_plr}{rank} || 0;
	$self->{_plr}{rank} = shift if @_;
	return $old;
}

sub pq {
	return $_[0]->{pq} if @_ == 1;
	my $old = $_[0]->{pq};
	$_[0]->{pq} = $_[1];
	return $old;
}

sub allowrank {
	my $self = shift;
	my $old = $self->{_plr}{allowrank};
	$self->{_plr}{allowrank} = shift if @_;
	return $old;
}

sub active {
	my $self = shift;
	my $old = $self->{active};
	$self->{active} = shift if @_;
	return $old;
}

sub saved {
	my $self = shift;
	my $old = $self->{saved};
	$self->{saved} = shift if @_;
	return $old;
}

sub lastdecay {
	my $self = shift;
	my $old = $self->{_plr}{lastdecay} || 0;
	$self->{_plr}{lastdecay} = shift if @_;
	return $old;
}


# sets/gets the current signature. 
# this is mainly used by the PS::Game caching routines
sub signature { 
	my $self = shift;
	return $self->{signature} unless scalar @_;
	my $old = $self->{signature};
	$self->{signature} = shift;
	return $old;
}

# Sets the players profile name to be the name that has currently been used the most.
# this function does not check the 'namelocked' player profile variable.
sub most_used_name {
	my $self = shift;
#	return unless $self->{conf_plr_primary_name} eq 'most';
	my $db = $self->{db};
	my ($name1) = $db->select($db->{t_plr_ids}, 'name', [ plrid => $self->plrid ], "totaluses DESC");
	my ($name2) = $db->select($db->{t_plr_profile}, 'name', [ uniqueid => $self->uniqueid ] );
	if (defined($name1) and $name1 ne '' and $name1 ne $name2) {
		$db->update($db->{t_plr_profile}, { name => $name1 }, [ uniqueid => $self->uniqueid ]);
		$self->name($name1);
#		$self->clanid(0);
	} else {
		$name1 = undef;
	}
	return $name1;
}

# Sets the players profile name to be the name that was last used.
# this function does not check the 'namelocked' player profile variable.
sub last_used_name {
	my $self = shift;
#	return unless $self->{conf_plr_primary_name} eq 'last';
	my $db = $self->{db};
	my ($name1) = $db->select($db->{t_plr_ids}, 'name', [ plrid => $self->plrid ], "id DESC");
	my ($name2) = $db->select($db->{t_plr_profile}, 'name', [ uniqueid => $self->uniqueid ] );
	if (defined($name1) and $name1 ne '' and $name1 ne $name2) {
		$db->update($db->{t_plr_profile}, { name => $name1 }, [ uniqueid => $self->uniqueid ]);
		$self->name($name1);
#		$self->clanid(0);
	} else {
		$name1 = undef;
	}
	return $name1;
}

# player is considered disconnected from the server, so do any cleanup that is required
# the player is not actually deleted (or undef'd from memory) or saved.
sub disconnect {
	my ($self, $timestamp, $map) = @_;

#	if ($self->worldid eq 'STEAM_0:1:6048454') {
#		print "disconnected: \t" . date("%H:%i:%s", $timestamp) . " (" . $self->timer . ")\n";
#	}

	if ($self->active) {
		my $time = $self->timer;
		$self->{basic}{onlinetime} += $time;
		$self->{maps}{ $map->{mapid} }{onlinetime} += $time if defined $map;
	}
#	$self->timerstart($timestamp);
}

sub get_types { $TYPES }
sub get_types_maps { $TYPES_MAPS }
sub get_types_roles { $TYPES_ROLES }
sub get_types_weapons { $TYPES_WEAPONS }
sub get_types_victims { $TYPES_VICTIMS }

# subclasses override this to save their own special vars to the database (but also call this first)
sub save {
	my $self = shift;
	my $nocommit = shift;
	my $db = $self->{db};
	my $dataid;

	# grab some profile info
	my ($namelocked, $cc) = $db->select($db->{t_plr_profile}, [qw( namelocked cc )], [ uniqueid => $self->uniqueid ]);

	$db->begin unless $nocommit;

	# save basic player information (plr table)
	$self->{_plr}{lastdecay} = $self->{basic}{lasttime} || $self->{game}->{timestamp};
	$db->update($db->{t_plr}, $self->{_plr}, [ plrid => $self->plrid ]);

	# update the prevskill for the player (from the previous day)
	my $id = $db->quote($self->plrid);
	if ($db->subselects) {
		$db->query("UPDATE $db->{t_plr} SET prevskill=IFNULL(" . 
			"(SELECT dayskill FROM $db->{t_plr_data} WHERE plrid=$id ORDER BY statdate DESC " . $db->limit(1,1) . ")" . 
			",prevskill) WHERE plrid=$id");
	} else {
		my ($prevskill) = $db->get_list("SELECT dayskill FROM $db->{t_plr_data} WHERE plrid=$id ORDER BY statdate DESC " . $db->limit(1,1));
		$db->update($db->{t_plr}, { prevskill => $prevskill }, [ plrid => $self->plrid ]) if defined $prevskill;
	}

	# update most used name if the name is not locked
	if (!$namelocked and $self->{conf_uniqueid} ne 'name') {
		if ($self->{conf_plr_primary_name} eq 'most') {
			$self->most_used_name;
		} elsif ($self->{conf_plr_primary_name} eq 'last') {
			$self->last_used_name;
		}
	};

	# update the player's country code if one is not already set
        if ((!defined $cc or $cc eq '') and $self->ipaddr) {
                $cc = $db->select($db->{t_geoip_ip}, 'cc', $self->ipaddr . " BETWEEN " . $db->qi('start') . " AND " . $db->qi('end'));
		$db->update($db->{t_plr_profile}, [ cc => $cc ], [ uniqueid => $self->uniqueid ]) if defined $cc and $cc ne '';
        }

#	if ($self->worldid eq 'STEAM_0:1:6335774') {
#		$self->debug('saving ' . $self->name);
#	}

	# save the current session separately
	if ($self->{conf_plr_sessions_max} and $self->{basic}{lasttime}) {
		my $session = {};
		# get most recent player session
		my ($id, $start, $end) = $db->get_row_array(
			"SELECT dataid,sessionstart,sessionend FROM $db->{t_plr_sessions} " .
			"WHERE plrid=$self->{plrid} " . 
			"ORDER BY sessionstart DESC "
		);
		# if there was no session or the last session was too old start a new one.
		# the previous session is "too old" if more than X mins has gone by since the previous session ended.
		# This grace period allows players to be disconnected between maps and still have a single session.
		if (!$id or ($self->{firsttime} - $end > 60*15)) {
			$session->{dataid} = $id = $db->next_id($db->{t_plr_sessions}, 'dataid');	# update $id too
			$session->{plrid} = $self->{plrid};
			$session->{sessionstart} = $start = $self->{firsttime};				# update $start too
		}
		$session->{sessionend} = $self->{basic}{lasttime};
		$session->{skill} = $self->skill;

		# if the session length is negative we assume DST "fall back" has occured and compensate.
		# the end of the session will not actually be accurate, but it will be sufficient.
		if ($session->{sessionend} - $start < 0) {
			$session->{sessionend} += 3600;
		}

		# save the session (only if the session is more than 0 seconds)
		if ($session->{sessionend} - $start > 0) {
			$db->save_stats($db->{t_plr_sessions}, { %$session, %{$self->{basic}} }, $TYPES_PLRSESSIONS, [ dataid => $id ] );
			# remove old sessions
			my $numsessions = $db->count($db->{t_plr_sessions}, [ plrid => $self->{plrid} ]);
			if ($numsessions > $self->{conf_plr_sessions_max}) {
				my @del = $db->get_list(
					"SELECT dataid FROM $db->{t_plr_sessions}" . 
					" WHERE plrid=$self->{plrid}" . 
					" ORDER BY sessionstart" .		# oldest first
					" LIMIT " . ($numsessions - $self->{conf_plr_sessions_max})
				);
				if (@del) {
					$db->query("DELETE FROM $db->{t_plr_sessions} WHERE dataid IN (" . join(',', @del) . ")");
				}
			}
		}
	}

	$self->{save_history} = (diffdays_ymd(POSIX::strftime("%Y-%m-%d", localtime), $self->{statdate}) <= $self->{conf_maxdays});
#	print "$self->{statdate} = " . POSIX::strftime("%Y-%m-%d", localtime) . " == " . diffdays_ymd(POSIX::strftime("%Y-%m-%d", localtime), $self->{statdate}) . "\n";


	# save basic+mod compiled player stats
	$db->save_stats( $db->{c_plr_data}, { %{$self->{basic} || {}}, %{$self->{mod} || {}} }, $self->get_types, 
		[ plrid => $self->{plrid} ], $self->{statdate});

	# the 'dayskill' and 'dayrank' are explictly added to the saved data (but not to the compiled data above)
	if ($self->{save_history}) {
		$dataid = $db->save_stats($db->{t_plr_data}, 
			{ dayskill => $self->skill, dayrank => $self->rank, %{$self->{basic}} }, $TYPES, 
			[ plrid => $self->{plrid}, statdate => $self->{statdate} ]);
	}
	$self->{basic} = {};

	# ROLES ARE SAVED FROM THE MOD OBJECT ...

	# save player weapons
	while (my($id,$data) = each %{$self->{weapons}}) {
		$self->save_weapon($id, $data);
	}
	$self->{weapons} = {};

	# save player victims
	if ($self->{conf_plr_save_victims}) {
		while (my($id,$data) = each %{$self->{victims}}) {
			$self->save_victim($id, $data);
		}
	}
	$self->{victims} = {};

	# save player maps
	while (my($id,$data) = each %{$self->{maps}}) {
		$self->save_map($id, $data);
	}
	$self->{maps} = {};

	$db->commit unless $nocommit;

	return $dataid;		# return the ID of the 'basic' data that was saved
}

sub save_weapon {
	my ($self, $id, $data) = @_;
	my $dataid;
	$self->{db}->save_stats( $self->{db}->{c_plr_weapons}, $data, $TYPES_WEAPONS, 
		[ plrid => $self->{plrid}, weaponid => $id ], $self->{statdate});
	if ($self->{save_history}) {
		$dataid = $self->{db}->save_stats( $self->{db}->{t_plr_weapons}, $data, $TYPES_WEAPONS, [ plrid => $self->{plrid}, weaponid => $id, statdate => $self->{statdate} ]);
	}
	return $dataid;
}

sub save_victim {
	my ($self, $id, $data) = @_;
	my $dataid;
	$self->{db}->save_stats( $self->{db}->{c_plr_victims}, $data, $TYPES_VICTIMS, 
		[ plrid => $self->{plrid}, victimid => $id ], $self->{statdate});
	if ($self->{save_history}) {
		$dataid = $self->{db}->save_stats( $self->{db}->{t_plr_victims},  $data, $TYPES_VICTIMS, [ plrid => $self->{plrid}, victimid => $id, statdate => $self->{statdate} ]);
	}
	return $dataid;
}

sub save_map {
	my ($self, $id, $data) = @_;
	my $dataid;
	$self->{db}->save_stats( $self->{db}->{c_plr_maps}, { %{$data || {}}, %{$self->{mod_maps}{$id} || {}} }, 
		$self->get_types_maps, [ plrid => $self->{plrid}, mapid => $id ], $self->{statdate});
	if ($self->{save_history}) {
		$dataid = $self->{db}->save_stats($self->{db}->{t_plr_maps},  $data, $TYPES_MAPS, [ plrid => $self->{plrid}, mapid => $id, statdate => $self->{statdate} ]);
	}
	return $dataid;
}

# returns true if the player is a bot
sub is_bot { 0 }

# returns true if the gametype:modtype has extra mod tables
sub has_mod_tables { 0 }

# returns the result: 0.0, 0.5, 1.0 if the player is stronger than another
# 0 = weaker, 0.5 = even, 1 = stronger
sub winresult {
	return 1 if $_[0]->{pq} > $_[1];
	return 0 if $_[0]->{pq} < $_[1];
	return 0.5;
}

1;

