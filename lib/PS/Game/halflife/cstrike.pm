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
#	$Id$
#
package PS::Game::halflife::cstrike;

use strict;
use warnings;
use base qw( PS::Game::halflife );
use PS::SourceFilter;
use util qw( :net );

our $VERSION = '4.00.' . (('$Rev$' =~ /(\d+)/)[0] || '000');

#sub init { 
#	my $self = shift;
#	$self->SUPER::init;
##	$self->{cs_hostages} = {};
#	return $self;
#}

# cstrike doesn't have character roles (classes) so just return a false value
sub get_role { undef }
sub role_normal { '' }

# override default event so we can reset per-log variables
sub event_logstartend {
	my ($self, $timestamp, $args) = @_;
	my ($startedorclosed) = @$args;
	$self->SUPER::event_logstartend($timestamp, $args);

	return unless lc $startedorclosed eq 'started';

	# reset some tracking vars
	undef $self->{$_} for qw( cs_bombplanter cs_bombspawner cs_vip );
}

sub event_plrtrigger {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $trigger, $propstr) = @$args;
	my $p = $self->get_plr($plrstr) || return;
	my $m = $self->get_map;
	my $props = $self->parseprops($propstr);

	#return if $self->isbanned($p);
	#return unless $self->minconnected;

	$trigger = lc $trigger;
	$self->plrbonus($trigger, 'enactor', $p);
	if ($trigger eq 'weaponstats' or $trigger eq 'weaponstats2') {
		$self->event_weaponstats($timestamp, $args);

	} elsif ($trigger eq 'address') {			# PIP 'address' (ipaddress) events
		$self->add_ipcache($p->uid, ip2int($props->{address}), $timestamp);

	} elsif ($trigger =~ /^(killed|touched|rescued)_a_hostage/) {
		$p->action_hostage($self, $1, $m, $props);
		$m->action_hostage($self, $1, $p, $props);

	} elsif ($trigger =~ /^begin_bomb_defuse/) {		# ignore: _with_kit, _without_kit
		$p->action_bomb($self, 'defuse_attempts', $m, $props);
		$m->action_bomb($self, 'defuse_attempts', $p, $props);

	} elsif ($trigger =~ /^(planted|defused|spawned_with|got|dropped)_the_bomb/) {
		my $action = $1;
		$p->action_bomb($self, $action, $m, $props);
		$m->action_bomb($self, $action, $p, $props);

		if ($action eq 'planted') {
			# keep track of who planted bomb
			$self->{cs_bombplanter} = $p;

		} elsif ($action eq 'defused') {
			# bomb planter should lose points
			if (ref $self->{cs_bombplanter}) {
				$self->plrbonus($trigger, 'victim', $self->{cs_bombplanter});
			}

		# 'spawned_with_the_bomb' is not logged by the source engine.
		# so I'm not going to track the bomb_runner anymore.
		#} elsif ($action eq 'spawned_with') {
		#	# keep track of who spawned with the bomb
		#	$self->{cs_bombspawner} = $p;
		#
		#} elsif ($action eq 'dropped' or $action eq 'got') {
		#	# if the bomb dropped the spawner is no longer valid
		#	$self->{cs_bombspawner} = undef;
		}

	} elsif ($trigger =~ /^(became|escaped_as|assassinated_the)_vip/) {
		# VIP games are a thing of the past. No one plays them anymore.
		#my $action = $1;
		#$action = (split '_', $action, 2)[0];	# remove '_as' or '_the'
		#$action = 'killed' if $action eq 'assassinated';
		#
		#$p->action_vip($self, $action, $m, $props);
		#$m->action_vip($self, $action, $p, $props);
		#
		#if ($action eq 'became') {
		#	$self->{cs_vip} = $p;			# keep track of current CT VIP
		#
		#} elsif ($action eq 'killed') {
		#	# VIP should lose points
		#	$self->plrbonus($trigger, 'victim', $self->{cs_vip}) if ref $self->{cs_vip};
		#	$self->{cs_vip} = undef;
		#}

	} elsif ($trigger eq 'terrorist_escaped') {
		# no one plays these maps anymore, they suck. and HL2 doesn't
		# have this mode of play anymore.
		
	} elsif ($trigger =~ /^(time|latency|amx_|game_idle_kick|camped)/) {
		# extra statsme / amx triggers
		$p->action_misc($self, $trigger, $props);
		
	} else {
		if ($self->{report_unknown}) {
			$self->warn("Unknown player trigger '$trigger' from src $self->{_src} line $self->{_line}: $self->{_event}");
		}
	}

}

