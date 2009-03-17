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
package PS::Game::halflife::l4d;

use strict;
use warnings;
use base qw( PS::Game::halflife );

use util qw( :net );

our $VERSION = '4.00.' . (('$Rev$' =~ /(\d+)/)[0] || '000');

#sub init { 
#	my $self = shift;
#	$self->SUPER::init;
##	$self->{l4d_} = {};
#	return $self;
#}

sub get_role { undef }

sub event_kill {
	my ($self, $timestamp, $args) = @_;
	my ($killer, $victim, $weapon, $propstr) = @$args;
	my $k = $self->get_plr_l4d($killer);
	my $v = $self->get_plr_l4d($victim);
	return unless ref $k && ref $v;
	return unless $self->minconnected;
	#return if $self->isbanned($k) or $self->isbanned($v);

	my $m = $self->get_map;
	my $w = $self->get_weapon($weapon);
	my $kr = $self->get_role($k->role);
	my $vr = $self->get_role($v->role);
	my $props = $self->parseprops($propstr);

	$k->action_kill($self,  $v, $w, $m, $props);	# record a kill for the KILLER
	$w->action_kill($self,  $k, $v, $m, $props);	# record a kill for the WEAPON
	$m->action_kill($self,  $k, $v, $w, $props);	# record a kill for the MAP
	$v->action_death($self, $k, $w, $m, $props);	# record a death for the VICTIM

	my $skill_handled = 0;
	if ($self->can('mod_event_kill')) {
		$skill_handled = $self->mod_event_kill($k, $v, $w, $m, $kr, $vr, $props);
	}
	$self->calcskill_kill_func($k, $v, $w) unless $skill_handled;

	#if ($self->conf->main->save_plr_on_kill) {
	#	# if we're configured for up-to-the-second real-time stats
	#	# then we need to quick save these players.
	#	$k->quick_save;
	#       $v->quick_save;
	#}

	# automatically update player ranks after X number of kills or X game
	# time minutes have elapsed.
	if (++$self->{rank_kills} >= $self->{rank_kills_threshold} or
	    time - $self->{rank_time} >= $self->{rank_time_threshold}) {
		$self->update_plrs;
		$self->{rank_kills} = 0;
		$self->{rank_time} = time;
	}
}

# override default event so we can reset per-log variables
sub event_logstartend {
	my ($self, $timestamp, $args) = @_;
	my ($startedorclosed) = @$args;
	$self->SUPER::event_logstartend($timestamp, $args);

	return unless lc $startedorclosed eq 'started';

	# reset some tracking vars
	#undef $self->{$_} for qw( );
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

	} elsif ($trigger =~ /^(time|latency|amx_|game_idle_kick|camped)/) {
		# extra statsme / amx triggers
		$p->action_misc($self, $trigger, $props);

	} else {
		if ($self->{report_unknown}) {
			$self->warn("Unknown player trigger '$trigger' from src $self->{_src} line $self->{_line}: $self->{_event}");
		}
	}

}

# reset tracking vars at the start of each round.
#sub event_round {
#	my $self = shift;
#	undef $self->{...};
#	$self->SUPER::event_round(@_);
#}

sub event_teamtrigger {
	my ($self, $timestamp, $args) = @_;
	my ($team, $trigger, $propstr) = @$args;
	my $m = $self->get_map;
	my $infected = $self->get_online_plrs('infected');
	my $survivor = $self->get_online_plrs('survivor');
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
		
		$winners = $infected;
		$losers = $survivor;

	} elsif ($trigger eq 'cts_win' ||
		 $trigger eq 'bomb_defused' ||
		 $trigger eq 'target_saved' ||
		 $trigger eq 'all_hostages_rescued' ||
		 $trigger eq 'vip_escaped' ||
		 $trigger eq 'cts_preventescape' ||
		 $trigger eq 'terrorists_not_escaped') {
		
		$winners = $survivor;
		$losers = $infected;
		
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

	# update map stats
	$m->action_teamwon($self,  $trigger, $team, $props);
	$m->action_teamlost($self, $trigger, $team, $props);

	# update stats for online players
	$_->action_teamwon($self,  $trigger, $team, $m, $props) for @$winners;
	$_->action_teamlost($self, $trigger, $team, $m, $props) for @$losers;

}

