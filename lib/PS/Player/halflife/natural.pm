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

our $TYPES_ROLES = { 
	'plrid'		=> '=',
	%$PS::Role::TYPES 
};

sub get_types { return { %{$_[0]->SUPER::get_types}, %$TYPES } }
sub get_types_maps { return { %{$_[0]->SUPER::get_types_maps}, %$TYPES_MAPS } }
sub get_types_roles { return { %{$_[0]->SUPER::get_types_roles}, %$TYPES_ROLES } }

sub _init_table_roles {
	my $self = shift;
	my $conf = $self->{conf};
	my $db = $self->{db};
	my $basetable = 'plr_roles';
	my $table = $db->ctbl($basetable);
	my $tail = '';
	my $fields = {};
	my @order = ();
	$tail .= "_$PS::Player::GAMETYPE" if $PS::Player::GAMETYPE;
	$tail .= "_$PS::Player::MODTYPE" if $PS::Player::MODTYPE;
	return if $db->table_exists($table);

	# get all keys used in the 2 tables so we can combine them all into a single table
	$fields->{$_} = 'int' foreach keys %{$db->tableinfo($db->tbl($basetable))};
# roles do not currently allow for game/modtype extensions
#	if ($tail) {
#		$fields->{$_} = 'int' foreach keys %{$db->tableinfo($basetable . $tail)};
#	}

	# remove unwanted/special keys
	delete @$fields{ qw( statdate firstdate lastdate ) };

	# add extra keys
	my $alltypes = $self->get_types_roles;
	$fields->{$_} = 'date' foreach qw( firstdate lastdate ); 
	$fields->{$_} = 'uint' foreach qw( dataid plrid roleid );	# unsigned
	$fields->{$_} = 'float' foreach grep { ref $alltypes->{$_} } keys %$alltypes;

	# build the full set of keys for the table
	@order = (qw( dataid plrid roleid firstdate lastdate ), sort grep { !/^((data|plr|role)id|(first|last)date)$/ } keys %$fields );

	$db->create($table, $fields, \@order);
	$db->create_primary_index($table, 'dataid');
#	$db->create_index($table, 'plrroles', 'plrid', 'roleid');
	$db->create_unique_index($table, 'plrroles', qw( plrid roleid ));
	$self->info("Compiled table $table was initialized.");
}

sub save {
	my $self = shift;
	my $db = $self->{db};
	my $dataid = $self->SUPER::save(@_) || return;

	$db->save_stats($db->{t_plr_data_mod}, $self->{mod}, $TYPES, [ dataid => $dataid ]);
	$self->{mod} = {};

	# save player roles
	while (my($id,$data) = each %{$self->{roles}}) {
		$self->save_role($id, $data);
	}
	$self->{roles} = {};

	return $dataid;
}

sub save_role {
	my ($self, $id, $data) = @_;
	my $dataid;
	$self->{db}->save_stats( $self->{db}->{c_plr_roles}, $data, $TYPES_ROLES, 
		[ plrid => $self->{plrid}, roleid => $id ], $self->{statdate});
	if ($self->{save_history}) {
		$dataid = $self->{db}->save_stats( $self->{db}->{t_plr_roles}, $data, $TYPES_ROLES, [ plrid => $self->{plrid}, roleid => $id, statdate => $self->{statdate} ]);
	}
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
