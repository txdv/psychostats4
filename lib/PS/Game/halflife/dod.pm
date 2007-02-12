package PS::Game::halflife::dod;

use strict;
use warnings;
use base qw( PS::Game::halflife );

our $VERSION = '1.00';


sub _init { 
	my $self = shift;
	$self->SUPER::_init;
	$self->load_events(*DATA);
	$self->{conf}->load('game_halflife_dod');

	$self->{plr_save_on_round} = ($self->{plr_save_on} eq 'round');

	return $self;
}

sub event_dod_teamtrigger {
	my ($self, $timestamp, $args) = @_;
	my ($team, $trigger, $props) = @$args;
	my ($team2);

	return unless $self->minconnected;
	my $m = $self->get_map;

	my @vars = ();
	$team = lc $team;

	$trigger = lc $trigger;
	if ($trigger eq 'tick_score') {
		my $val = $self->parseprops($props);
		return unless (($team eq 'allies' or $team eq 'axis') and $val->{score});
		my $plrs = $self->get_team($team, 1);			# dead players count too
		my $var = $team . 'score';
#		print scalar @$plrs, " $team members scored $val->{score} on map " . $m->name . "\n";
		foreach my $p1 (@$plrs) {
			$p1->{mod_maps}{ $m->{mapid} }{$var}++;
			$p1->{mod}{$var}++;
		}
		$m->{mod}{$var}++;

	} elsif ($trigger eq 'round_win') {
		return unless $team eq 'allies' or $team eq 'axis';
		my $team2 = $team eq 'axis' ? 'allies' : 'axis';
		my $winners = $self->get_team($team, 1);
		my $losers  = $self->get_team($team2, 1);
		my $var = $team . 'won';
		my $var2 = $team2 . 'lost';
		foreach my $p1 (@$winners) {
			$p1->{mod_maps}{ $m->{mapid} }{$var}++;
			$p1->{mod}{$var}++;
		}
		foreach my $p1 (@$losers) {
			$p1->{mod_maps}{ $m->{mapid} }{$var2}++;
			$p1->{mod}{$var2}++;
		}
		$m->{mod}{$var}++;
		$m->{mod}{$var2}++;

	} elsif ($trigger eq 'captured_loc') {
	} elsif ($trigger eq 'team_scores') {
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
	my ($plrstr, $trigger, $props) = @$args;
	my $p1 = $self->get_plr($plrstr) || return;
	return if $self->isbanned($p1);

	$p1->{basic}{lasttime} = $timestamp;
	return unless $self->minconnected;
	my $m = $self->get_map;

	my @vars = ();
	$trigger = lc $trigger;
#	print "PLRBONUS($trigger, 'enactor', $p1);\n";
	$self->plrbonus($trigger, 'enactor', $p1);
	if ($trigger eq 'weaponstats' or $trigger eq 'weaponstats2') {
		$self->event_weaponstats($timestamp, $args);

	} elsif ($trigger eq 'dod_object') {					# got TNT (pre source)
# these are useless, from the old days of the original DOD
#		@vars = qw( tnt );
	} elsif ($trigger eq 'dod_object_goal') {				# used TNT (pre source)
#		@vars = qw( tntused );

	} elsif ($trigger eq 'dod_control_point') {				# plr captured a flag
		@vars = ( $p1->{team} . 'flagscaptured', 'flagscaptured' );

	} elsif ($trigger eq 'dod_capture_area') {				# plr captured an area (pre source)
		@vars = ( $p1->{team} . 'flagscaptured', 'flagscaptured' );
### no longer counting 'areas' separately
###		@vars = ( $p1->{team} . 'areascaptured', 'areascaptured' );

	} elsif ($trigger eq 'bomb_plant') {	# props: flagindex, flagname
		@vars = ( $p1->{team} . 'bombplanted', 'bombplanted');

	} elsif ($trigger eq 'bomb_defuse') {
		# this can be used as a percentage against flagsblocked
		@vars = ( $p1->{team} . 'bombdefused', 'bombdefused' );

	} elsif ($trigger eq 'kill_planter') {	# props: ' against "<plrstr>"'
		@vars = ( 'killedbombplanter' );

	} elsif ($trigger eq 'dod_blocked_point') {
		@vars = ( $p1->{team} . 'flagsblocked', 'flagsblocked' );

# ignore the following triggers, they're detected using other triggers above
	} elsif ($trigger eq 'axis_capture_flag') {
	} elsif ($trigger eq 'allies_capture_flag') {
	} elsif ($trigger eq 'capblock') {
	} elsif ($trigger eq 'allies_blocked_capture') {
	} elsif ($trigger eq 'axis_blocked_capture') {
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

sub event_dods_round {
	my ($self, $timestamp, $args) = @_;
	my ($trigger, $props) = @$args;
	my $m = $self->get_map;

	$trigger = lc $trigger;
	if ($trigger eq 'round_start') {
		$m->{basic}{rounds}++;
		while (my ($uid, $p1) = each %{$self->{plrs}}) {
			$p1->{basic}{lasttime} = $timestamp;
			$p1->{isdead} = 0;
			$p1->{basic}{rounds}++;
			$p1->{maps}{ $m->{mapid} }{rounds}++;
			$p1->save if $self->{plr_save_on_round};
		}
	}
}

sub event_dod_changed_role {
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
  regex = /^"([^"]+)" triggered(?: a)? "([^"]+)"(.*)/

## new dod:s events

[dods_round]
  regex = /^World triggered "([^"]+)"(.*)/

[dods_scores]
  regex = /^Team "([^"]+)" (scored|captured)/
  options = ignore

[dods_ignore1]
  regex = /^"([^"]+)" blocked/
  options = ignore

## original dod events

[dod_changed_role]
  regex = /^"([^"]+)" changed role to "([^"]+)"/

[dod_objitem]
  regex = /^"([^"]+)" triggered an objective item/

[dod_teamscore]
  regex = /^"([^"]+)" scored "([^"]+)" with "([^"]+)" players/

[dod_teamtrigger]
  regex = /^Team "([^"]+)" triggered(?: a)? "([^"]+)"(.*)/

[dod_ignorelines1]
  regex = /^(?:Final)/
  options = ignore

