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
package PS::Plr::halflife;

use strict;
use warnings;
use base qw( PS::Plr );
use PS::SourceFilter;

our $VERSION = '4.00.' . (('$Rev$' =~ /(\d+)/)[0] || '000');

BEGIN {
	my ($history, $fields);

	$fields = __PACKAGE__->SUPER::FIELDS('DATA');
	%{$fields->{halflife}} = (
		(map { $_ => '+' } qw(
			damage
			damage_absorbed
			damage_mitigated
			damage_taken
			hits 		shots
			hit_head	dmg_head
			hit_leftarm	dmg_leftarm
			hit_rightarm	dmg_rightarm
			hit_chest	dmg_chest
			hit_stomach	dmg_stomach
			hit_leftleg	dmg_leftleg
			hit_rightleg	dmg_rightleg
		 )),
		#latency 	=> '=',
		latency_sum	=> '+',
		latency_total	=> '+',
	);

	# Player map fields
	$fields = __PACKAGE__->SUPER::FIELDS('MAPS');
	%{$fields->{halflife}} = (
		%{__PACKAGE__->SUPER::FIELDS('DATA')->{halflife}},
		(map { $_ => '+' } qw(
		 ))
	);
	delete @{$fields->{halflife}}{qw( latency latency_sum latency_total )};
	delete @{$fields->{halflife}}{qw( hit_head hit_leftarm hit_rightarm hit_chest hit_stomach hit_leftleg hit_rightleg )};
	delete @{$fields->{halflife}}{qw( dmg_head dmg_leftarm dmg_rightarm dmg_chest dmg_stomach dmg_leftleg dmg_rightleg )};
	
	# Player role fields
	$fields = __PACKAGE__->SUPER::FIELDS('ROLES');
	%{$fields->{halflife}} = (
		(map { $_ => '+' } qw(
		 ))
	);

	$fields = __PACKAGE__->SUPER::FIELDS('SESSIONS');
	%{$fields->{halflife}} = (
		(map { $_ => '+' } qw(
			damage
			damage_absorbed
			damage_mitigated
			damage_taken
			hits shots
		)),
	);
	
	# Player weapon fields
	$fields = __PACKAGE__->SUPER::FIELDS('WEAPONS');
	%{$fields->{halflife}} = (
		%{__PACKAGE__->SUPER::FIELDS('DATA')->{halflife}},
		(map { $_ => '+' } qw(
		 ))
	);
	delete @{$fields->{halflife}}{qw( latency latency_sum latency_total )};
	
	# Player victim fields
	$fields = __PACKAGE__->SUPER::FIELDS('VICTIMS');
	%{$fields->{halflife}} = (
		%{__PACKAGE__->SUPER::FIELDS('DATA')->{halflife}},
		(map { $_ => '+' } qw(
		 ))
	);
	delete @{$fields->{halflife}}{qw( latency latency_sum latency_total )};
}

#sub new {
#	my $proto = shift;
#	my $signature = shift;			# Dennis<123><STEAMID|BOT><TEAM>
#	my $timestamp = shift || time;		# timestamp when plr was seen (game time)
#	my $class = ref($proto) || $proto;
#	my $self = {
#		gametype	=> 'halflife',
#		modtype		=> '',
#		timestamp	=> $timestamp,	# player timestamp
#	};
#
#	bless($self, $class);
#	return $self->init($signature);
#}

# Assign a plrid to the player, watch out for STEAM_ID_PENDING or STEAM_ID_LAN
# and do not allow them to be used.
sub assign_plrid {
	my ($self, $check_only) = @_;
	my $uniqueid = $self->{ $self->conf->uniqueid };
	if ($self->conf->uniqueid eq 'guid' and
	    ($uniqueid eq 'STEAM_ID_PENDING' or $uniqueid eq 'STEAM_ID_LAN')) {
		;;; $self->debug4("Delaying PLRID assignment for $self",0);
		return 0;
	}
	return $self->SUPER::assign_plrid($check_only);
}

# save plr_ids' but do not save STEAM_ID_PENDING or STEAM_ID_LAN as GUID's
sub save_plr_id {
	my $self = shift;	# 0=$type, 1=$id, 2=@$set
	return if $_[0] eq 'guid' and
		($_[1] eq 'STEAM_ID_PENDING' or $_[1] eq 'STEAM_ID_LAN');
	$self->SUPER::save_plr_id(@_);
}

