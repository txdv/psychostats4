package PS::Map;

use strict;
use warnings;
use base qw( PS::Debug );
use POSIX;
use util qw( :date );

our $VERSION = '1.00';
our $BASECLASS = undef;

our $GAMETYPE = '';
our $MODTYPE = '';

our $TYPES = {
	dataid		=> '=', 
	mapid		=> '=',
	statdate	=> '=',
	games		=> '+',
	rounds		=> '+',
	kills		=> '+',
	suicides	=> '+',
	ffkills		=> '+',
	ffkillspct	=> [ percent => qw( ffkills kills ) ],
	connections	=> '+',
	onlinetime	=> '+',	
	lasttime	=> '>',
};

sub new {
	my ($proto, $mapname, $conf, $db) = @_;
	my $baseclass = ref($proto) || $proto;
	my $self = { debug => 0, class => undef, mapname => $mapname, conf => $conf, db => $db };
	my $class;

	# determine what kind of player we're going to be using the first time we're created
	if (!$BASECLASS) {
		$GAMETYPE = $conf->get('gametype');
		$MODTYPE = $conf->get('modtype');

		my @ary = ($MODTYPE) ? ($MODTYPE, $GAMETYPE) : ($GAMETYPE);
		while (@ary) {
			$class = join("::", $baseclass, reverse(@ary));
			eval "require $class";
			if ($@) {
				if ($@ !~ /^Can't locate/i) {
					$::ERR->warn("Compile error in class $class:\n$@\n");
					return undef;
				} 
				undef $class;
				shift @ary;
			} else {
				last;
			}
		}

		# still no class? create a basic PS::Map object and return that
		$class = $baseclass if !$class;

	} else {
		$class = $BASECLASS;
	}

	$self->{class} = $class;

	bless($self, $class);
#	$self->debug($self->{class} . " initializing");

	$self->_init;

	if (!$BASECLASS) {
		$self->_init_table;
		$BASECLASS = $class;
	}

	return $self;
}

# makes sure the compiled map data table is already setup
sub _init_table {
	my $self = shift;
	my $conf = $self->{conf};
	my $db = $self->{db};
	my $basetable = 'map_data';
	my $table = $db->ctbl($basetable);
	my $tail = '';
	my $fields = {};
	my @order = ();
	$tail .= "_$GAMETYPE" if $GAMETYPE;
	$tail .= "_$MODTYPE" if $GAMETYPE and $MODTYPE;
	return if $db->table_exists($table);

	# get all keys used in the 2 tables so we can combine them all into a single table
	$fields->{$_} = 'int' foreach keys %{$db->tableinfo($db->tbl($basetable))};
	if ($tail and $self->has_mod_tables) {
		$fields->{$_} = 'int' foreach keys %{$db->tableinfo($db->tbl($basetable . $tail))};
	}

	# remove unwanted/special keys
	delete @$fields{ qw( statdate ) };

	# add extra keys
	my $alltypes = $self->get_types;
	$fields->{$_} = 'date' foreach qw( firstdate lastdate );
	$fields->{$_} = 'uint' foreach qw( dataid mapid );	# unsigned
	$fields->{$_} = 'float' foreach grep { ref $alltypes->{$_} } keys %$alltypes;

	# build the full set of keys for the table
	@order = (qw( dataid mapid firstdate lastdate ), sort grep { !/^((data|map)id|(first|last)date)$/ } keys %$fields );

	$db->create($table, $fields, \@order);
	$db->create_primary_index($table, 'dataid');
	$db->create_unique_index($table, 'mapid');
	$self->info("Compiled table $table was initialized.");
}

sub _init {
	my $self = shift;

	return unless $self->{mapname};

	$self->{conf_maxdays} = $self->{conf}->get_main('maxdays');

	$self->{mapid} = $self->{db}->select($self->{db}->{t_map}, 'mapid', 
		"uniqueid=" . $self->{db}->quote($self->{mapname})
	);
	# map didn't exist so we have to create it
	if (!$self->{mapid}) {
		$self->{mapid} = $self->{db}->next_id($self->{db}->{t_map},'mapid');
		my $res = $self->{db}->insert($self->{db}->{t_map}, { 
			mapid => $self->{mapid},
			uniqueid => $self->{mapname},
		});
		$self->fatal("Error adding map to database: " . $self->{db}->errstr) unless $res;
	}
}

sub name { $_[0]->{mapname} }

sub statdate {
	return $_[0]->{statdate} if @_ == 1;
	my $self = shift;
	my ($d,$m,$y) = (localtime(shift))[3,4,5];
	$m++;
	$y += 1900;
	$self->{statdate} = sprintf("%04d-%02d-%02d",$y,$m,$d);
}

sub timerstart {
	my $self = shift;
	my $timestamp = shift;
	my $prevtime = 0;
#	no warnings;						# don't want any 'undef' or 'uninitialized' errors

	# a previous timer was already started, get it's elapsed value
	if ($self->{firsttime}) {
		$prevtime = $self->timer; #$self->{basic}{lasttime} - $self->{firsttime};
	}
	$self->{firsttime} = $self->{basic}{lasttime} = $timestamp;	# start new timer with current timestamp
	$self->statdate($timestamp) unless $self->statdate;		# set the statdate if it wasn't set already
	return $prevtime;
}

# return the total time that has passed since the timer was started
sub timer {
	my $self = shift;
	return 0 unless $self->{firsttime} and $self->{basic}{lasttime};
	my $t = $self->{basic}{lasttime} - $self->{firsttime};
	# If $t is negative then there's a chance that DST "fall back" just occured, so the timestamp is going to be -1 hour.
	# I try to compensate for this here by fooling the routines into thinking the time hasn't actually changed. this will
	# cause minor timing issues but the result is better then the player receiving NO time at all.
	if ($t < 0) {
		$t += 3600;	# add 1 hour
	}
	return $t > 0 ? $t : 0;
}

sub get_types { $TYPES }

sub save {
	my $self = shift;
	my $db = $self->{db};
	my $dataid;

	# save basic map stats ...
	$dataid = $db->save_stats( $db->{c_map_data}, { %{$self->{basic} || {}}, %{$self->{mod} || {}} }, $self->get_types, 
		[ mapid => $self->{mapid} ], $self->{statdate});

	if (diffdays_ymd(POSIX::strftime("%Y-%m-%d", localtime), $self->{statdate}) <= $self->{conf_maxdays}) {
		$dataid = $db->save_stats($db->{t_map_data}, $self->{basic}, $TYPES, 
			[ mapid => $self->{mapid}, statdate => $self->{statdate} ]
		);
	}
	$self->{basic} = {};

	return $dataid;
}

sub has_mod_tables { 0 }

1;
