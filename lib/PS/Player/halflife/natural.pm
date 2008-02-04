package PS::Player::halflife::natural;

use strict;
use warnings;
use base qw( PS::Player );

use PS::Role;

our $TYPES = {
	marinekills		=> '+',
	alienkills		=> '+',
	marinedeaths		=> '+',
	aliendeaths		=> '+',
	joinedmarine		=> '+',
	joinedalien		=> '+',
	joinedspectator		=> '+',
	alienwon		=> '+',
	alienwonpct		=> [ percent2 => qw( alienwon marinewon ) ],
	alienlost		=> '+',
	marinewon		=> '+',
	marinewonpct		=> [ percent2 => qw( marinewon alienwon ) ],
	marinelost		=> '+',
	commander		=> '+',
	commanderwon		=> '+',
	commanderwonpct		=> [ percent => qw( commanderwon commander ) ],
	votedown		=> '+',
	structuresbuilt		=> '+',
	structuresdestroyed	=> '+',
	structuresrecycled	=> '+',
};

# Player map stats are the same as the basic stats
our $TYPES_MAPS = { %$TYPES };

our $TYPES_ROLES = { 
	'plrid'		=> '=',
	%$PS::Role::TYPES 
};

# override parent methods to combine types
sub get_types { return { %{$_[0]->SUPER::get_types}, %$TYPES } }
sub get_types_maps { return { %{$_[0]->SUPER::get_types_maps}, %$TYPES_MAPS } }
sub get_types_roles { return { %{$_[0]->SUPER::get_types_roles}, %$TYPES_ROLES } }

# allows the parent to determine our local types
sub mod_types { $TYPES };
sub mod_types_maps { $TYPES_MAPS };
sub mod_types_roles { $TYPES_ROLES };

sub has_mod_tables { 1 }

sub has_roles { 1 }

1;
