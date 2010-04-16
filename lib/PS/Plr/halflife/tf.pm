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
package PS::Plr::halflife::tf;

use strict;
use warnings;
use base qw( PS::Plr::halflife );
use PS::SourceFilter;

BEGIN {
	my ($fields, $history);
	
	$fields = __PACKAGE__->SUPER::FIELDS('DATA');
	%{$fields->{halflife_tf}} = (
		(map { $_ => '+' } qw(
			assisted_kills
			domination			revenge
			custom_kills			custom_deaths
			backstab_kills			backstab_deaths
			team_kills			team_deaths
			red_kills			red_deaths
			blue_kills			blue_deaths
			killed_red			deathsby_red
			killed_blue 			deathsby_blue 
			joined_red			joined_blue
			wins				losses
			red_wins			blue_wins
			red_losses			blue_losses
			destroyed_objects		built_objects
			destroyed_dispenser		built_dispenser
			destroyed_sentrygun		built_sentrygun
			destroyed_attachment_sapper 	built_attachment_sapper
			destroyed_teleporter_entrance 	built_teleporter_entrance
			destroyed_teleporter_exit	built_teleporter_exit
			red_flag_captured		red_flag_defended
			blue_flag_captured		blue_flag_defended
			flag_captured			flag_defended
			flag_dropped			flag_pickedup
			point_captured			blocked_capture
			uber_charge
		 ))
	);
	$history = __PACKAGE__->SUPER::HISTORY('DATA');
	%$history = (
		%$history, 
		(map { $_ => $fields->{halflife_tf}{$_} } qw(
			assisted_kills		backstab_kills
			domination		revenge
			team_kills
		 ))
	);
	
	$fields = __PACKAGE__->SUPER::FIELDS('MAPS');
	%{$fields->{halflife_tf}} = (
		# use the same fields as 'DATA'
		%{__PACKAGE__->SUPER::FIELDS('DATA')->{halflife_tf}},
		(map { $_ => '+' } qw(
		 ))
	);

	$fields = __PACKAGE__->SUPER::FIELDS('ROLES');
	%{$fields->{halflife_tf}} = (
		(map { $_ => '+' } qw(
			assisted_kills
			domination			revenge
			custom_kills			custom_deaths
			backstab_kills			backstab_deaths
			headshot_kills			headshot_deaths
			flag_captured			flag_defended
			flag_dropped			flag_pickedup
			point_captured			blocked_capture
		 ))
	);

	$fields = __PACKAGE__->SUPER::FIELDS('SESSIONS');
	%{$fields->{halflife_tf}} = (
		(map { $_ => '+' } qw(
			assisted_kills
			domination			revenge
			custom_kills			custom_deaths
			backstab_kills			backstab_deaths
			red_wins			blue_wins
			red_losses			blue_losses
			flag_captured			flag_defended
			flag_dropped			flag_pickedup
			point_captured			blocked_capture
		 ))
	);

	$fields = __PACKAGE__->SUPER::FIELDS('WEAPONS');
	%{$fields->{halflife_tf}} = (
		(map { $_ => '+' } qw(
			custom_kills			custom_deaths
			backstab_kills			backstab_deaths
			team_kills			team_deaths
			destroyed_objects
			destroyed_dispenser
			destroyed_sentrygun
			destroyed_attachment_sapper
			destroyed_teleporter_entrance
			destroyed_teleporter_exit
		 ))
	);

	$fields = __PACKAGE__->SUPER::FIELDS('VICTIMS');
	%{$fields->{halflife_tf}} = (
		(map { $_ => '+' } qw(
			assisted_kills
			domination			revenge
			custom_kills			custom_deaths
			backstab_kills			backstab_deaths
			team_kills			team_deaths
			destroyed_objects
			destroyed_dispenser
			destroyed_sentrygun
			destroyed_attachment_sapper
			destroyed_teleporter_entrance
			destroyed_teleporter_exit
		))
	);
}

our $VERSION = '4.00.' . (('$Rev$' =~ /(\d+)/)[0] || '000');

#sub new {
#	my $proto = shift;
#	my $signature = shift;			# Dennis<123><STEAMID|BOT><TEAM>
#	my $timestamp = shift || time;		# timestamp when plr was seen (game time)
#	my $class = ref($proto) || $proto;
#	my $self = {
#		gametype	=> 'halflife',
#		modtype		=> 'tf',
#		timestamp	=> $timestamp,	# player timestamp
#	};
#
#	bless($self, $class);
#	return $self->init($signature);
#}

# A player blocked the capture of a control point
sub action_blocked_capture {
	my ($self, $game, $map, $props) = @_;
	my $m = $map->id;

	$self->{data}{blocked_capture}++;
	$self->{maps}{$m}{blocked_capture}++;
}

# A player captured a point on the map.
sub action_captured_point {
	my ($self, $game, $map, $props) = @_;
	my $role = $game->get_role($self->role, $self->team);
	my $m = $map->id;
	my $r = $role ? $role->id : undef;
	$self->timestamp($props->{timestamp});

	$self->{data}{point_captured}++;
	$self->{maps}{$m}{point_captured}++;
	$self->{roles}{$r}{point_captured}++ if $r;
}

