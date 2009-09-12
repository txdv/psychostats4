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
#	$Id$
#
package PS::Game::halflife::tf;

use strict;
use warnings;
use base qw( PS::Game::halflife );
use PS::SourceFilter;
use util qw( :net print_r );

our $VERSION = '4.00.' . (('$Rev$' =~ /(\d+)/)[0] || '000');


sub init { 
	my $self = shift;
	$self->SUPER::init;

	# load the kill assist calculation. used in plrtrigger().
	$self->add_calcskill_func('killassist', $self->conf->main->calcskill_kill);

	return $self;
}

sub collect_state_vars {
	my ($self) = @_;
	my $state = $self->SUPER::collect_state_vars;
	$state->{last_kill_plr} 	= $self->{last_kill_plr} ? $self->{last_kill_plr}->freeze : undef;
	$state->{last_kill_weapon} 	= $self->{last_kill_weapon} ? $self->{last_kill_weapon}->name : undef;
	$state->{last_kill_role} 	= $self->{last_kill_role} ? $self->{last_kill_role}->name : undef;
	$state->{last_kill_headshot} 	= $self->{last_kill_headshot};
	return $state;
}

sub restore_state_vars {
	my ($self, $state) = @_;
	$self->SUPER::restore_state_vars($state);
	
	$self->{last_kill_plr} 		= PS::Plr->unfreeze($state->{last_kill_plr});
	$self->{last_kill_weapon} 	= $self->get_weapon($state->{last_kill_weapon});
	$self->{last_kill_role} 	= $self->get_role($state->{last_kill_role});
	$self->{last_kill_headshot} 	= $state->{last_kill_headshot};
}

# add some extra stats from a kill (called from event_kill)
# k 	= killer
# v 	= victim
# w 	= weapon
# m 	= map
# kr 	= killer role (might be undef)
# vr 	= victim role (which could be the same object as killer)
# props = extra properties hash
sub mod_event_kill {
	my ($self, $k, $v, $w, $m, $kr, $vr, $props) = @_;

	# used for kill assists
	$self->{last_kill_plr} = $k;
	$self->{last_kill_weapon} = $w;
	$self->{last_kill_role} = $kr;
	$self->{last_kill_headshot} = ($props->{headshot} ||
				       (($props->{customkill} || '') eq 'headshot')
				       );

	return;
}

sub event_plrtrigger {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $trigger, $plrstr2, $propstr) = @$args;
	my $p = $self->get_plr($plrstr);
	my $m = $self->get_map;
	my $props = $self->parseprops($propstr);

	return unless ref $p;
	#return if $self->isbanned($p);

	$trigger = lc $trigger;
	$self->plrbonus($trigger, 'enactor', $p);
	if ($trigger eq 'weaponstats' or $trigger eq 'weaponstats2') {
		$self->event_weaponstats($timestamp, $args);

	} elsif ($trigger eq 'address') {	# PIP 'address' (ipaddress) events
		$self->add_ipcache($p->uid, ip2int($props->{address}), $timestamp);

	} elsif ($trigger eq 'kill assist') {
		return unless $self->minconnected;
		my $p2 = $self->get_plr($plrstr2);
		if ($p2) {
			my $r = $self->get_role($p2->role, $p2->team);
			$p->action_kill_assist($self, $p2, $self->{last_kill_weapon}, $m, $props);
			$r->action_kill_assist($self, $p2, $self->{last_kill_weapon}, $m, $props) if $r;
			$self->calcskill_killassist_func($p, $p2, $self->{last_kill_weapon});
		}
		
	} elsif ($trigger eq 'flagevent') {
		return unless $self->minconnected;
		my $action = $props->{event};
		$action =~ s/\s+//g; # remove spaces
		$m->action_flag($self, $action, $p, $props);
		$p->action_flag($self, $action, $m, $props);
		$self->plrbonus('flag_' . $action,
				'enactor', 	$p,
				'enactor_team', $self->get_online_plrs($p->team),
				'victim_team',  $self->get_online_plrs($p->team eq 'red' ? 'blue' : 'red')
		);

	} elsif ($trigger eq 'killedobject') {
		return unless $self->minconnected;
		my $p2 = $props->{objectowner} ? $self->get_plr($props->{objectowner}) : undef;
		my $w  = $props->{weapon} ? $self->get_weapon($props->{weapon}) : undef;
		my $obj = lc substr($props->{object}, 4); # strip off "OBJ_"
		my $r = $self->get_role($p->role, $p->team);

		$p->action_destroyed_object($self, $obj, $p2, $w, $m, $props);
		$m->action_destroyed_object($self, $obj, $p, $p2, $w, $props);
		$r->action_destroyed_object($self, $obj, $p, $p2, $w, $props) if $r;

		# no bonus to the object owner if they destroy their own object.
		if (!$p2 or ($p->id != $p2->id)) {
			$self->plrbonus('destroyed_' . $obj,
					'enactor', $p,
					'victim', $p2);
		}

	} elsif ($trigger eq 'builtobject') {
		return unless $self->minconnected;
		my $obj = lc substr($props->{object}, 4); # strip off "OBJ_"
		my $r = $self->get_role($p->role, $p->team);

		$p->action_created_object($self, $obj, $m, $props);
		$m->action_created_object($self, $obj, $p, $props);
		$r->action_created_object($self, $obj, $p, $props) if $r;

		$self->plrbonus('built_' . $obj, 'enactor', $p);

	} elsif ($trigger eq 'chargedeployed') {
		return unless $self->minconnected;
		$p->action_ubercharge($self, $m, $props);

	} elsif ($trigger eq 'revenge') {
		return unless $self->minconnected;
		my $p2 = $self->get_plr($plrstr2);
		$p->action_misc_plr($self, $trigger, $p2, $m, $props);
		# enactor is given their bonus above
		$self->plrbonus($trigger, 'victim', $p2);

	} elsif ($trigger eq 'domination') {
		return unless $self->minconnected;
		# $props->{assist} will be true if this was the result of an
		# assist or straight domination kill. We don't care though.
		my $p2 = $self->get_plr($plrstr2);
		$p->action_misc_plr($self, $trigger, $p2, $m, $props);
		# enactor is given their bonus above
		$self->plrbonus($trigger, 'victim', $p2);

	} elsif ($trigger eq 'captureblocked') {
		return unless $self->minconnected;
		$p->action_blocked_capture($self, $m, $props);
		$m->action_blocked_capture($self, $p, $props);

	} elsif ($trigger eq 'backstab' ||
		 $trigger eq 'headshot') {
		# these are redundant triggers for 'customkill' properties and
		# are ignored otherwise the stat could be doubled. I'm not
		# sure if these triggers were added by an addon or were added
		# to the engine by valve.

	} elsif ($trigger eq 'hurt_firstblood') {
		# beetlesmod trigger, ignoring for now, until I deem it useful.

	} elsif ($trigger =~ /^(time|latency|amx_|game_idle_kick|camped)/) {
		# extra statsme / amx triggers
		$p->action_misc($self, $trigger, $m, $props);
		
	} else {
		if ($self->conf->main->errlog->report_unknown) {
			$self->warn("Unknown player trigger '$trigger' from src $self->{_src} line $self->{_line}: $self->{_event}");
		}
	}

}

