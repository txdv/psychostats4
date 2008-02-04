package PS::Map::halflife::hldm;

use strict;
use warnings;
use base qw( PS::Map::halflife );

our $TYPES = {};

# override parent methods to combine types
sub get_types { return { %{$_[0]->SUPER::get_types}, %$TYPES } }

# allows the parent to determine our local types
sub mod_types { $TYPES };

sub has_mod_tables { 0 }

1;
