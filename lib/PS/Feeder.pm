# Base Feeder class. This is a basic factory class that creates a Feeder object based on the current logsource.
# If a subclass is detected for the current gametype it will be created and returned.
# Order of class detection (first class to be found is used):
#	PS::Feeder::{gametype}::{modtype}::{base}
#	PS::Feeder::{gametype}::{base}
#	PS::Feeder::{base}
#
package PS::Feeder;
#
#	This file is part of PsychoStats.
#
#	Written by Jason Morriss <stormtrooper@psychostats.com>
#	Copyright 2008 Jason Morriss
#
#	PsychoStats is free software: you can redistribute it and/or modify
#	it under the terms of the GNU General Public License as published by
#	the Free Software Foundation, either version 3 of the License, or
#	(at your option) any later version.
#
#	PsychoStats is distributed in the hope that it will be useful,
#	but WITHOUT ANY WARRANTY; without even the implied warranty of
#	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#	GNU General Public License for more details.
#
#	You should have received a copy of the GNU General Public License
#	along with PsychoStats.  If not, see <http://www.gnu.org/licenses/>.
#

use strict;
use warnings;
use base qw( PS::Debug );
use util qw( :numbers );
use Data::Dumper;

our $VERSION = '1.10.' . ('$Rev$' =~ /(\d+)/)[0];

our $WAIT   = 1;
our $ERROR  = 0;
our $NOWAIT = -1;

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

	if (ref $logsource) {	# logsource is a loaded hash
		$base = $logsource->{type};
	} else {		# logsource is a string and might have a protocol prefix
		$base = $1 if $logsource =~ m|^([^:]+)://.+|;
	}

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

sub _init {  # called by local new()
	my $self = shift;
	$self->{orig_logsource} = $self->{logsource};
	$self->parsesource;
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
	delete($state->{players});

	return if (defined $self->{game}{timestamp} and ($self->{_state_saved} == $self->{game}{timestamp}));
#	$::ERR->verbose("Saving state");

	my ($state_id) = $db->select($db->{t_state}, 'id', [ logsource => $self->{logsource}{id} ]);
#	$self->{db}->delete($db->{t_state}, [ logsource => $state_id ]);

	$state->{id} 		= $state_id ? $state_id : $self->{db}->next_id($db->{t_state});
	$state->{logsource} 	= $self->{logsource}{id};
	$state->{lastupdate} 	= time;
	$state->{timestamp} 	= $self->{game}->{timestamp};
	$state->{map} 		= $self->{game}->{curmap};

	local $Data::Dumper::Indent = 0;
	local $Data::Dumper::Terse = 1;

	my @players = ();
	foreach my $p ($self->{game}->get_plr_list) {
		next unless $p->active;		# don't remember player if they are not active
		my $plr = {
			plrid 	=> $p->plrid, 
			uid	=> $p->uid,
			isdead	=> $p->is_dead || 0,
			team	=> $p->team || '',
			role	=> $p->role || '',
			plrsig	=> $p->signature,
			name	=> $p->name,
			worldid	=> $p->uniqueid,
			ipaddr	=> $p->ipaddr,
		};
		push(@players, $plr);
	}
	$state->{players} = Dumper(\@players);
	$state->{ipaddrs} = Dumper($self->{game}{ipcache});
#	$state->{ipaddrs} = join("\n", map { "$_=" . $self->{game}{ipcache}{$_} } keys %{$self->{game}{ipcache}} );

	if ($state_id) {
		$self->{db}->update($db->{t_state}, $state, [ id => $state_id ]);
	} else {
		$self->{db}->insert($db->{t_state}, $state);
	}

	$self->{_state_saved} = $self->{game}{timestamp};
}

sub load_state {
	my $self = shift;
	my $db = $self->{db};
	my $state;

	$state = $db->get_row_hash("SELECT * FROM $db->{t_state} WHERE logsource=" . $db->quote($self->{logsource}{id})) || {};

	my $plrs = [];
	my $ips  = {};
	$plrs = eval $state->{players} if $state->{players};
	$ips  = eval $state->{ipaddrs} if $state->{ipaddrs};
	$state->{players} = $plrs;
	$state->{ipaddrs} = $ips;
	$self->{game}->restore_state($state);
	return $state;
}

sub init { 1 }	# called by subclass. ret: 1=wait; 0=error; -1=nowait
sub done { $_[0]->save_state }
sub defaultmap { $_[0]->{logsource}{defaultmap} }
sub next_event { undef }
sub idle { }
sub parsesource { }

1;
