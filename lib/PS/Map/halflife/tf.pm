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
			team_kills		
			killed_red 		killed_blue 
			red_kills 		blue_kills 
			joined_red		joined_blue
			red_wins		blue_wins
			red_losses		blue_losses
		 ))
	);
}

1;
