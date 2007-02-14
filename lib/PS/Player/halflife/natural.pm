package PS::Player::halflife::natural;

use strict;
use warnings;
use base qw( PS::Player::halflife );

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

sub get_types { return { %{$_[0]->SUPER::get_types}, %$TYPES } }
sub get_types_maps { return { %{$_[0]->SUPER::get_types_maps}, %$TYPES_MAPS } }

sub save {
	my $self = shift;
	my $db = $self->{db};
	my $dataid = $self->SUPER::save(@_) || return;

	$db->save_stats($db->{t_plr_data_mod}, $self->{mod}, $TYPES, [ dataid => $dataid ]);
	$self->{mod} = {};
	return $dataid;
}

sub save_map {
	my ($self, $id, $data) = @_;
	my $db = $self->{db};
	my $dataid = $self->SUPER::save_map($id, $data) || return;

	$db->save_stats($db->{t_plr_maps_mod}, $self->{mod_maps}{$id}, $TYPES_MAPS, [ dataid => $dataid ]);
	return $dataid;
}

sub has_mod_tables { 1 }

1;
