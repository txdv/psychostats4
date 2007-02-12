#
#	Game template. Replace all occurances of {template} with the name of this file w/o ".pm"
#
package PS::Game::halflife::{template};

use strict;
use warnings;
use base qw( PS::Game::halflife );

our $VERSION = '1.00';


sub _init { 
	my $self = shift;
	$self->SUPER::_init;
	$self->load_events(*DATA);
	$self->{conf}->load('game_halflife_{template}');

	$self->{plr_save_on_round} = ($self->{plr_save_on} eq 'round');

	return $self;
}

sub has_mod_tables { 1 }


1;

# event matching expressions do under the __DATA__ block
__DATA__