# reset cstrike tracking var(s) at the start of each round.
sub event_round {
	my $self = shift;
	undef $self->{cs_bombplanter};
	$self->SUPER::event_round(@_);
}

sub event_teamtrigger {
	my ($self, $timestamp, $args) = @_;
	my ($team, $trigger, $propstr) = @$args;
	my $m = $self->get_map;
	my $ct = $self->get_online_plrs('ct');
	my $terr = $self->get_online_plrs('terrorist');
	my $props = $self->parseprops($propstr);
	my ($winners, $losers, $bombed);
	return unless $self->minconnected;

	$team = $self->team_normal($team);
	$trigger = lc $trigger;

	# bonuses for the teams are assigned at the end...
	
	if ($trigger eq 'terrorists_win' ||
	    $trigger eq 'target_bombed' ||
	    $trigger eq 'hostages_not_rescued' ||
	    $trigger eq 'terrorists_escaped' ||
	    $trigger eq 'vip_assassinated' ||
	    $trigger eq 'vip_not_escaped') {
		
		$winners = $terr;
		$losers = $ct;

		# make sure to count the 'target_bombed' event even though the
		# round ended before it actually occured.
		$bombed = 1 if ref $self->{cs_bombplanter} &&
			($trigger eq 'target_bombed' ||
			 $trigger eq 'terrorists_win');

	} elsif ($trigger eq 'cts_win' ||
		 $trigger eq 'bomb_defused' ||
		 $trigger eq 'target_saved' ||
		 $trigger eq 'all_hostages_rescued' ||
		 $trigger eq 'vip_escaped' ||
		 $trigger eq 'cts_preventescape' ||
		 $trigger eq 'terrorists_not_escaped') {
		
		$winners = $ct;
		$losers = $terr;
		
	#} elsif ($trigger eq 'intermission_win_limit') {
	#	# TF2 trigger when a round time limit is reached the team with
	#	# the highest score wins the round.
	#	# ignore for now.
	#	return;

	} else {
		if ($self->{report_unknown}) {
			$self->warn("Unknown team trigger '$trigger' from source $self->{_src} line $self->{_line}: $self->{_event}");
		}
		return;		# return here so we don't calculate the 'won/lost' points below
	}

	# Assign bonus points to all team members based on specific trigger
	$self->plrbonus($trigger, 'enactor_team', $winners, 'victim_team', $losers);
	
	if ($trigger ne 'terrorists_win' and $trigger ne 'cts_win') {
		# allow both teams to receive points for a generic win event as
		# well as the more specific trigger above.
		$self->plrbonus($trigger, 'enactor_team', $winners, 'victim_team', $losers);
	}

	# make sure the bomb exploding event is triggered if needed
	if ($bombed) {
		my $p = $self->{cs_bombplanter};
		if (ref $p) {
			$p->action_bomb($self, 'exploded', $m, $props);
			$m->action_bomb($self, 'exploded', $p, $props);
			# give the bomber some bonus points
			$self->plrbonus('target_bombed',
				'enactor', $p,
				'enactor_team', $winners,
				'victim_team', $losers
			);
			undef $self->{cs_bombplanter};
		}
	}
	
	# update map stats
	$m->action_teamwon($self,  $trigger, $team, $props);
	$m->action_teamlost($self, $trigger, $team, $props);

	# update stats for online players
	$_->action_teamwon($self,  $trigger, $team, $m, $props) for @$winners;
	$_->action_teamlost($self, $trigger, $team, $m, $props) for @$losers;

}

sub event_cs_teamscore {
	my ($self, $timestamp, $args) = @_;
	my ($team, $score, $totalplrs, $props) = @$args;
	return;

#	$self->info("$team scored $score with $totalplrs players\n");
}

1;
