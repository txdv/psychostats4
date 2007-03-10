# Base Feeder class. This is a basic factory class that creates a Feeder object based on the current logsource.
# If a subclass is detected for the current gametype it will be created and returned.
# Order of class detection (first class to be found is used):
#	PS::Feeder::{gametype}::{modtype}::{base}
#	PS::Feeder::{gametype}::{base}
#	PS::Feeder::{base}
#
package PS::Feeder;

use strict;
use warnings;
use base qw( PS::Debug );
use util qw( :numbers );

our $VERSION = '1.00';

sub new {
	my $proto = shift;
	my $logsource = shift;
	my $game = shift;
	my $conf = shift;
	my $db = shift;
	my $baseclass = ref($proto) || $proto;
	my $self = { debug => 0, class => undef, logsource => $logsource, game => $game, conf => $conf, db => $db };

	my $class;
	my $gametype = $conf->get('gametype');
	my $modtype = $conf->get('modtype');
	my $base = "file";

	$base = $1 if $logsource =~ m|^([^:]+)://.+|;
	my @ary = ($modtype) ? ($modtype, $gametype) : ($gametype);
	while (@ary) {
		$class = join("::", $baseclass, reverse(@ary), $base);
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

	# Still no class? Then try a normal non-specific class
	if (!$class) {
		$class = join("::", $baseclass, $base);
		eval "require $class";
		if ($@) {
			if ($@ !~ /^Can't locate/) {
				$::ERR->warn("Compile error in class $class:\n$@\n");
			} 
			undef $class;
		}
	}

	# STILL nothing? -- We give up, nothing more to try ...
	if (!$class) {
		$::ERR->warn("No suitable Feeder class for '$base' found. Ignoring logsource.\n");
		return undef;
	}

	$self->{class} = $class;

	bless($self, $class);
#	$self->debug($self->{class} . " initializing");

	$self->{_verbose} = ($self->{conf}->get_opt('verbose') and !$self->{conf}->get_opt('quiet'));

	$self->{_lasttimebytes} = time;
	$self->{_lasttimelines} = time;
	$self->{_prevlines} = 0;
	$self->{_totallines} = 0;
	$self->{_totalbytes} = 0;
	$self->{_prevbytes} = 0;
	$self->{_state_saved} = 0;

	$self->_init;

	return $self;
}

sub bytes_per_second {
	my ($self, $tail) = @_;
	return undef unless defined $self->{_lasttimebytes};
	my $time_diff = time() - $self->{_lasttimebytes};
	my $byte_diff = $self->{_totalbytes} - $self->{_prevbytes};
	my $total = $time_diff ? sprintf("%.0f", $byte_diff / $time_diff) : $byte_diff;
# If you comment out the next 2 lines your 'per second' calculation will be based 
# on the entire length of time that has passed since we started...
#	$self->{_prevbytes} = $self->{_totalbytes};
#	$self->{_lasttimebytes} = time;
	return $tail ? abbrnum($total,0) . 'ps' : $total;
}

sub lines_per_second {
	my ($self, $tail) = @_;
	return undef unless defined $self->{_lasttimelines};
	my $time_diff = time() - $self->{_lasttimelines};
	my $line_diff = $self->{_totallines} - $self->{_prevlines};
	my $total = $time_diff ? sprintf("%.0f", $line_diff / $time_diff) : $line_diff;
#	$self->{_prevlines} = $self->{_totallines};
#	$self->{_lasttimelines} = time;
	return $tail ? abbrnum($total,0) . 'ps' : $total;
}

sub save_state {
	my $self = shift;
	my $state = shift || $self->{state} || {};
	my $db = $self->{db};
	delete($state->{plrs});

	return if (defined $self->{game}->{timestamp} and ($self->{_state_saved} == $self->{game}->{timestamp}));
#	$::ERR->verbose("Saving state");

	$db->begin;
	my $id = $state->{id} ? $state->{id} : $db->select($db->{t_state}, 'id', [ logsource => $self->{logsource} ]);
	$self->{db}->delete($db->{t_state}, [ logsource => $self->{logsource} ]);

	$state->{id} = $self->{db}->next_id($db->{t_state});
	$state->{logsource} = $self->{logsource}; # unless $state->{logsource};
	$state->{map} = $self->{game}->{curmap}; # unless $state->{map};
	$state->{timestamp} = $self->{game}->{timestamp}; # unless $state->{timestamp};
#	$state->{timestamp} = $self->{game}->{lasttimestamp};
	$state->{lastupdate} = time; # unless $state->{lastupdate};

	$self->{db}->insert($db->{t_state}, $state);

	$self->{db}->delete($db->{t_state_plrs}, [ id => $id ]) if $id;
	while (my ($uid, $p) = each %{$self->{game}->get_plr_list}) {
		my $set = {
			id	=> $state->{id},
			plrid 	=> $p->{plrid}, 
			uid	=> $uid,
			isdead	=> $p->{isdead} || 0,
			team	=> $p->{team} || '',
			role	=> $p->{role} || '',
			plrsig	=> $p->signature,
			name	=> $p->name,
			worldid	=> $p->worldid,
			ipaddr	=> $p->ipaddr,
		};
#		use Data::Dumper; print Dumper($set);
		$self->{db}->insert($db->{t_state_plrs}, $set);
	}
	$self->{db}->commit;
	$self->{db}->optimize($db->{t_state}, $db->{t_state_plrs});

	$self->{_state_saved} = $self->{game}->{timestamp};
}

sub load_state {
	my $self = shift;
	my $db = $self->{db};
	my $state;

	$state = $db->get_row_hash("SELECT * FROM $db->{t_state} WHERE logsource=" . $db->quote($self->{logsource})) || {};

	$state->{plrs} = [];
	if ($state->{id}) {
		$state->{plrs} = $db->get_rows_hash("SELECT * FROM $db->{t_state_plrs} WHERE id=" . $db->quote($state->{id}));
	}
	$self->{game}->restore_state($state);

	return $state;
}

sub _init { }
sub init { 1 }	# 1=wait; 0=error; -1=nowait
sub done { $_[0]->save_state }
sub next_event { undef };

1;