#sub event_cs_teamscore {
#	my ($self, $timestamp, $args) = @_;
#	my ($team, $score, $totalplrs, $props) = @$args;
#	return;
#
##	$self->info("$team scored $score with $totalplrs players\n");
#}

# L4D has a longer player signature for most events (Except the basic connect
# and enter events)
sub get_plr_l4d {
	my ($self, $plrsig) = @_;
	my ($p,$str,$team,$guid,$uid,$name,$ipaddr,$area,$loc,$num,$status,$role);

	# Return the cached player via the player sig, if previously cached.
	if ($p = $self->plrcached($plrsig)) {
		#warn "* CACHE(eventsig): \"$p\"\n"; # == \"" . $p->eventsig . "\"\n";
		return $p;
	}

	# make a copy, since we'll be striping it apart
	$str = $plrsig;

	# Player Name
	# <uid>
	# <STEAM_ID>
	# <team>
	# <role>
	# <ALIVE> | <DEAD>
	# <100+0> | <0> | <-1>
	# <setpos_exact 1633.86 1071.27 340.03; setang 9.21 62.88 0.00>
	# <Area 28108>

	$area = substr($str, rindex($str,'<'), 128, '');
	$area = substr($area, 1, -1);

	$loc = substr($str, rindex($str,'<'), 128, '');
	$loc = substr($area, 1, -1);

	# I don't know what this number means yet...
	$num = substr($str, rindex($str,'<'), 128, '');
	$num = substr($num, 1, -1);

	$status = substr($str, rindex($str,'<'), 128, '');
	$status = substr($status, 1, -1);

	$role = substr($str, rindex($str,'<'), 128, '');
	$role = $self->role_normal(substr($role, 1, -1));

	$team = substr($str, rindex($str,'<'), 128, '');
	$team = $self->team_normal(substr($team, 1, -1));

	# Note: steamid could be STEAM_ID_PENDING or BOT
	$guid = substr($str, rindex($str,'<'), 128, '');
	$guid = substr($guid, 1, -1);
	$guid =~ s/^STEAM_\d://;	# strip the leading STEAM_x prefix

	# UID is unique to each player until a server restarts
	$uid = substr($str, rindex($str,'<'), 128, '');
	$uid = substr($uid, 1, -1);

	# The rest of the string is the full player name. Trim the string, since
	# the HLDS engine doesn't do it.
	$name = $str;
	$name =~ s/^\s+//;
	$name =~ s/\s+$//;
	$name = 'unnamed' if $name eq '';	# do not allow blank names

	# try to determine this player's current IP address based on their UID
	# which was cached in the "connect" event.
	$ipaddr = $self->ipcached($uid) || 0;

	if ($guid eq 'BOT') {
		# For BOTS: replace STEAMID's with the player name otherwise all
		# bots will be combined into the same STEAMID
		return undef if $self->conf->main->ignore_bots;
		# limit the total characters (128 - 4)
		$guid = "BOT_" . uc substr($name, 0, 124);
	} elsif ($guid eq '') {
		# If the plr has no GUID then its an 'Infected' mob that is
		# computer controlled.
		#return undef;
	}


	# lookup the alias for the player uniqueid
	if ($self->{uniqueid} eq 'guid') {
		$guid = $self->get_plr_alias($guid);
	} elsif ($self->{uniqueid} eq 'name') {
		$name = $self->get_plr_alias($name);
	} elsif ($self->{uniqueid} eq 'ipaddr') {
		$ipaddr = ip2int($self->get_plr_alias(int2ip($ipaddr)));
	}

	# * The signature potentially identifies a unique player in the DB
	my $sig = {
		name => $name,		# *
		guid => $guid,		# *
		ipaddr => $ipaddr,	# *
		eventsig => $plrsig,
		team => $team,
		uid => $uid
	};

	return $self->_get_plr($sig, $plrsig);
}


# The LFD engine changes the log file format for "Source" games, so we must
# override them here for halflife. Thankfully, this new engine uses filenames
# with the full year and time on it, so its very easy to sort w/o worrying about
# end-of-year problems. The IP portion of the filename helps keep different
# 'forks' in a server separate (thank you Valve!)

# filename: L192.168.1.1_27015_200811062306_001.log
# format:    IPADDR      PORT  YYYYMMDDHHmm idx
sub logcompare { 
	#my ($self, $x, $y) = @_; 
	return lc $_[1] cmp lc $_[2];
}

1;
