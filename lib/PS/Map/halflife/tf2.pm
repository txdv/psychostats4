package PS::Map::halflife::tf2;

use strict;
use warnings;
use base qw( PS::Map::halflife );

our $TYPES = {
	redkills		=> '+',
	bluekills		=> '+',

	redwon			=> '+',
	redwonpct		=> [ percent2 => qw( redwon bluewon ) ],
	bluewon			=> '+',
	bluewonpct		=> [ percent2 => qw( bluewon redwon ) ],
	redlost			=> '+',
	bluelost		=> '+',

	assists			=> '+',
	redassists		=> '+',
	blueassists		=> '+',

	flagscaptured		=> '+',
	flagsdefended		=> '+',
#	flagsdropped		=> '+',
	captureblocked		=> '+', 
	pointcaptured		=> '+',

	redflagscaptured	=> '+',
	redflagscapturedpct	=> [ percent => qw( redflagscaptured flagscaptured ) ],
	redflagsdefended	=> '+',
	redflagsdefendedpct	=> [ percent => qw( redflagsdefended flagsdefended ) ],
	redcaptureblocked	=> '+',
	redpointcaptured	=> '+',
	redflagsdropped		=> '+',
	redflagspickedup	=> '+',

	bluecaptureblocked	=> '+',
	bluepointcaptured	=> '+',
	blueflagsdefended	=> '+',
	blueflagsdropped	=> '+',
	blueflagspickedup	=> '+',
	blueflagscaptured	=> '+',
	blueflagscapturedpct	=> [ percent => qw( blueflagscaptured flagscaptured ) ],

	itemsdestroyed		=> '+',
	dispenserdestroy	=> '+',
	sentrydestroy		=> '+',
	sapperdestroy		=> '+',
	teleporterdestroy	=> '+',

	dominations		=> '+',
	backstabkills		=> '+',

	joinedred		=> '+',
	joinedblue		=> '+',
};

# override parent methods to combine types
sub get_types { return { %{$_[0]->SUPER::get_types}, %$TYPES } }

# allows the parent to determine our local types
sub mod_types { $TYPES };

sub has_mod_tables { 1 }

1;
