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
package PS::Map::halflife::tf;

use strict;
use warnings;

# NOTE ------------------------------------------------------------------------
# NOTE: We don't subclass halflife here, since there's no need at the moment.
use base qw( PS::Map );
# NOTE ------------------------------------------------------------------------

our $VERSION = '4.00.' . (('$Rev$' =~ /(\d+)/)[0] || '000');

BEGIN {
	my $fields = __PACKAGE__->SUPER::FIELDS('DATA');
	%{$fields->{halflife_tf}} = (
		(map { $_ => '+' } qw(
			team_kills			custom_kills
			headshot_kills			backstab_kills
			killed_red 			killed_blue 
			red_kills 			blue_kills 
			joined_red			joined_blue
			wins				losses
			red_wins			blue_wins
			red_losses			blue_losses
			flag_captured
			red_flag_captured		blue_flag_captured
			red_flag_defended		blue_flag_defended
			point_captured			blocked_capture
		 ))
	);
}

# A player blocked the capture of a control point
sub action_blocked_capture {
	my ($self, $game, $props) = @_;

	$self->{data}{blocked_capture}++;
}

# A player captured a point on the map.
sub action_captured_point {
	my ($self, $game, $props) = @_;
	$self->timestamp($props->{timestamp});

	$self->{data}{point_captured}++;
}

# A player did something with a flag (captured, picked up, dropped, etc)
sub action_flag {
	my ($self, $game, $action, $plr, $props) = @_;
	$self->timestamp($props->{timestamp});

	$self->{data}{'flag_' . $action}++;
	$self->{data}{$plr->team . '_flag_' . $action}++ if $plr;
}

# override the kill action to capture 'customkill' events
sub action_kill {
	my $self = shift;
	my ($game, $killer, $victim, $weapon, $props) = @_;
	
	# allow parent to do its thing...
	$self->SUPER::action_kill(@_);
	
	# if there is no custom kill property then we're done.
	return unless exists $props->{customkill};
	
	# track custom kills
	my @customs = ( 'custom_kills', $props->{customkill} . '_kills' );
	for (@customs) {
		$self->{data}{$_}++;
	}
}

1;
