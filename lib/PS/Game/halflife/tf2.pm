package PS::Game::halflife::tf2;

use strict;
use warnings;
use base qw( PS::Game::halflife );

use util qw( :net print_r );

our $VERSION = '1.00';


sub _init { 
	my $self = shift;
	$self->SUPER::_init;

	return $self;
}

# add some extra stats from a kill (called from event_kill)
# p1 	= killer
# p2 	= victim
# w 	= weapon
# m 	= map
# r1 	= killer role (might be undef)
# r2 	= victim role (which could be the same object as killer)
# props = extra properties hash
sub mod_event_kill {
	my ($self, $p1, $p2, $w, $m, $r1, $r2, $props) = @_;

	my $custom = $props->{customkill};
	if ($custom) {	# headshot, backstab
		my $key = ($custom eq 'headshot') ? 'basic' : 'mod';

		$p1->{victims}{ $p2->{plrid} }{$custom . 'kills'}++;
		$p1->{mod_maps}{ $m->{mapid} }{$custom . 'kills'}++;
		$p1->{mod_roles}{ $r1->{roleid} }{$custom . 'kills'}++ if $r1;

		$p1->{$key}{$custom . 'kills'}++;
		$p2->{$key}{$custom . 'deaths'}++;
		$r1->{$key}{$custom . 'kills'}++ if $r1;
		$r2->{$key}{$custom . 'deaths'}++ if $r2;
		$m->{$key}{$custom  . 'kills'}++;
		$w->{$key}{$custom  . 'kills'}++;
	}
}

sub event_plrtrigger {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $trigger, $plrstr2, $propstr) = @$args;
	my $p1 = $self->get_plr($plrstr) || return;
	my $p2 = undef;
	$self->_do_connected($timestamp, $p1) unless $p1->{_connected};
	return if $self->isbanned($p1);

	$p1->{basic}{lasttime} = $timestamp;
	return unless $self->minconnected;
	my $r1 = $self->get_role($p1->{role}, $p1->{team});
	my $m = $self->get_map;

	$trigger = lc $trigger;
	$self->plrbonus($trigger, 'enactor', $p1);
	
	my @vars = ();
	if ($trigger eq 'weaponstats' or $trigger eq 'weaponstats2') {
		$self->event_weaponstats($timestamp, $args);

	} elsif ($trigger eq 'address') {	# PIP 'address' (ipaddress) events
		my $props = $self->parseprops($propstr);
		return unless $p1->{uid} and $props->{address};
		$self->{ipcache}{$p1->{uid}} = ip2int($props->{address});

	} elsif ($trigger eq 'kill assist') {
		@vars = ( $p1->{team} . 'assists', 'assists' );
		$p1->{mod_roles}{ $r1->{roleid} }{assists}++ if $r1;
		$self->plrbonus('kill_assist', 'enactor', $p1);

	} elsif ($trigger eq 'flagevent') {
		my $props = $self->parseprops($propstr);
		if ($props->{event} eq "defended") {
			@vars = ( $p1->{team} . 'flagsdefended', 'flagsdefended' );
			$self->plrbonus('flag_defended','enactor',$p1);

		} elsif ($props->{event} eq "picked up") {
			@vars = ( $p1->{team} . 'flagspickedup', 'flagspickedup' );

		} elsif ($props->{event} eq "dropped") {
			@vars = ( $p1->{team} . 'flagsdropped', 'flagsdropped' );

		} elsif ($props->{event} eq "captured") {
			@vars = ( $p1->{team} . 'flagscaptured', 'flagscaptured' );
			$self->plrbonus('flag_captured', 'enactor', $p1);
		}

	} elsif ($trigger eq 'killedobject') {
		my $props = $self->parseprops($propstr);
		if ($props->{object} eq "OBJ_DISPENSER") {
			@vars = ( 'dispenserdestroy' );

		} elsif ($props->{object} eq "OBJ_SENTRYGUN") {
			@vars = ( 'sentrydestroy' );
			$self->plrbonus('killedsentry', 'enactor', $p1);	# these warrant an extra bonus

		} elsif ($props->{object} eq "OBJ_TELEPORTER_ENTRANCE" || $props->{object} eq "OBJ_TELEPORTER_EXIT") {
			@vars = ( 'teleporterdestroy' );

		} elsif ($props->{object} eq "OBJ_ATTACHMENT_SAPPER") {
			@vars = ( 'sapperdestroy' );

		}
		push(@vars, 'itemsdestroyed');

	} elsif ($trigger eq 'revenge') {
		@vars = ( 'revenge' );

	} elsif ($trigger eq 'builtobject') {
		@vars = ( 'itemsbuilt' );
		# player built something... good for them.

	} elsif ($trigger eq 'chargedeployed') {
		# ... something to do with the medic charge gun thingy ...
		@vars = ( 'chargedeployed' );

	} elsif ($trigger eq 'domination') {
		$p2 = $self->get_plr($plrstr2) || return;
#		my $r2 = $self->get_role($p2->{role}, $p1->{team});
		@vars = ( 'dominations' );

		$self->plrbonus('domination', 'enactor', $p1, 'victim', $p2);

	} elsif ($trigger eq 'captureblocked') {
		@vars = ( $p1->{team} . 'captureblocked', 'captureblocked' );

	} elsif ($trigger =~ /^(time|latency|amx_|game_idle_kick)/) {

	} else {
		if ($self->{report_unknown}) {
			$self->warn("Unknown player trigger '$trigger' from src $self->{_src} line $self->{_line}: $self->{_event}");
		}
	}

	foreach my $var (@vars) {
		$p1->{mod_maps}{ $m->{mapid} }{$var}++;
		$p1->{mod}{$var}++;
		if ($r1) {
			$p1->{mod_roles}{ $r1->{roleid} }{$var}++;
			$r1->{mod}{$var}++;
		}
		$m->{mod}{$var}++;
	}
}

