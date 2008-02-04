package PS::Role::halflife::tf2;

use strict;
use warnings;
use base qw( PS::Role::halflife );

our $TYPES = {
	assists			=> '+',
	dominations		=> '+',
	backstabkills		=> '+',
	backstabkillspct	=> [ percent => qw( backstabkills kills ) ],
	itemsbuilt		=> '+',
	itemsdestroyed		=> '+',
};

sub get_types { return { %{$_[0]->SUPER::get_types}, %$TYPES } }

sub _init {
	my $self = shift;
	$self->SUPER::_init(@_);

	$self->{mod} = {};

	return $self;
}


sub save {
	my $self = shift;
	my $db = $self->{db};
	my $dataid = $self->SUPER::save(@_) || return;

	$db->save_stats($db->{t_role_data_mod}, $self->{mod}, $TYPES, [ dataid => $dataid ]);
	$self->{mod} = {};

	return $dataid;
}

sub has_mod_tables { 1 }

1;
