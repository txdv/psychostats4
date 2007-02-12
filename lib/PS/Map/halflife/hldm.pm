package PS::Map::halflife::hldm;

use strict;
use warnings;
use base qw( PS::Map::halflife );

our $TYPES = {};

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

#	$db->save_stats($db->{t_map_data_mod}, $self->{mod}, $TYPES, [ dataid => $dataid ]);
#	$self->{mod} = {};

	return $dataid;
}

sub has_mod_tables { 0 }

1;