sub signature {
	my ($self) = @_;
	my $steamid = $self->{guid};
	if (substr($steamid,0,4) eq 'BOT_') {
		$steamid = "BOT";
	} else {
		# PS::Game::halflife::get_plr() strips the "STEAM_X:" prefix off
		$steamid = "STEAM_0:" . $steamid;
	}
	return sprintf("%s<%s><%s><%s>",
		$self->{name},
		$self->{uid},
		$steamid,
		uc $self->{team}
	);
}

# attacked events are game particular... so override it here for halflife
sub action_attacked {
	my ($self, $game, $victim, $weapon, $map, $props) = @_;
	my $w = $weapon->id;
	my $v = $victim->id;
	my $dmg = int($props->{damage}) || 0;
	my $absorbed = int($props->{damage_armor}) || 0;
	my $health = int($props->{health}) || 0;
	$self->timestamp($props->{timestamp});
	#;;; $self->debug3("$self attacked $victim for $dmg dmg with $weapon ($absorbed absorbed)", 0);

	$self->{data}{hits}++;
	$self->{data}{shots}++;
	$self->{data}{damage} += $dmg;
	$self->{data}{damage_absorbed} += $absorbed;

	$self->{victims}{$v}{hits}++;
	$self->{victims}{$v}{shots}++;
	$self->{victims}{$v}{damage} += $dmg;
	$self->{victims}{$v}{damage_absorbed} += $absorbed;

	$self->{weapons}{$w}{hits}++;
	$self->{weapons}{$w}{shots}++;
	$self->{weapons}{$w}{damage} += $dmg;
	$self->{weapons}{$w}{damage_absorbed} += $absorbed;

	# HL2 records the hitbox information on attacked events
	if ($props->{hitgroup}) {
		my $hit_loc = 'hit_' . $props->{hitgroup};
		my $dmg_loc = 'dmg_' . $props->{hitgroup};

		$self->{data}{$hit_loc}++;
		$self->{data}{$dmg_loc} += $dmg;

		$self->{victims}{$v}{$hit_loc}++;
		$self->{victims}{$v}{$dmg_loc} += $dmg;

		$self->{weapons}{$w}{$hit_loc}++;
		$self->{weapons}{$w}{$dmg_loc} += $dmg;
	}

	# if the player still has some health left and its < 10 then count
	# this as a "close call" for the victim. They just barely survived.
	#if ($health and $health <= 10) {
	#	$victim->{data}{almost_died}++;
	#	# note: another good stat would be to keep track of who the
	#	# attacker was and if the victim kills the attacker then track
	#	# a "revenge" stat. Which would be done in action_kill.
	#	#$victim->track('almost_killed_me', $self);
	#}
}

# Odd-ball actions (from 3rd party plugins)
sub action_misc {
	my ($self, $game, $action, $props) = @_;
	if ($action eq 'time') {
		
	} elsif ($action eq 'latency') {
		# Should the last known latency be recorded, or keep a running
		# sum/total to calculate the average for the player?
		no warnings;
		#$self->{data}{latency} = int $props->{latency};
		$self->{data}{latency_sum} += int $props->{ping};
		$self->{data}{latency_total}++;
	}
}

# assign 'weaponstats' and 'weaponstats2' stats
sub action_weaponstats {
	my ($self, $game, $trigger, $weapon, $props) = @_;
	my $w = $weapon->id;

	if ($trigger eq 'weaponstats') {
		for (qw( hits shots damage headshots )) {
			no warnings;
			my $int = int(exists $props->{$_} ? $props->{$_} : 0);
			$self->{data}{$_} += $int;
			$self->{weapons}{$w}{$_} += $int;
		}
	} else {
		for (qw( head chest stomach leftarm rightarm leftleg rightleg )) {
			no warnings;
			my $int = int(exists $props->{$_} ? $props->{$_} : 0);
			$self->{data}{'hit_' . $_} += $int;
			$self->{weapons}{$w}{'hit_' . $_} += $int;
		}
	}
}

1;