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
package PS::Role::halflife;

use strict;
use warnings;
use base qw( PS::Role );

our $VERSION = '4.00.' . (('$Rev$' =~ /(\d+)/)[0] || '000');

BEGIN {
	#my $fields = __PACKAGE__->SUPER::FIELDS('DATA');
	#%{$fields->{halflife}} = (
	#	(map { $_ => '+' } qw(
	#		team_kills
	#		damage
	#		damage_absorbed
	#		hits shots
	#		hit_head	dmg_head
	#		hit_leftarm	dmg_leftarm
	#		hit_rightarm	dmg_rightarm
	#		hit_chest	dmg_chest
	#		hit_stomach	dmg_stomach
	#		hit_leftleg	dmg_leftleg
	#		hit_rightleg	dmg_rightleg
	#	 ))
	#);
}

1;
