package PS::Map::halflife::natural;

use strict;
use warnings;
use base qw( PS::Map::halflife );

our $TYPES = {
	marinekills		=> '+',
	alienkills		=> '+',
	joinedmarine		=> '+',
	joinedalien		=> '+',
	joinedspectator		=> '+',
	alienwon		=> '+',
	alienwonpct		=> [ percent2 => qw( alienwon marinewon ) ],
	alienlost		=> '+',
	marinewon		=> '+',
	marinewonpct		=> [ percent2 => qw( marinewon alienwon ) ],
	marinelost		=> '+',
	structuresbuilt		=> '+',
	structuresdestroyed	=> '+',
	structuresrecycled	=> '+',
};

sub _init {
	my $self = shift;
	$self->SUPER::_init(@_);

	$self->{mod} = {};

	return $self;
}

sub get_types { return { %{$_[0]->SUPER::get_types}, %$TYPES } }

sub save {
	my $self = shift;
	my $db = $self->{db};
	my $dataid = $self->SUPER::save(@_) || return;

	$db->save_stats($db->{t_map_data_mod}, $self->{mod}, $TYPES, [ dataid => $dataid ]);
	$self->{mod} = {};

	return $dataid;
}

sub has_mod_tables { 1 }

1;
