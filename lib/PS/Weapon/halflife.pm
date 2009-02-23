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
package PS::Weapon::halflife;

use strict;
use warnings;
use base qw( PS::Weapon );
use PS::SourceFilter;

our $VERSION = '4.00.' . (('$Rev$' =~ /(\d+)/)[0] || '000');

BEGIN {
	my $fields = __PACKAGE__->SUPER::FIELDS('DATA');
	%{$fields->{halflife}} = (
		(map { $_ => '+' } qw(
			team_kills
			damage
			damage_absorbed
			hits 		shots
			hit_head	dmg_head
			hit_leftarm	dmg_leftarm
			hit_rightarm	dmg_rightarm
			hit_chest	dmg_chest
			hit_stomach	dmg_stomach
			hit_leftleg	dmg_leftleg
			hit_rightleg	dmg_rightleg
		 ))
	);
}

# a player attacked someone
sub action_attacked {
	my ($self, $game, $killer, $victim, $map, $props) = @_;
	my $dmg = $props->{damage} || 0;
	my $absorbed = $props->{damage_armor} || 0;
	$self->timestamp($props->{timestamp});

	$self->{data}{hits}++;
	$self->{data}{shots}++;
	$self->{data}{damage} += $dmg;
	$self->{data}{damage_absorbed} += $absorbed;

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
	my ($self, $game, $killer, $victim, $map, $props) = @_;
	my $kt = $killer->team;
	my $vt = $victim->team;
	$self->timestamp($props->{timestamp});
	
	$self->{data}{kills}++;
	
	if (($kt and $vt) and ($kt eq $vt)) {
		$self->{data}{team_kills}++;
	}
}

# assign 'weaponstats' and 'weaponstats2' stats
sub action_weaponstats {
	my ($self, $game, $trigger, $player, $props) = @_;
	no warnings;

	if ($trigger eq 'weaponstats') {
		for (qw( hits shots damage headshots )) {
			$self->{data}{$_} += int(exists $props->{$_} ? $props->{$_} : 0);
		}
	} else { # $trigger eq 'weaponstats2'
		for (qw( head chest stomach leftarm rightarm leftleg rightleg )) {
			$self->{data}{'hit_' . $_} += int(exists $props->{$_} ? $props->{$_} : 0);
		}
	}
}

1;
