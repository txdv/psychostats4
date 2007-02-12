package PS::Game::halflife::hldm;
#
#	HLDM is very basic and simply has kill events.
#	


use strict;
use warnings;
use base qw( PS::Game::halflife );

our $VERSION = '1.00';


sub _init { 
	my $self = shift;
	$self->SUPER::_init;
	$self->load_events(*DATA);
	$self->{conf}->load('game_halflife_hldm');

	return $self;
}

sub has_mod_tables { 0 }

1;

# event matching expressions go under the __DATA__ block
__DATA__

