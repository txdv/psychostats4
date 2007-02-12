package PS::Player::halflife;

use strict;
use warnings;
use base qw( PS::Player );

our $VERSION = '1.00';

# nothing new to add to the types yet, so just copy the original
our $BASIC_TYPES = $PS::Player::BASIC_TYPES;

# The init method must determine if the player exists in the database and gather their current info
# or create a new player in the database for the specified plrids
sub _init {
	my $self = shift;
	$self->SUPER::_init;

	$self->{_plrids_saved} = 0;

	return $self;
}

sub save {
	my $self = shift;
	$self->plrids unless $self->{_plrids_saved};
	return $self->SUPER::save(@_);
}

sub plrids {
	my $self = shift;
	$self->{_plrids_saved} = 1;
	$self->SUPER::plrids(@_);
}

# returns true if the player is considered a BOT
sub is_bot { substr($_[0]->worldid,0,3) eq 'BOT' }

# sets/gets the current signature. 
# This is used so the plr caching routines work in the Game subclasses to allow for faster player lookups.
#sub signature { 
#	my $self = shift;
#	return $self->{signature} unless scalar @_;
#	my $old = $self->{signature};
#	$self->{signature} = shift;
#	return $old;
#}

1;
