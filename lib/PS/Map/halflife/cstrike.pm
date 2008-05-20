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
use base qw( PS::Map::halflife );

our $VERSION = '1.00.' . (('$Rev$' =~ /(\d+)/)[0] || '000');

our $TYPES = {
	ctkills			=> '+',
	terroristkills		=> '+',
	joinedct		=> '+',
	joinedterrorist		=> '+',
	joinedspectator		=> '+',
	bombdefuseattempts	=> '+',
	bombdefused		=> '+',
	bombdefusedpct		=> [ percent => qw( bombdefused bombdefuseattempts ) ],
	bombplanted		=> '+',
	bombplantedpct		=> [ percent => qw( bombplanted rounds ) ],
	bombexploded		=> '+',
	bombexplodedpct		=> [ percent => qw( bombexploded bombplanted ) ],
	bombrunner		=> '+',
	bombrunnerpct		=> [ percent => qw( bombrunner rounds ) ],
	killedhostages		=> '+',
	rescuedhostages		=> '+',
	rescuedhostagespct	=> [ percent => qw( rescuedhostages touchedhostages ) ],
	touchedhostages		=> '+',
	vipescaped		=> '+',
	vipkilled		=> '+',
	ctwon			=> '+',
	ctwonpct		=> [ percent2 => qw( ctwon terroristwon ) ],
	ctlost			=> '+',
	terroristwon		=> '+',
	terroristwonpct		=> [ percent2 => qw( terroristwon ctwon ) ],
	terroristlost		=> '+',
};

# override parent methods to combine types
sub get_types { return { %{$_[0]->SUPER::get_types}, %$TYPES } }

# allows the parent to determine our local types
sub mod_types { $TYPES };

sub has_mod_tables { 1 }

1;
