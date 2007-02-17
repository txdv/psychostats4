package PS::Game::halflife::dod;

use strict;
use warnings;
use base qw( PS::Game::halflife );

our $VERSION = '1.01';


sub _init { 
	my $self = shift;
	$self->SUPER::_init;
	$self->load_events(*DATA);
	$self->{conf}->load('game_halflife_dod');

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
	$self->{dod_teamscore} = undef;
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
	my ($plrstr, $trigger, $propstr) = @$args;
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

	} elsif ($trigger eq 'address') {	# PIP 'address' events
		my $props = $self->parseprops($propstr);
		return unless $p1->{uid} and $props->{address};
		$self->{ipcache}{$p1->{uid}} = ip2int($props->{address});

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

# Original DOD 'team scored' event. The only way to tell which team won with old DOD
# is to compare the 2 'scored' events and see which team had more points.
# this can also be used to count 'rounds'
sub event_dod_teamscore {
	my ($self, $timestamp, $args) = @_;
	my ($team, $score, $numplrs) = @$args;
	$team = lc $team;

	# if there's no team score known yet, record it and return.
	# there are always 2 'team scored' events per round.
	if (!$self->{dod_teamscore}) {
		$self->{dod_teamscore} = { team => $team, score => $score, numplrs => $numplrs };
		return;
	}

	my $m = $self->get_map;
	my $teams = {
		allies	=> $self->get_team('allies', 1) || [],
		axis	=> $self->get_team('axis',   1) || [],
	};

#	print "allies = " . scalar(@{$self->get_team('allies', 1)}) . "\n";
#	print "axis   = " . scalar(@{$self->get_team('axis', 1)}) . "\n";

	# increase everyone's rounds
	$m->{rounds}++;
	for (@{$teams->{allies}}, @{$teams->{axis}}) {
		$_->{rounds}++;
		$_->{maps}{ $m->{mapid} }{rounds}++;
	}

	# determine who won and lost
	my ($won, $lost, $teamwon, $teamlost);
	if ($score > $self->{dod_teamscore}{score}) {
		$teamwon  = $team;
		$teamlost = $team eq 'axis' ? 'allies' : 'axis';
		$won  = $teams->{ $teamwon };
		$lost = $teams->{ $teamlost };
	} elsif ($self->{dod_teamscore}{score} > $score) {
		$teamwon  = $self->{dod_teamscore}{team};
		$teamlost = $self->{dod_teamscore}{team} eq 'axis' ? 'allies' : 'axis';
		$won  = $teams->{ $teamwon };
		$lost = $teams->{ $teamlost };
	} else {
		# do mot count 'draws'
	}

	# clear the previous team score
	$self->{dod_teamscore} = undef;

	return unless $teamwon;	# no one 'won'; it's a draw.

	# assign won/lost values to all players
	$m->{mod}{$teamwon  . 'won'}++;
	$m->{mod}{$teamlost . 'lost'}++;
	for (@$won) {
		$_->{mod}{$teamwon.'won'}++;
		$_->{mod_maps}{ $m->{mapid} }{$teamwon.'won'}++;
	}
	for (@$lost) {
		$_->{mod}{$teamlost.'lost'}++;
		$_->{mod_maps}{ $m->{mapid} }{$teamlost.'lost'}++;
	}
}

sub has_mod_tables { 1 }

1;

__DATA__

[plrtrigger]
  regex = /^"([^"]+)" triggered(?: a)? "([^"]+)"(.*)/

## new dod:s events

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

