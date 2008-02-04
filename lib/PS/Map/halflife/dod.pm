package PS::Map::halflife::dod;

use strict;
use warnings;
use base qw( PS::Map::halflife );

our $TYPES = {
	allieskills		=> '+',
	axiskills		=> '+',
	joinedallies		=> '+',
	joinedaxis		=> '+',
	allieswon		=> '+',
	allieswonpct		=> [ percent2 => qw( allieswon axiswon ) ],
	axiswon			=> '+',
	axiswonpct		=> [ percent2 => qw( axiswon allieswon ) ],
	allieslost		=> '+',
	axislost		=> '+',
#	tnt			=> '+',
#	tntused			=> '+',
	alliesflagscaptured	=> '+',
	alliesflagscapturedpct	=> [ percent => qw( alliesflagscaptured flagscaptured ) ],
	axisflagscaptured	=> '+',
	axisflagscapturedpct	=> [ percent => qw( axisflagscaptured flagscaptured ) ],
	flagscaptured		=> '+',

	alliesflagsblocked	=> '+',
	alliesflagsblockedpct	=> [ percent => qw( alliesflagsblocked flagsblocked ) ],
	axisflagsblocked	=> '+',
	axisflagsblockedpct	=> [ percent => qw( axisflagsblocked flagsblocked ) ],
	flagsblocked		=> '+',

	bombdefused		=> '+',
	bombplanted		=> '+',
	killedbombplanter	=> '+',
	alliesscore		=> '+',	
	alliesscorepct		=> [ percent2 => qw( alliesscore axisscore ) ],
	axisscore		=> '+',	
	axisscorepct		=> [ percent2 => qw( axisscore alliesscore ) ],

};

# override parent methods to combine types
sub get_types { return { %{$_[0]->SUPER::get_types}, %$TYPES } }

# allows the parent to determine our local types
sub mod_types { $TYPES };

sub has_mod_tables { 1 }

1;
