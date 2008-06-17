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
#	SOLDAT logs are based on the Half-Life logging standard perfectly.
#
package PS::Game::soldat;

use strict;
use warnings;
use base qw( PS::Game::halflife );

use util qw( :net );

our $VERSION = '1.00.' . (('$Rev$' =~ /(\d+)/)[0] || '000');


sub _init { 
	my $self = shift;
	$self->SUPER::_init;
	
	# load halflife:cstrike events since soldat uses the same events
	$self->load_events('halflife','');
	$self->load_events('halflife','cstrike');
	
	return $self;
}

sub get_role { undef }
sub has_mod_tables { 0 }

# override default event so we can reset per-log variables
sub event_logstartend {
	my ($self, $timestamp, $args) = @_;
	my ($startedorclosed) = @$args;
	$self->SUPER::event_logstartend($timestamp, $args);

	return unless lc $startedorclosed eq 'started';

	# reset some tracking vars
#	map { undef $self->{$_} } qw( ... );
}

sub event_plrtrigger {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $trigger, $propstr) = @$args;
	my $p1 = $self->get_plr($plrstr) || return;
	$self->_do_connected($timestamp, $p1) unless $p1->{_connected};
	return if $self->isbanned($p1);

	$p1->{basic}{lasttime} = $timestamp;
	return unless $self->minconnected;
	my $m = $self->get_map;

	$trigger = lc $trigger;
	$self->plrbonus($trigger, 'enactor', $p1);
	if ($trigger eq 'weaponstats' or $trigger eq 'weaponstats2') {
		$self->event_weaponstats($timestamp, $args);

	} elsif ($trigger eq 'address') {	# PIP 'address' (ipaddress) events
		my $props = $self->parseprops($propstr);
		return unless $p1->{uid} and $props->{address};
		$self->{ipcache}{$p1->{uid}} = ip2int($props->{address});

	#} elsif ($trigger eq 'something') {
	#	# I'm not sure what plugin provides this trigger...

	} else {
		if ($self->{report_unknown}) {
			$self->warn("Unknown player trigger '$trigger' from src $self->{_src} line $self->{_line}: $self->{_event}");
		}
	}

}


sub event_teamtrigger {
	my ($self, $timestamp, $args) = @_;
	my ($team, $trigger, $props) = @$args;
	return unless $self->minconnected;
	my $m = $self->get_map;
	my $ct = $self->get_team('ct', 1);
	my $terr = $self->get_team('terrorist', 1);
	my ($p1, $p2, $ctvar, $terrvar, $enactor_team, $victim_team);

	$team = lc $team;
#	$team =~ tr/ /_/;
#	$team =~ tr/a-z0-9_//cs;
	$trigger = lc $trigger;

	if ($trigger eq "terrorists_win") {
		$terrvar  = 'terroristwon';
		$ctvar = 'ctlost';
		$enactor_team = $terr;
		$victim_team = $ct;

	} elsif ($trigger eq "cts_win") {
		$terrvar  = 'terroristlost';
		$ctvar = 'ctwon';
		$enactor_team = $ct;
		$victim_team = $terr;

	} else {
		if ($self->{report_unknown}) {
			$self->warn("Unknown team trigger '$trigger' from src $self->{_src} line $self->{_line}: $self->{_event}");
		}
		return;		# return here so we don't calculate the 'won/lost' points below
	}

	$self->plrbonus($trigger, 'enactor_team', $enactor_team, 'victim_team', $victim_team);

	# assign won/lost points ...
	$m->{mod}{$ctvar}++;
	$m->{mod}{$terrvar}++;
	foreach (@$ct) {
		$_->{mod}{$ctvar}++;
		$_->{mod_maps}{ $m->{mapid} }{$ctvar}++;		
	}
	foreach (@$terr) {
		$_->{mod}{$terrvar}++;
		$_->{mod_maps}{ $m->{mapid} }{$terrvar}++;		
	}
}

# prevent 'unknown event' warning
sub event_cs_teamscore { }

sub team_normal {
	my ($self, $team) = @_;
	$team = lc $team;
	if ($team eq 'a' or $team eq 'ct') { 	# 'ct' is legacy from early logs
		return 'alpha';
	} else {				# anything else is bravo
		return 'bravo';
	}
	return team;
}

sub logsort {
	my $self = shift;
	my $list = shift;
	return [ sort { $a cmp $b } @$list ];
}

1;
