package PS::Game::halflife::natural;

use strict;
use warnings;
use base qw( PS::Game::halflife );

our $VERSION = '1.00';


sub _init { 
	my $self = shift;
	$self->SUPER::_init;
	$self->load_events(*DATA);
	$self->{conf}->load('game_halflife_natural');

	$self->{plr_save_on_round} = ($self->{plr_save_on} eq 'round');

	return $self;
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
	my ($plrstr, $trigger, $propstr) = @$args;
	my $p1 = $self->get_plr($plrstr) || return;
	return if $self->isbanned($p1);

	$p1->{basic}{lasttime} = $timestamp;
	return unless $self->minconnected;
	my $m = $self->get_map;

	my @vars = ();
	$trigger = lc $trigger;
	$self->plrbonus($trigger, 'enactor', $p1);
	if ($trigger eq 'weaponstats' or $trigger eq 'weaponstats2') {
		$self->event_weaponstats($timestamp, $args);

	} elsif ($trigger eq 'address') {	# PIP 'address' events
		my $props = $self->parseprops($propstr);
		return unless $p1->{uid} and $props->{address};
		$self->{ipcache}{$p1->{uid}} = ip2int($props->{address});

	} elsif ($trigger eq '') {
#		@vars = ( $p1->{team} . 'flagsblocked', 'flagsblocked' );

# ---------

	# extra statsme / amx triggers
	} elsif ($trigger =~ /^(time|latency|amx_|game_idle_kick)/) {

	} else {
		if ($self->{report_unknown}) {
			$self->warn("Unknown player trigger '$trigger' from src $self->{_src} line $self->{_line}: $self->{_event}");
		}
	}

	foreach my $var (@vars) {
		$p1->{mod_maps}{ $m->{mapid} }{$var}++;
		$p1->{mod}{$var}++;
		$m->{mod}{$var}++;
	}
}

sub event_ns_mapinfo {
	my ($self, $timestamp, $args) = @_;
	my ($mapname, $propstr) = @$args;
	my $props = $self->parseprops($propstr);

}

# can't use the built in halflife::change_role since NS likes 
# to do things a little differently
sub event_ns_changed_role {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $rolestr) = @$args;
	my $p1 = $self->get_plr($plrstr) || return;
	$rolestr =~ s/^(?:#?class_)?//;
	my $r1 = $self->get_role($rolestr, $p1->{team});

	$p1->{role} = $rolestr;

	$p1->{roles}{ $r1->{roleid} }{joined}++;
	$r1->{basic}{joined}++ if $r1;
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
  regexp = /^(?:Game reset)/
  options = ignore

[ns_ignore2]
  regexp = /^Map validity/
  options = ignore

[ns_ignore3]
  regexp = /^Team "\d+" scored/
  options = ignore
