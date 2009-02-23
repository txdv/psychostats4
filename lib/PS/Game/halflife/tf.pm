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

	return undef;
}

sub event_plrtrigger {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $trigger, $plrstr2, $propstr) = @$args;
	my $p = $self->get_plr($plrstr);
	my $m = $self->get_map;
	my $props = $self->parseprops($propstr);

	return unless ref $p;
	#return if $self->isbanned($p);
	#return unless $self->minconnected;

	$trigger = lc $trigger;
	$self->plrbonus($trigger, 'enactor', $p);
	if ($trigger eq 'weaponstats' or $trigger eq 'weaponstats2') {
		$self->event_weaponstats($timestamp, $args);

	} elsif ($trigger eq 'address') {	# PIP 'address' (ipaddress) events
		$self->add_ipcache($p->uid, ip2int($props->{address}), $timestamp);

	} elsif ($trigger eq 'kill assist') {
		my $p2 = $self->get_plr($plrstr2);
		if ($p2) {
			my $r = $self->get_role($p2->role, $p2->team);
			$p->action_kill_assist($self, $p2, $self->{last_kill_weapon}, $m, $props);
			$r->action_kill_assist($self, $p2, $self->{last_kill_weapon}, $m, $props) if $r;
			$self->calcskill_killassist_func($p, $p2, $self->{last_kill_weapon});
		}
		
	} elsif ($trigger eq 'flagevent') {
		my $action = $props->{event};
		$action =~ s/\s+//g; # remove spaces
		$p->action_flag($self, $action, $m, $props);
		$self->plrbonus('flag_' . $action,
				'enactor', 	$p,
				'enactor_team', $self->get_online_plrs($p->team),
				'victim_team',  $self->get_online_plrs($p->team eq 'red' ? 'blue' : 'red')
		);

	#} elsif ($trigger eq 'killedobject') {
	#	my $props = $self->parseprops($propstr);
	#	$p2 = $props->{objectowner} ? $self->get_plr($props->{objectowner}) : undef;
	#	if ($props->{object} eq "OBJ_DISPENSER") {
	#		#@vars = ( 'dispenserdestroy' );
	#
	#	} elsif ($props->{object} eq "OBJ_SENTRYGUN") {
	#		#@vars = ( 'sentrydestroy' );
	#		# do not give points to the object owner if they kill their own object
	#		if (!$p2 or $p->plrid != $p2->plrid) {
	#			$self->plrbonus('killedsentry', 'enactor', $p);	# depreciated; REMOVEME
	#			$self->plrbonus('sentrydestroy', 'enactor', $p);
	#		}
	#
	#	} elsif ($props->{object} eq "OBJ_TELEPORTER_ENTRANCE" || $props->{object} eq "OBJ_TELEPORTER_EXIT") {
	#		#@vars = ( 'teleporterdestroy' );
	#		# do not give points to the object owner if they kill their own object
	#		$self->plrbonus('teleporterdestroy', 'enactor', $p) if !$p2 or $p->plrid != $p2->plrid;
	#
	#	} elsif ($props->{object} eq "OBJ_ATTACHMENT_SAPPER") {
	#		#@vars = ( 'sapperdestroy' );
	#		# do not give points to the object owner if they kill their own object
	#		$self->plrbonus('sapperdestroy', 'enactor', $p) if !$p2 or $p->plrid != $p2->plrid;
	#
	#	}
	#	#push(@vars, 'itemsdestroyed');
	#
	#} elsif ($trigger eq 'revenge') {
	#	#@vars = ( 'revenge' );
	#	$p2 = $self->get_plr($plrstr2);
	#	$self->plrbonus($trigger, 'victim', $p2) if $p2;	# 'enactor' will get their bonus below...
	#
	#} elsif ($trigger eq 'builtobject') {
	#	#@vars = ( 'itemsbuilt' );
	#	# player built something... good for them.
	#
	#} elsif ($trigger eq 'chargedeployed') {
	#	# ... something to do with the medic charge gun thingy ...
	#	#@vars = ( 'chargedeployed' );
	#
	#} elsif ($trigger eq 'domination') {
	#	#@vars = ( 'dominations' );
	#	$p2 = $self->get_plr($plrstr2);
	#	$self->plrbonus($trigger, 'victim', $p2) if $p2;	# 'enactor' will get their bonus below...
	#
	#} elsif ($trigger eq 'captureblocked') {
	#	#@vars = ( $p->{team} . 'captureblocked', 'captureblocked' );
	#
	} elsif ($trigger =~ /^(time|latency|amx_|game_idle_kick|camped)/) {
		# extra statsme / amx triggers
		$p->action_misc($self, $trigger, $props);
		
	} else {
		if ($self->{report_unknown}) {
			$self->warn("Unknown player trigger '$trigger' from src $self->{_src} line $self->{_line}: $self->{_event}");
		}
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

		# old style (player "") (player "") ...
		if (ref $props->{player}) {			# array of player strings
			push(@$list, @{$props->{player}});
		} elsif (defined $props->{player}) {		# 1 player string
			push(@$list, $props->{player});
		}

		# new style (player1 "") (player2 "") ...
		while (exists $props->{'player' . $i}) {
			push(@$list, $props->{'player' . $i++});
		}

		return unless @$list;
		foreach my $plrstr (@$list) {
			my $p1 = $self->get_plr($plrstr) || next;
#			my $r1 = $self->get_role($p1->{roleid}, $team);
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
		my $team1 = $self->get_online_plrs($team);
		my $team2 = $self->get_online_plrs($team eq 'red' ? 'blue' : 'red');
		$self->plrbonus($trigger, 'enactor', $players, 'enactor_team', $team1, 'victim_team', $team2);
	} elsif ($trigger eq 'intermission_win_limit') {
		# uhm.... what?
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
		my $winners = $self->get_online_plrs($team);
		my $losers  = $self->get_online_plrs($team2);
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

sub event_logstartend {
	my ($self, $timestamp, $args) = @_;
	$self->SUPER::event_logstartend($timestamp, $args);
	$self->{last_kill_weapon} = undef;
	$self->{last_kill_role} = undef;
}

sub has_mod_tables { 1 }
sub has_roles { 1 }

1;