sub event_teamtrigger {
        my ($self, $timestamp, $args) = @_;
        my ($team, $trigger, $propstr) = @$args;
        my ($team2);

        return unless $self->minconnected;
        my $m = $self->get_map;

        my @vars = ();
        $team = $self->team_normal($team);

        $trigger = lc $trigger;

	if ($trigger eq "pointcaptured") {
		my $props = $self->parseprops($propstr);
		my $roles = {};
		my $players = [];
		my $list = [];
		my $i = 1;
		while (exists $props->{'player' . $i}) {
			push(@$list, $props->{'player' . $i++});
		}
		return unless @$list;
		foreach my $plrstr (@$list) {
			my $p1 = $self->get_plr($plrstr) || next;
			my $r1 = $self->get_role($p1->{roleid}, $team);
			$p1->{mod}{$trigger}++;
			$p1->{mod}{$team . $trigger}++;
			$p1->{mod_maps}{ $m->{mapid} }{$trigger}++;
			$p1->{mod_roles}{$trigger}++;
#			$roles->{ $r1->{roleid} } = $r1 if $r1;		# keep track of which roles are involved
			push(@$players, $p1);				# keep track of each player
		}
#		$roles->{$_}{mod}{$trigger}++ for keys %$roles;		# give point to each unique role
		$m->{mod}{$trigger}++;
		$m->{mod}{$team . $trigger}++;
		my $team1 = $self->get_team($team, 1);
		my $team2 = $self->get_team($team eq 'red' ? 'blue' : 'red', 1);
		$self->plrbonus($trigger, 'enactor', $players, 'enactor_team', $team1, 'victim_team', $team2);
	} else {
		print "Unknown team trigger: $trigger from src $self->{_src} line $self->{_line}: $self->{_event}\n";
	}
}

sub event_round {
	my ($self, $timestamp, $args) = @_;
	my ($trigger, $propstr) = @$args;

	$trigger = lc $trigger;
	if ($trigger eq 'round_win' or $trigger eq 'mini_round_win') {
		my $m = $self->get_map;
		my $props = $self->parseprops($propstr);
		my $team = $self->team_normal($props->{winner}) || return;
		return unless $team eq 'red' or $team eq 'blue';
		my $team2 = $team eq 'red' ? 'blue' : 'red';
		my $winners = $self->get_team($team, 1);
		my $losers  = $self->get_team($team2, 1);
		my $var = $team . 'won';
		my $var2 = $team2 . 'lost';
		$self->plrbonus($trigger, 'enactor_team', $winners, 'victim_team', $losers);
		$m->{mod}{$var}++;
		$m->{mod}{$var2}++;
		foreach my $p1 (@$winners) {
			$p1->{basic}{rounds}++;
			$p1->{maps}{ $m->{mapid} }{basic}{rounds}++;
			$p1->{mod_maps}{ $m->{mapid} }{$var}++;
			$p1->{mod}{$var}++;
		}
		foreach my $p1 (@$losers) {
			$p1->{basic}{rounds}++;
			$p1->{maps}{ $m->{mapid} }{basic}{rounds}++;
			$p1->{mod_maps}{ $m->{mapid} }{$var2}++;
			$p1->{mod}{$var2}++;
		}
	} else {
		$self->SUPER::event_round($timestamp, $args);
	}

}

sub has_mod_tables { 1 }

1;