sub event_teamtrigger {
        my ($self, $timestamp, $args) = @_;
        my ($team, $trigger, $propstr) = @$args;
	my $props = $self->parseprops($propstr);
        my $m = $self->get_map;
        return unless $self->minconnected;

        $team = $self->team_normal($team);

        $trigger = lc $trigger;
	if ($trigger eq 'pointcaptured') {
		my $players = [];
		my $list = [];
		my $i = 1;

		# old style (player "") (player "") ...
		if (ref $props->{player} eq 'ARRAY') {		# array of player strings
			push(@$list, @{$props->{player}});
		} elsif (defined $props->{player}) {		# 1 player string
			push(@$list, $props->{player});
		}

		# new style (player1 "") (player2 "") ...
		while (exists $props->{'player' . $i}) {
			push(@$list, $props->{'player' . $i++});
		}

		foreach my $plrstr (@$list) {
			my $p = $self->get_plr($plrstr) || next;
			$p->action_captured_point($self, $m, $props);
			# keep track of each player (for bonus)
			push(@$players, $p);
		}
		$m->action_captured_point($self, $props);

		my $team1 = $self->get_online_plrs($team);
		my $team2 = $self->get_online_plrs($team eq 'red' ? 'blue' : 'red');
		$self->plrbonus('point_captured',
				'enactor', $players,
				'enactor_team', $team1,
				'victim_team', $team2);
	} elsif ($trigger eq 'intermission_win_limit') {
		# uhm.... what?
	} else {
		print "Unknown team trigger: $trigger from src $self->{_src} line $self->{_line}: $self->{_event}\n";
	}
}

sub event_round {
	my ($self, $timestamp, $args) = @_;
	my ($trigger, $propstr) = @$args;
	my $props = $self->parseprops($propstr);
	my $m = $self->get_map;

	$trigger = lc $trigger;
	if ($trigger eq 'round_win' or $trigger eq 'mini_round_win') {
		my $team = $self->team_normal($props->{winner});
		return unless $team eq 'red' or $team eq 'blue';
		my $team2 = $team eq 'red' ? 'blue' : 'red';
		my $winners = $self->get_online_plrs($team);
		my $losers  = $self->get_online_plrs($team2);

		$m->action_teamwon($self, $trigger, $team, $props);
		$m->action_teamlost($self, $trigger, $team2, $props);
		$self->plrbonus($trigger, 'enactor_team', $winners, 'victim_team', $losers);
		$_->action_teamwon($self, $trigger, $team, $m, $props) for @$winners;
		$_->action_teamlost($self, $trigger, $team, $m, $props) for @$losers;
	} else {
		# parent handles 'round_start'
		$self->SUPER::event_round($timestamp, $args);
	}

}

sub event_logstartend {
	my ($self, $timestamp, $args) = @_;
	$self->SUPER::event_logstartend($timestamp, $args);
	$self->{last_kill_weapon} = undef;
	$self->{last_kill_role} = undef;
}

1;
