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
package PS::Plr::halflife::cstrike;

use strict;
use warnings;
use base qw( PS::Plr::halflife );
use PS::SourceFilter;

BEGIN {
	my $fields;
	
	$fields = __PACKAGE__->SUPER::FIELDS('DATA');
	%{$fields->{halflife_cstrike}} = (
		(map { $_ => '+' } qw(
			team_kills		team_deaths
			terrorist_kills		terrorist_deaths
			ct_kills		ct_deaths
			killed_terrorist	deathsby_terrorist
			killed_ct 		deathsby_ct 
			joined_terrorist	joined_ct
			wins			losses
			terrorist_wins		ct_wins
			terrorist_losses	ct_losses
			hostages_killed		hostages_rescued 	hostages_touched
			bomb_planted		bomb_exploded
			bomb_defuse_attempts	bomb_defused
		 ))
			# these are virtually worthless these days, so lets not
			# waste space by adding them to the database.
			#vip_became		vip_killed		vip_escaped
	);

	$fields = __PACKAGE__->SUPER::FIELDS('MAPS');
	%{$fields->{halflife_cstrike}} = (
		# use the same fields as 'DATA'
		%{__PACKAGE__->SUPER::FIELDS('DATA')->{halflife_cstrike}},
		(map { $_ => '+' } qw(
		 ))
	);

	#$fields = __PACKAGE__->SUPER::FIELDS('ROLES');
	#$fields = { data => {} };
	#%{$fields->{halflife_cstrike}} = (
	#	(map { $_ => '+' } qw(
	#	 ))
	#);

	$fields = __PACKAGE__->SUPER::FIELDS('SESSIONS');
	%{$fields->{halflife_cstrike}} = (
		(map { $_ => '+' } qw(
			terrorist_wins		ct_wins
			terrorist_losses	ct_losses
		 ))
	);

	$fields = __PACKAGE__->SUPER::FIELDS('WEAPONS');
	%{$fields->{halflife_cstrike}} = (
		(map { $_ => '+' } qw(
			team_kills		team_deaths
		 ))
	);

	$fields = __PACKAGE__->SUPER::FIELDS('VICTIMS');
	%{$fields->{halflife_cstrike}} = (
		(map { $_ => '+' } qw(
			team_kills		team_deaths
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
#		modtype		=> 'cstrike',
#		timestamp	=> $timestamp,	# player timestamp
#	};
#
#	bless($self, $class);
#	return $self->init($signature);
#}

sub action_bomb {
	my ($self, $game, $action, $map, $props) = @_;
	my $m = $map->id;
	my $var = 'bomb_' . $action;
	$self->timestamp($props->{timestamp});

	$self->{data}{$var}++;
	$self->{maps}{$m}{$var}++;
}


# The player did something to a hostage (killed, touched, rescued)
sub action_hostage {
	my ($self, $game, $action, $map, $props) = @_;
	my $m = $map->id;
	my $var = 'hostages_' . $action;
	#warn "$self $action a hostage!!\n";
	$self->timestamp($props->{timestamp});
	
	$self->{data}{$var}++;
	$self->{maps}{$m}{$var}++;
}

sub action_vip {
	my ($self, $game, $action, $map, $props) = @_;
	my $m = $map->id;
	my $var = 'vip_' . $action;
	$self->timestamp($props->{timestamp});

	$self->{data}{$var}++;
	$self->{maps}{$m}{$var}++;
}

1;