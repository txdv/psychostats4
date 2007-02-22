package PS::Game::halflife::natural;

use strict;
use warnings;
use base qw( PS::Game::halflife );

use util qw( :net );

our $VERSION = '1.00';


sub _init { 
	my $self = shift;
	$self->SUPER::_init;
	$self->load_events(*DATA);
	$self->{conf}->load('game_halflife_natural');

	$self->{plr_save_on_round} = ($self->{plr_save_on} eq 'round');

	return $self;
}

# override default event so we can reset per-log variables
sub event_logstartend {
	my ($self, $timestamp, $args) = @_;
	my ($startedorclosed) = @$args;
	$self->SUPER::event_logstartend($timestamp, $args);

	return unless lc $startedorclosed eq 'started';

	# reset some tracking vars
#	map { undef $self->{$_} } qw( ns_commander );
	$self->{ns_commander} = undef;
}

sub event_ns_teamtrigger {
	my ($self, $timestamp, $args) = @_;
	my ($team, $trigger, $props) = @$args;
	my ($team2);

	return unless $self->minconnected;
	my $m = $self->get_map;

	my @vars = ();
	$team = lc $team;

	$trigger = lc $trigger;
	if ($trigger eq '') {
	} elsif ($trigger eq '') {
	} else {
		if ($self->{report_unknown}) {
			$self->warn("Unknown team trigger '$trigger' from src $self->{_src} line $self->{_line}: $self->{_event}");
		}
	}

#	foreach my $var (@vars) {
#		$p1->{mod_maps}{ $m->{mapid} }{$var}++;
#		$p1->{mod}{$var}++;
#		$m->{mod}{$var}++;
#	}
}

sub event_plrtrigger {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $trigger, $plrstr2, $propstr) = @$args;
	my $p1 = $self->get_plr($plrstr) || return;
	my $p2 = undef;
	return if $self->isbanned($p1);

	$p1->{basic}{lasttime} = $timestamp;
	return unless $self->minconnected;
	my $m = $self->get_map;

	my @vars1 = ();
	my @vars2 = ();
	my $value1 = 1;
	my $value2 = 1;
	$trigger = lc $trigger;
	$self->plrbonus($trigger, 'enactor', $p1);
	if ($trigger eq 'weaponstats' or $trigger eq 'weaponstats2') {
		$self->event_weaponstats($timestamp, $args);

	} elsif ($trigger eq 'address') {	# PIP 'address' events
		my $props = $self->parseprops($propstr);
		return unless $p1->{uid} and $props->{address};
		$self->{ipcache}{$p1->{uid}} = ip2int($props->{address});

	} elsif ($trigger eq 'votedown') {
		$p2 = $self->get_plr($plrstr2);
		@vars2 = ( 'votedown' );
		$self->plrbonus($trigger, 'victim', $p2);	# current commander sucks

	} elsif ($trigger eq 'structure_built') {
		@vars1 = ( 'structuresbuilt' );

	} elsif ($trigger eq 'structure_destroyed') {
		@vars1 = ( 'structuresdestroyed' );

	} elsif ($trigger eq 'recycle') {
		@vars1 = ( 'structuresrecycled' );

	} elsif ($trigger eq 'research_start') {
		@vars1 = ( 'research' );

	} elsif ($trigger eq 'research_cancel') {
		@vars1 = ( 'research' );
		$value1 = -1;

#	} elsif ($trigger eq 'x') {
#		@vars = ( $p1->{team} . 'x', 'x' );

# ---------

	# extra statsme / amx triggers
	} elsif ($trigger =~ /^(time|latency|amx_|game_idle_kick)/) {

	} else {
		if ($self->{report_unknown}) {
			$self->warn("Unknown player trigger '$trigger' from src $self->{_src} line $self->{_line}: $self->{_event}");
		}
	}

	foreach my $var (@vars1) {
		$p1->{mod_maps}{ $m->{mapid} }{$var} += $value1;
		$p1->{mod}{$var} += $value1;
		$m->{mod}{$var} += $value1;
	}

	if (ref $p2) {
		foreach my $var (@vars2) {
			$p2->{mod_maps}{ $m->{mapid} }{$var} += $value2;
			$p2->{mod}{$var} += $value2;
			# don't bump global map stats here; do it for $p1 above
		}
	}
}

