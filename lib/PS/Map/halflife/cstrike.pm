package PS::Map::halflife::cstrike;

use strict;
use warnings;
use base qw( PS::Map::halflife );

our $TYPES = {
	ctkills			=> '+',
	terroristkills		=> '+',
	joinedct		=> '+',
	joinedterrorist		=> '+',
	joinedspectator		=> '+',
	bombdefuseattempts	=> '+',
	bombdefused		=> '+',
	bombdefusedpct		=> [ percent => qw( bombdefused bombdefuseattempts ) ],
	bombplanted		=> '+',
	bombplantedpct		=> [ percent => qw( bombplanted rounds ) ],
	bombexploded		=> '+',
	bombexplodedpct		=> [ percent => qw( bombexploded bombplanted ) ],
	bombrunner		=> '+',
	bombrunnerpct		=> [ percent => qw( bombrunner rounds ) ],
	killedhostages		=> '+',
	rescuedhostages		=> '+',
	rescuedhostagespct	=> [ percent => qw( rescuedhostages touchedhostages ) ],
	touchedhostages		=> '+',
	vipescaped		=> '+',
	vipkilled		=> '+',
	ctwon			=> '+',
	ctwonpct		=> [ percent2 => qw( ctwon terroristwon ) ],
	ctlost			=> '+',
	terroristwon		=> '+',
	terroristwonpct		=> [ percent2 => qw( terroristwon ctwon ) ],
	terroristlost		=> '+',
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
