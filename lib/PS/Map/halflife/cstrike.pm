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
package PS::Map::halflife::cstrike;

use strict;
use warnings;
use PS::SourceFilter;

# NOTE ------------------------------------------------------------------------
# NOTE: We don't subclass halflife here, since there's no need at the moment.
use base qw( PS::Map );
# NOTE ------------------------------------------------------------------------

our $VERSION = '4.00.' . (('$Rev$' =~ /(\d+)/)[0] || '000');

BEGIN {
	my $fields = __PACKAGE__->SUPER::FIELDS('DATA');
	%{$fields->{halflife_cstrike}} = (
		(map { $_ => '+' } qw(
			headshot_kills		team_kills
			killed_terrorist 	killed_ct 
			terrorist_kills 	ct_kills 
			joined_terrorist	joined_ct
			wins			losses
			terrorist_wins		ct_wins
			terrorist_losses	ct_losses
			hostages_killed		hostages_rescued 	hostages_touched
			bomb_planted		bomb_exploded
			bomb_defuse_attempts	bomb_defused
		 ))
			#vip_became		vip_killed		vip_escaped
	);
}

# A player did something with a bomb
sub action_bomb {
	my ($self, $game, $action, $plr, $props) = @_;
	$self->timestamp($props->{timestamp});

	$self->{data}{'bomb_' . $action}++;
}

# The player did something to a hostage (killed, touched, rescued)
sub action_hostage {
	my ($self, $game, $action, $plr, $props) = @_;
	my $var = $action . '_hostages';
	$self->timestamp($props->{timestamp});

	$self->{data}{$var}++;
}

sub action_vip {
	my ($self, $game, $action, $map, $props) = @_;
	my $m = $map->id;
	my $var = 'vip_' . $action;
	$self->timestamp($props->{timestamp});

	$self->{data}{$var}++;
}

1;