# this event is triggered after a round has been completed and a team won
sub event_ns_mapinfo {
	my ($self, $timestamp, $args) = @_;
	my ($mapname, $propstr) = @$args;
	my $props = $self->parseprops($propstr);
	my $m = $self->get_map;
	my $marine = $self->get_team('marine', 1);
	my $alien  = $self->get_team('alien', 1);
	my ($p1, $p2, $marinevar, $alienvar, $won, $lost);

	if ($props->{victory_team} eq 'marine') {
		$won  = $marine;
		$lost = $alien;
		$marinevar = 'marinewon';
		$alienvar = 'alienlost';
		# give a point to the current commander
		if ($self->{ns_commander} and $self->minconnected) {
			$p2 = $self->{ns_commander};
			$p2->{mod}{commanderwon}++;
			$self->plrbonus('commander_win', 'enactor', $p2);
		}
	} else {
		$won  = $alien;
		$lost = $marine;
		$marinevar = 'marinelost';
		$alienvar = 'alienwon';
	}
	$self->plrbonus('round_win', 'enactor_team', $won, 'victim_team', $lost);

	# assign won/lost points ...
	$m->{mod}{$marinevar}++;
	$m->{mod}{$alienvar}++;
	foreach (@$marine) {
		$_->{mod}{$marinevar}++;
		$_->{mod_maps}{ $m->{mapid} }{$marinevar}++;		
	}
	foreach (@$alien) {
		$_->{mod}{$alienvar}++;
		$_->{mod_maps}{ $m->{mapid} }{$alienvar}++;		
	}
}

# can't use the built in halflife::change_role since NS likes 
# to do things a little differently
sub event_ns_changed_role {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $rolestr) = @$args;
	my $p1 = $self->get_plr($plrstr) || return;
	$rolestr =~ s/^(?:#?class_)//;
	my $r1 = $self->get_role($rolestr, $p1->{team});

	$p1->{role} = $rolestr;

	$p1->{roles}{ $r1->{roleid} }{joined}++;
	$r1->{basic}{joined}++ if $r1;

	# keep track of who is the commander
	if ($rolestr eq 'commander') {
#		print "CHANGING COMMANDER\n";
		$self->{ns_commander} = $p1;
	}
}

# override halflife event. NS uses stupid team names so we need to clean it up
sub event_joined_team {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $team, $props) = @$args;
	my $p1 = $self->get_plr($plrstr) || return;
	my $m = $self->get_map;
	$self->_do_connected($timestamp, $p1) unless $p1->{_connected};

	$team = lc $team;
	$team =~ tr/ /_/;
	$team =~ tr/a-z0-9_//cs;				# remove all non-alphanumeric characters
	$team =~ s/\dteam$//;					# remove trailing '1team' from team names
	$team = 'spectator' if $team eq 'spectators';		# some MODS have a trailing 's'.
	$team = '' if $team eq 'unassigned';

	$self->{plrs}{ $p1->uid } = $p1;
	$self->delcache($p1->signature($plrstr));		# delete old sig from cache and set new sig
	$self->addcache($p1, $p1->signature, $p1->uniqueid);	# add current sig and uniqueid to cache

	$p1->{team} = $team;

	# do not record team events for spectators
#	return if $team eq 'spectator';

	$p1->{basic}{lasttime} = $timestamp;
	if ($team) {
		$p1->{mod_maps}{ $m->{mapid} }{"joined$team"}++;
		$p1->{mod}{"joined$team"}++;
		$m->{mod}{"joined$team"}++;
	}
}

# Remove the eject prefix and inject the event back into the queue.
# At least some versions of NS do not write a newline to the end of
# of the 'Eject' message causing the next log event to be appended
# to the end and it will otherwise get ignored.
sub event_ns_eject_fix {
	my ($self, $timestamp, $args) = @_;
	my ($event) = @$args;
	return if !$event or $event =~ /^\s*$/;
	$self->event($self->{_src}, $event, $self->{_line});
}

sub has_mod_tables { 1 }

1;

__DATA__

[plrtrigger]
  regex = /^"([^"]+)" triggered "([^"]+)"(?: against "([^"]+)")?(.*)/

[ns_changed_role]
  regex = /^"([^"]+)" changed role to "([^"]+)"/

[ns_mapinfo]
  regex = /^\(map_name "([^"]+)"\)(.*)/

[ns_teamtrigger]
  regex = /^Team "([^"]+)" triggered "([^"]+)"(.*)/

[ns_teamlost]
  regex = /^Team \d+ has lost/
  options = ignore

[ns_ignore1]
  regex = /^(?:Map validity|Game reset|AvHGamerules|Contact|BUG:|AvHVisibleBlipList)/
  options = ignore

[ns_ignore2]
  regex = /^Team "\d+" scored/
  options = ignore

# fix for "Eject commander: \d+ of d+ votes needed."
[ns_eject_fix]
  regex = /^Eject commander: \d+ of \d+ votes needed\.(.*)/