# A player created an object (sentry guns, dispensers, etc)
sub action_created_object {
	my ($self, $game, $object, $map, $props) = @_;
	my $m = $map->id;
	my @vars = ( 'built_' . $object, 'built_objects' );
	$self->timestamp($props->{timestamp});

	foreach (@vars) {
		$self->{data}{$_}++;
		$self->{maps}{$m}{$_}++;
	}
}

# override the death action to capture 'customkill' events
sub action_death {
	my $self = shift;
	my ($game, $killer, $weapon, $map, $props) = @_;

	# allow parent to do its thing...
	$self->SUPER::action_death(@_);
	
	# if there is no custom kill property then we're done.
	return unless exists $props->{customkill};
	
	my $w = $weapon->id;
	my $m = $map->id;
	my $k = $killer->id;
	my $role = $game->get_role($self->role, $self->team);
	my $r = $role ? $role->id : undef;

	my @custom = ( 'custom_deaths', $props->{customkill} . '_deaths' );
	for (@custom) {
		$self->{data}{$_}++;
		$self->{maps}{$m}{$_}++;
		$self->{roles}{$r}{$_}++ if $r;
		$self->{victims}{$k}{$_}++;
		$self->{weapons}{$w}{$_}++;
	}
}

sub action_destroyed_object {
	my ($self, $game, $object, $owner, $weapon, $map, $props) = @_;
	my $m = $map->id;
	my $w = $weapon ? $weapon->id : undef;
	my $v = $owner ? $owner->id : undef;
	my @vars = ( 'destroyed_' . $object, 'destroyed_objects' );
	$self->timestamp($props->{timestamp});

	foreach (@vars) {
		$self->{data}{$_}++;
		$self->{maps}{$m}{$_}++;
		$self->{weapons}{$w}{$_}++ if $w;
		$self->{victims}{$v}{$_}++ if $v and $v != $self->id;
	}
}

# A player did something with a flag (captured, picked up, dropped, etc)
sub action_flag {
	my ($self, $game, $action, $map, $props) = @_;
	my $m = $map->id;
	$self->timestamp($props->{timestamp});

	$self->{data}{'flag_' . $action}++;
	$self->{data}{$self->team . '_flag_' . $action}++;

	$self->{maps}{$m}{'flag_' . $action}++;
	$self->{maps}{$m}{$self->team . '_flag_' . $action}++;
}

# override the kill action to capture 'customkill' events
sub action_kill {
	my $self = shift;
	my ($game, $victim, $weapon, $map, $props) = @_;
	
	# allow parent to do its thing...
	$self->SUPER::action_kill(@_);
	
	# if there is no custom kill property then we're done.
	return unless exists $props->{customkill};
	
	my $w = $weapon->id;
	my $m = $map->id;
	my $v = $victim->id;
	my $role = $game->get_role($self->role, $self->team);
	my $r = $role ? $role->id : undef;

	# track custom kills
	my @customs = ( 'custom_kills', $props->{customkill} . '_kills' );
	for (@customs) {
		$self->{data}{$_}++;
		$self->{maps}{$m}{$_}++;
		$self->{roles}{$r}{$_}++ if $r;
		$self->{victims}{$v}{$_}++;
		$self->{weapons}{$w}{$_}++;
	}
}

# A kill assist is counted essentially the same as a real kill except we
# increment the players 'assisted_kills' value as well. Skill is given to the
# assister from the event caller.
sub action_kill_assist {
	my ($self, $game, $victim, $weapon, $map, $props) = @_;
	my $kt = $self->team;
	my $vt = $victim->team;
	my $m = $map->id;
	my $w = $weapon->id;
	my $v = $victim->id;
	my $role = $game->get_role($self->role, $self->team);
	my $r = $role ? $role->id : undef;

	# Treat an assist the same as a regular kill
	$self->action_kill($game, $victim, $weapon, $map, $role, $props);
	;;; $self->debug7("$self kill assisted $victim with '$weapon'", 0);

	$self->{data}{assisted_kills}++;
	$self->{maps}{$m}{assisted_kills}++;
	$self->{roles}{$r}{assisted_kills}++ if $r;
	$self->{victims}{$v}{assisted_kills}++;
	$self->{weapons}{$w}{assisted_kills}++;

	# Track the kill streak for this player.
	# Since we're treating an assist the same as a kill, we'll allow the
	# kill streak to increment as if it were a normal kill.
	$self->end_streak('death_streak');
	$self->inc_streak('kill_streak');
}

# Handles domination, revenge, etc...
sub action_misc_plr {
	my ($self, $game, $trigger, $victim, $map, $props) = @_;
	my $role = $game->get_role($self->role, $self->team);
	my $m = $map ? $map->id : undef;
	my $r = $role ? $role->id : undef;
	my $v = $victim->id;
	
	$self->{data}{$trigger}++;
	$self->{maps}{$m}{$trigger}++;
	$self->{roles}{$r}{$trigger}++ if $r;
	$self->{victims}{$v}{$trigger}++;
}

# A player deployed their UberCharge (invul or 100% crits for 8 seconds)
sub action_ubercharge {
	my ($self, $game, $map, $props) = @_;
	my $m = $map->id;

	$self->{data}{uber_charge}++;
	$self->{maps}{$m}{uber_charge}++;
}


1;