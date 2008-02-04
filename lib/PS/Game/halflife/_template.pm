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

	# do special initializion here. Most mods won't need to do anything.

	return $self;
}

sub has_mod_tables { 0 }
sub has_roles { 0 }
sub has_mod_roles { 0 }

# event functions go here ...
# replace the function below with an actual event function

sub event_eventname {
	my ($self, $timestamp, $args) = @_;
	my ($match1, $match2, $match3) = @$args;
}

1;
