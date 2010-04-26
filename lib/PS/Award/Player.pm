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
package PS::Award::Player;

use base qw( PS::Award );
use strict;
use warnings;

our $VERSION = '1.00.' . (('$Rev$' =~ /(\d+)/)[0] || '000');

sub _init {
	my ($self) = @_;
	$self = $self->SUPER::_init;
	
	# add vars from the player tables ...
	$self->add_vars_from_table($self->{db}{t_plr_data});
	if ($self->{gametype} and $self->{modtype}) {
		$self->add_vars_from_table(
			$self->{db}{t_plr_data} . '_' .
			$self->{gametype} . '_' .
			$self->{modtype}
		);
	}
	
	# add some extra vars useful for player stats
	$self->add_vars(
		kills_streak 	=> undef,
		deaths_streak 	=> undef,
		avg_rank	=> 'AVG(rank)',
		avg_skill	=> 'AVG(skill)',
	);
	
	return $self;
}



1;
