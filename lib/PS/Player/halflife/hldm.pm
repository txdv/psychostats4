# Halflife Deathmatch sucks and doesn't really have any extra stats
package PS::Player::halflife::hldm;

use strict;
use warnings;
use base qw( PS::Player );

our $TYPES = {};
our $TYPES_MAPS = { %$TYPES };

# override parent methods to combine types
sub get_types { return { %{$_[0]->SUPER::get_types}, %$TYPES } }
sub get_types_maps { return { %{$_[0]->SUPER::get_types_maps}, %$TYPES_MAPS } }

# allows the parent to determine our local types
sub mod_types { $TYPES };
sub mod_types_maps { $TYPES_MAPS };

1;
