package PS::Player::halflife::cstrike;

use strict;
use warnings;
use base qw( PS::Player );

our $TYPES = {
	ctkills			=> '+',
	terroristkills		=> '+',
	ctdeaths		=> '+',
	terroristdeaths		=> '+',
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
	bombspawned		=> '+',
	bombrunner		=> '+',
	bombrunnerpct		=> [ percent => qw( bombrunner rounds ) ],
	killedhostages		=> '+',
	touchedhostages		=> '+',
	rescuedhostages		=> '+',
	rescuedhostagespct	=> [ percent => qw( rescuedhostages touchedhostages ) ],
	vip			=> '+',
	vipescaped		=> '+',
	vipkilled		=> '+',
	ctwon			=> '+',
	ctwonpct		=> [ percent2 => qw( ctwon terroristwon ) ],
	ctlost			=> '+',
	terroristwon		=> '+',
	terroristwonpct		=> [ percent2 => qw( terroristwon ctwon ) ],
	terroristlost		=> '+',
};

# Player map stats are the same as the basic stats
our $TYPES_MAPS = { %$TYPES };

# override parent methods to combine types
sub get_types { return { %{$_[0]->SUPER::get_types}, %$TYPES } }
sub get_types_maps { return { %{$_[0]->SUPER::get_types_maps}, %$TYPES_MAPS } }

# allows the parent to determine our local types
sub mod_types { $TYPES };
sub mod_types_maps { $TYPES_MAPS };

sub has_mod_tables { 1 }

1;
