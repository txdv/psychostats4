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
package PS::Game::halflife;

use strict;
use warnings;
use base qw( PS::Game );

use util qw( :net :date );
use PS::SourceFilter;
use PS::Plr;
use Encode qw( encode_utf8 decode_utf8 );
use Time::Local qw( timegm timegm_nocheck );

our $VERSION = '4.00.' . (('$Rev$' =~ /(\d+)/) || '000')[0];

sub init {
	my $self = shift;
	$self->SUPER::init;

	# Keep track of the last timestamp prefix string and time.
	# This allows the event loop to reuse the previous time value 
	# w/o having to do a regex or call timegm().
	$self->{last_prefix} = "";
	$self->{last_timestamp} = 0;
	$self->{last_min} = undef;
	$self->{last_hour} = undef;
	$self->{last_day} = undef;
	$self->{min} = undef;
	$self->{hour} = undef;
	$self->{day} = undef;
	
	$self->{rank_time} = time;
	$self->{rank_kills} = 0;
	$self->{rank_time_threshold} = 30;	# TODO: make these configurable
	$self->{rank_kills_threshold} = 5000;	# TODO: ...

	# keep track of when a round started.
	$self->{roundstart} = 0;
	$self->{versus} = {};		# track "versus" stat (1v1, v2, v3, v4, v5)

#	$self->{bans}{ipaddr} = {};	# Current 'permanent' bans from the current log by IP ADDR
#	$self->{bans}{worldid} = {};	# ... by guid / steamid

	# keep track of objects in memory
	$self->{maps} = {};		# loaded map objects, keyed on id
	$self->{weapons} = {};		# loaded weapon objects, keyed on id

	$self->init_online;
	$self->init_plrcache;
	$self->init_ipcache;

	#$self->{auto_plr_bans} = $self->conf->auto_plr_bans;

	# default map will be determined from the log source, since each log
	# source can have a different default.
	$self->{curmap} = 'unknown';

	return $self;
}

# handle the event that comes in from the Feeder (log line)
#;;; my $last = time;
sub event {
	my $self = shift;
	my ($event, $feed) = @_;
	my ($prefix, $timestamp);

	$event = decode_utf8($event);		# HL logs are UTF-8 encoded

	# Ignore lines that are not complete (no newline). An incomplete line
	# usually means the last line of the log file is corrupt (server
	# shutdown or crashed).
	if (chomp($event) == 0) {
		$self->warn("Ignoring incomplete line in " . $feed->curlog . " on line " . $feed->curline);
		return;
	}
	
	# verify the prefix and remove it from the event
	return if length($event) < 25;		# "123456789*123456789*12345"
	$prefix = substr($event, 0, 25, '');	# "L MM/DD/YYYY - hh:mm:ss: "

	$self->{_event} = $event;
	$self->{_src} = $feed->curlog;
	$self->{_line} = $feed->curline;

	# Avoid performing the prefix regex as much as possible (potential
	# performance gain). In busy logs the timestamp won't change for several
	# events per second.
	if ($prefix eq $self->{last_prefix}) {
		$timestamp = $self->{last_timestamp};
	} else {
		if ($prefix !~ /^L (\d\d)\/(\d\d)\/(\d\d\d\d) - (\d\d):(\d\d):(\d\d)/) {
			if ($self->conf->global->errlog->log_report_timestamps) {
				# do not warn on lines with "unable to contact
				# the authentication server, 31)."
				$self->warn("Invalid timestamp from source " . $feed->curlog . " line " . $feed->curline . ": $event")
					unless substr($prefix,0,6) eq 'unable';
			}
			return;
		}

		$timestamp = timegm_nocheck($6, $5, $4, $2, $1-1, $3-1900) - ($feed->gmt_offset || 0);
		#my $localtime = Time::Local::timelocal($6, $5, $4, $2, $1-1, $3-1900);
		#print "$prefix == $6,$5,$4,$2,$1,$3 == $timestamp (" . ($feed->gmt_offset || 0) . ")\n";
		#print "$prefix == " . localtime($localtime) . " (localtime) $localtime\n";
		#print "$prefix == " . gmtime($timestamp) . " (GMT) $timestamp\n";

		$self->{last_timestamp} = $timestamp;
		$self->{last_prefix} = $prefix;
		$self->{last_min} = $self->{min};
		$self->{last_hour} = $self->{hour};
		$self->{last_day} = $self->{day};
		$self->{min} = $5;
		$self->{hour} = $4;
		$self->{day} = $2;
	}
	$self->{timestamp} = $timestamp;

	# SEARCH FOR A MATCH ON THE EVENT USING OUR LIST OF REGEX'S
	# If a match is found we dispatch the event to the proper event method
	# 'event_{match}' (or to its configured alias).
	my ($re, $params) = &{$self->{evregex}}($event);			# finds an EVENT match (fast)
	if ($re) {
		return if $self->{evconf}{$re}{ignore};				# should this match be ignored?
		$self->{re_match} = $re;					# keep track of the event that matched
		my $func = 'event_' . ($self->{evconf}{$re}{alias} || $re);	# use specified $event or 'event_$re'
		$self->$func($timestamp, $params);				# call event handler
	} elsif ($self->conf->global->errlog->log_report_unknown) {
		$self->warn("Unknown event was ignored from source " . $feed->curlog . " line " . $feed->curline . ": $event");
	}

=pod
	if (defined $self->{last_min} and $self->{last_min} != $self->{min}) {
		# every minute collect some player data
		$self->{last_min} = $self->{min};
		$self->{_plrtot} += $self->get_online_count;
		$self->{_plrcnt}++;
	}
	if (defined $self->{last_hour} and ($self->{last_hour} != $self->{hour} or $self->{last_day} != $self->{day})) {
		print "$self->{hour}: " . int($self->{_plrtot} / $self->{_plrcnt}) . "\n";
		$self->get_map->hourly('online', int($self->{_plrtot} / $self->{_plrcnt}));
		$self->{_plrcnt} = 0;
		$self->{_plrtot} = 0;
		$self->{last_hour} = $self->{hour};
		$self->{last_day} = $self->{day};
	}
=cut
}

# "Player<uid><STEAM_ID><TEAM>" attacked "Player<uid><STEAM_ID><TEAM>" with "weapon" (properties...)
sub event_attacked {
	my ($self, $timestamp, $args) = @_;
	my ($killer, $victim, $weapon, $propstr) = @$args;
	my $k = $self->get_plr($killer);
	my $v = $self->get_plr($victim);
	return unless ref $k && ref $v;
	return unless $self->minconnected;

	#return if $self->isbanned($p1) or $self->isbanned($p2);

	#my $r1 = $self->get_role($p1->{role}, $p1->{team});
	#my $r2 = $self->get_role($p2->{role}, $p1->{team});

	my $w = $self->get_weapon($weapon);
	my $m = $self->get_map;
	my $props = $self->parseprops($propstr);

	$w->action_attacked($self, $k, $v, $m, $props);
	$k->action_attacked($self, $v, $w, $m, $props);
	$v->action_injured($self,  $k, $w, $m, $props);
}

# "Player<uid><STEAM_ID><TEAM>" changed name to "name"
sub event_changed_name {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $name) = @$args;
	my $p = $self->get_plr($plrstr);
	my $props = $self->parseprops;
	return unless ref $p;

	# make sure the name is not all spaces (HLDS allows this)
	$name =~ s/^\s+//;
	$name =~ s/\s+$//;
	$name = 'unnamed' if $name eq '';

	# do nothing if the name wasn't changed
	return if $p->name eq $name;
	
	# If we're tracking players by NAME then we need to save the current
	# player record and start a new one in their place.
	if ($self->{uniqueid} eq 'name') {
		my $m = $self->get_map;
		
		# save and disconnect the current player entity
		$p->action_disconnect($self, $m, $props);
		$p->save($self);

		# start a new player entity using the original as the base.
		# stats are not cloned/transfered to the new entity.
		my $p2 = $p->clone;
		$p2->reset_ids;		# do not carry over player ids from clone
		$p2->name($name);	# change players name
		$p2->ipaddr($p->ipaddr);
		$p2->guid($p->guid);

		# remove the original player from memory
		$self->del_plrcache($p);
		$self->plr_offline($p);		# mark old player OFFLINE
		
		# cache the new player into memory
		$self->add_plrcache($p2);
		$self->plr_online($p2);		# mark new player ONLINE

	} else {
		$self->del_plrcache($p);
		$p->action_changed_name($self, $name, $props);
		$self->add_plrcache($p);
	}
}

# "Player<uid><STEAM_ID><TEAM>" changed role to "role"
sub event_changed_role {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $role) = @$args;
	my $p = $self->get_plr($plrstr);
	my $r = $self->get_role($role, $p->team) || return;
	my $props = $self->parseprops;
	return unless ref $p;

	$p->action_changed_role($self, $r, $props);
}

# "Player<uid><STEAM_ID><TEAM>" say "chat message" (dead)
# "Player<uid><STEAM_ID><TEAM>" say_team "chat message" (dead)
sub event_chat {
	my ($self, $timestamp, $args) = @_;
	return unless $self->conf->plr_chat_max > 0;
	my ($plrstr, $teamonly, $msg, $propstr) = @$args;
	my $p = $self->get_plr($plrstr);
	my $props = $self->parseprops($propstr);
	return unless ref $p;
	# hell, let banned players chat it up!
	#return if $self->isbanned($p1);

	$p->action_chat($self, $msg, $teamonly, $props);
}

# "Player<uid><STEAM_ID_PENDING><>" connected, address "1.2.3.4:12345"
sub event_connected {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $ipstr, $propstr) = @$args;
	my $p = $self->get_plr($plrstr);
	my $props = $self->parseprops($propstr);
	my $ip = lc((split(/:/,$ipstr,2))[0]);
	my $m = $self->get_map;
	return unless ref $p;

	# normalize the IP and make sure its a real IP.
	$ip = '127.0.0.1' if $ip !~ /^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/;
	$ip = ip2int($ip);
	
	$p->action_connected($self, $ip, $props);
	$m->action_connected($self, $p, $props);

	# save the IP address
	$self->add_ipcache($p->uid, $ip, $timestamp);
}

# "Player<uid><STEAM_ID><>" STEAM USERID validated
sub event_connected_steamid {
	# The regex definition is set to 'ignore'. We don't need this event to
	# properly identify a player, even those that have the STEAM_ID_PENDING
	# guid temporarily.
	#my ($self, $timestamp, $args) = @_;
	#my ($plrstr, $validated) = @$args;
	#my $p = $self->get_plr($plrstr);
	#return unless ref $p;
	#;;; warn "$p was validated\n";
}

# "Player<uid><STEAM_ID><TEAM>" disconnected
sub event_disconnected {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr) = @$args;
	my $p = $self->get_plr($plrstr);
	my $props = $self->parseprops;
	return unless ref $p;
	
	$p->action_disconnect($self, $self->get_map, $props);

	# remove the player from our cache since they're no longer online
	$self->del_plrcache($p);

	# scan the player for a valid clantag
	if ($self->conf->clantag_detection and !$p->clanid) {
		my ($tag, $clan) = $self->scan_for_clantag($p);
		if ($tag and $clan->{clanid}) {
			$p->clanid($clan->{clanid});
		}
	}

	# save any pending stats they might have.
	$p->save($self);

	# mark this player offline
	$self->plr_offline($p);

	# free memory	
	undef $p;
}

# "Player<uid><STEAM_ID><TEAM>" entered the game
sub event_entered_game {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $propstr) = @$args;
	my $p = $self->get_plr($plrstr);
	my $m = $self->get_map;
	my $props = $self->parseprops($propstr);
	return unless ref $p;

	$p->action_entered($self, $m, $props);
	
	# consider the player ONLINE and actively in the game.
	$self->plr_online($p);
}

# "Player<uid><STEAM_ID><>" joined team "TEAM"
sub event_joined_team {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $team, $propstr) = @$args;
	my $p = $self->get_plr($plrstr);
	return unless ref $p;

	# do nothing if the player changed to the same team. 
	# this occurs at least on CSTRIKE servers. Not sure about others.
	$team = $self->team_normal($team);
	return if $p->team eq $team;

	my $m = $self->get_map;
	my $props = $self->parseprops($propstr);

	$p->action_joined_team($self, $team, $m, $props);
}

# "Player<uid><STEAM_ID><TEAM>" killed "Player<uid><STEAM_ID><TEAM>" with "weapon" (headshot)
sub event_kill {
	my ($self, $timestamp, $args) = @_;
	my ($killer, $victim, $weapon, $propstr) = @$args;
	my $k = $self->get_plr($killer);
	my $v = $self->get_plr($victim);
	return unless ref $k && ref $v;
	return unless $self->minconnected;
	#return if $self->isbanned($k) or $self->isbanned($v);

	my $m = $self->get_map;
	my $w = $self->get_weapon($weapon);
	my $kr = $self->get_role($k->role, $k->team);
	my $vr = $self->get_role($v->role, $v->team);
	my $props = $self->parseprops($propstr);

	# Run the action on each affected entity...
	$v->action_death($self,  $k,     $w, $m, $props);
	$k->action_kill($self,       $v, $w, $m, $props);
	$w->action_kill($self,   $k, $v,     $m, $props);
	$m->action_kill($self,   $k, $v, $w,     $props);
	$kr->action_kill($self,  $k, $v, $w, $m, $props) if $kr;
	$vr->action_death($self, $v, $k, $w, $m, $props) if $vr;

	# track "versus" information, but only if teams exist. It makes no sense
	# to track this w/o a team (or on team kills)
	my $kt = $k->team;
	if ($kt) {
		if ($kt ne $v->team) {
			if (!$self->{versus}{plr} or $self->{versus}{plr}->uid != $k->uid) {
				$self->{versus}{plr} = $k;
				$self->{versus}{kills} = 1;
			} else {
				$self->{versus}{kills}++;
			}
			#warn "VERSUS: " . $k->name . " 1v" . $self->{versus}{kills} . "\n";
		} else {
			# reset it on team kills
			%{$self->{versus}} = ();
		}
	}

	my $skill_handled = 0;
	if ($self->can('mod_event_kill')) {
		$skill_handled = $self->mod_event_kill($k, $v, $w, $m, $kr, $vr, $props);
	}
	$self->calcskill_kill_func($k, $v, $w) unless $skill_handled;

	#if ($self->conf->save_plr_on_kill) {
	#	# if we're configured for up-to-the-second real-time stats
	#	# then we need to quick save these players.
	#	$k->quick_save;
	#       $v->quick_save;
	#}

	# automatically update player ranks after X number of kills or X game
	# time minutes have elapsed.
	#if (++$self->{rank_kills} >= $self->{rank_kills_threshold} or
	#    time - $self->{rank_time} >= $self->{rank_time_threshold}) {
	#	$self->update_plrs;
	#	$self->{rank_kills} = 0;
	#	$self->{rank_time} = time;
	#}
}

# Log file started (file "logs\L0116028.log") (game "c:\valve\orangebox\tf") (version "3351")
sub event_logstartend {
	my ($self, $timestamp, $args) = @_;
	my ($startedorclosed) = @$args;

	# A log 'started' event is almost ALWAYS guaranteed to happen (unlike
	# 'closed' events) we use this time to close out any previous maps and
	# save all current player data in memory
	return unless lc $startedorclosed eq 'started';

	$self->save(1);
	$self->init_online;		# consider no one online
	$self->init_plrcache;		# remove all plr caches
	$self->clean_ipcache;		# remove stale IP's from cache
}

# Started map "mapname" (CRC "794707506")
sub event_mapstarted {
	my ($self, $timestamp, $args) = @_;
	my ($startorload, $mapname, $propstr) = @$args;

	# ignore 'map loaded' events, we only care about 'map started' events
	return unless lc $startorload eq 'started';

	my $props = $self->parseprops($propstr);
	my $m;

	# save any current maps in memory (should only be 1 map)
	foreach my $map (keys %{$self->{maps}}) {
		$m = $self->get_map($map);
		$m->action_mapended($self, $props);
		$m->save;
		undef $m;
	}
	$self->{maps} = {};

	# start up the new map in memory
	$self->{curmap} = $mapname;
	$m = $self->get_map;
	
	$m->action_mapstarted($self, $props);
}

# all games will end up overridding this event.
sub event_plrtrigger { }
sub event_teamtrigger { }

# standard round start/end for several mods
sub event_round {
	my ($self, $timestamp, $args) = @_;
	my ($trigger, $propstr) = @$args;
	my $m = $self->get_map;
	my $props = $self->parseprops($propstr);
	$trigger = lc $trigger;
	;;; $self->debug7("ROUND STARTED.", 0);

	#my $plrs = $self->get_online_plrs;
	#;;; warn @$plrs . " players are online\n";
	#;;; warn join("\n", sort map { "\t$_" } grep { $_ =~ /13242553/ } @$plrs) . "\n";

	$m->action_round($self, $trigger, $props);
	foreach my $p ($self->get_online_plrs) {
		$p->action_round($self, $trigger, $m, $props);
	}

	if ($trigger eq 'round_start') {
		# not sure I even have a use for the round start timestamp
		#warn ">>>>> Round Start " . date('%H:%i:%s', $timestamp) . "\n";
		$self->{roundstart} = $timestamp;

		# reset "versus" player information ...
		%{$self->{versus}} = ();
	} elsif ($trigger eq 'round_end') {
		#warn "<<<<< Round End " . date('%H:%i:%s', $timestamp) . "\n";
		# record the "versus" stat for the last plr that killed
		if ($self->{versus}{plr}) {
			my $p = $self->{versus}{plr};
			my @kteam = grep { !$_->is_dead } $self->get_online_plrs($p->team);
			# The stat only counts when the killer is the only one
			# left alive on their team.
			if (@kteam == 1) {
				#warn ">>>>> " . $p->name . " succeeded in 1v" . $self->{versus}{kills} . "\n";
				$p->action_versus($self, $self->{versus}{kills});
			}
			%{$self->{versus}} = ();
		}
	}
	
}

sub event_spatial {
	my ($self, $timestamp, $args) = @_;
	my ($killer, $victim, $weapon, $propstr) = @$args;
	my $k = $self->get_plr($killer) || return;
	my $v = $self->get_plr($victim) || return;
	my $m = $self->get_map;
	my $w = $self->get_weapon($weapon);
	my $props = $self->parseprops($propstr);

	$m->action_spatial($self, $k, $v, $w, $props);
}

sub event_suicide {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $weapon, $propstr) = @$args;
	my $p = $self->get_plr($plrstr);
	return unless ref $p;
	return unless $self->minconnected;
	#return if $self->isbanned($p1);

	$weapon = $self->weapon_normal($weapon);

	# world = changed teams
	# worldspawn = ???
	return if substr($weapon,0,5) eq 'world';
	
	my $props = $self->parseprops($propstr);
	my $m = $self->get_map;
	my $w = $self->get_weapon($weapon);

	$p->action_suicide($self, $m, $w, $props);
	$m->action_suicide($self, $p, $w, $props);
	$w->action_suicide($self, $p, $m, $props);

	# 'suicide' award bonus/penalty for killing yourself (idiot!)
	$self->plrbonus('suicide', 'enactor', $p);
}

# 'statsme' weaponstats and weaponstats2 triggers.
sub event_weaponstats {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $trigger, $propstr) = @$args;
	my $p = $self->get_plr($plrstr);
	my $props = $self->parseprops($propstr);
	return unless ref $p;
	#return if $self->isbanned($p);
	
	my $w = $self->get_weapon($props->{weapon}) || return;

	$p->action_weaponstats($self, $trigger, $w, $props);
	$w->action_weaponstats($self, $trigger, $p, $props);
	#$r->action_weaponstats($self, $trigger, $p, $w, $props);
}

sub event_ban {
	my ($self, $timestamp, $args) = @_;
	my ($type, $plrstr, $duration, $who, $propstr) = @$args;
	return;

	return unless $self->{auto_plr_bans};

	$type = lc $type;
	if (substr($type,0,3) eq 'ban') {		# STEAMID
		my $plr = $self->get_plr($plrstr) || return;
		$self->addban($plr, reason => 'Auto Ban', 'ban_date' => $timestamp);
	} 
}

sub event_unban {
	my ($self, $timestamp, $args) = @_;
	my ($type, $plrstr, $who, $propstr) = @$args;
	return;

	return unless $self->{auto_plr_bans};

	$type = lc $type;
	if ($type eq 'id') {		# STEAMID
		my $plr = $self->get_plr($plrstr) || return;
		$self->unban($plr, 'reason' => 'Auto Unban', 'unban_date' => $timestamp);
	}
}

sub event_plugin {
	my ($self, $timestamp, $args) = @_;
	my ($plugin, $str, $propstr) = @$args;
	return;

#	print "[$plugin] $str\n";

#	if (lc $plugin eq 'statsme') {
#		$self->event_weaponstats($timestamp, [ ]);
#	}
}

sub event_rcon {
	my ($self, $timestamp, $args) = @_;
	my ($bad, $challenge, $pw, $cmd, $ipport) = @$args;
}

sub event_kick {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $who, $propstr) = @$args;
}

sub event_cheated {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr) = @$args;
}

sub event_pingkick {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr) = @$args;
}

sub event_ffkick {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr) = @$args;
}

;;;my ($get_plr_miss,$get_plr_hit,$get_plr_time) = (0,0,time);	# debugging
# parses the player signature string and returns the player object matching it.
# if an invalid player string is given then undef is returned.
sub get_plr {
	my ($self, $plrsig) = @_;
	my ($p,$str,$team,$guid,$uid,$name,$ipaddr);

	# debugging: show cache hit rate
	;;;if (time - $get_plr_time >= 5) {
	;;;	$self->debug3(sprintf("get_plr() cache hit rate: %.2f%%\n", ($get_plr_miss+$get_plr_hit) ? $get_plr_hit / ($get_plr_miss+$get_plr_hit) * 100 : 0), 0);
	;;;	$get_plr_time = time;
	;;;	$get_plr_hit = 0;
	;;;	$get_plr_miss = 0;
	;;;}

	# Return the cached player via the player sig, if previously cached.
	if ($p = $self->plrcached($plrsig)) {
		#warn "* CACHE(eventsig): \"$p\"\n"; # == \"" . $p->eventsig . "\"\n";
		;;;++$get_plr_hit;
		return $p;
	}
	;;;++$get_plr_miss;

	# make a copy, since we'll be striping it apart
	$str = $plrsig;

	# using multiple substr calls to fetch each piece of the player sig is
	# faster then using a single regular expression.
	# start from the end of the string and move back.
	$team = substr($str, rindex($str,'<'), 128, '');
	$team = $self->team_normal(substr($team, 1, -1));

	# Note: steamid could be STEAM_ID_PENDING or BOT
	$guid = substr($str, rindex($str,'<'), 128, '');
	$guid = substr($guid, 1, -1);
	$guid =~ s/^STEAM_//;		# strip the leading STEAM_ prefix
	#$guid =~ s/^STEAM_\d://;	# strip the leading STEAM_x: prefix

	# Ignore the HLTV client.
	# Ignore some plugin(s) that log using <Console> as the guid (tf)
	if ($guid eq 'HLTV' or $guid eq 'Console') {
		return;
	}

	# UID is unique to each player until a server restarts
	$uid = substr($str, rindex($str,'<'), 128, '');
	$uid = substr($uid, 1, -1);
	
	# ignore any players with an UID of -1 or no STEAMID
	# treat $uid as a string to avoid numerical errors due to invalid events
	if (!$guid or $uid eq '-1') {
		;;; $self->debug1("Ignoring invalid player identifier from logsource '$self->{_src}' line $self->{_line}: '$plrsig'",0);
		return;
	}

	# The rest of the string is the full player name. Trim the string, since
	# the HLDS engine doesn't do it.
	$name = $str;
	$name =~ s/^\s+//;
	$name =~ s/\s+$//;
	$name = 'unnamed' if $name eq '';	# do not allow blank names
	
	# try to determine this player's current IP address based on their UID
	# which was cached in the "connect" event.
	$ipaddr = $self->ipcached($uid) || 0;

	# For BOTS: replace STEAMID's with the player name otherwise all bots
	# will be combined into the same STEAMID
	if ($guid eq 'BOT') {
		return if $self->conf->ignore_bots;
		# limit the total characters (128 - 4)
		$guid = "BOT_" . uc substr($name, 0, 124);
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

# internal method for get_plr. Returns an actual PS::Plr object based on the
# signature given.
sub _get_plr {
	my ($self, $sig, $plrsig) = @_;
	my $p;
	
	# Based on their UID the player already existed (something in their
	# signature changed since their last event)
	if ($p = $self->plrcached($sig->{uid}, 'uid')) {
		$self->del_plrcache($p);	# remove old cached signature
		
		# special care must be taken (when we're tracking by name) for
		# signatures that have a different name. I'm not sure how this
		# anomaly occurs. It might be due to dropped streaming packets
		# that cause 'changed_name' events to be missed.
		if ($self->{uniqueid} eq 'name' and $p->name ne $sig->{name}) {
			# mark the original player offline and mark the new
			# player signature as online (see event_changed_name for
			# details).
			$p->action_disconnect($self, $self->get_map, scalar $self->parseprops);
			$p->save($self);

			my $p2 = $p->clone;
			$p2->reset_ids;
	
			$self->plr_offline($p);		# Original plr offline
			$self->plr_online($p2);		# New plr online

			undef $p;
			$p = $p2;
		}
		
		$p->eventsig($plrsig);		# save new sig for caching
		$p->name($sig->{name});
		$p->guid($sig->{guid});
		$p->ipaddr($sig->{ipaddr});
		$p->team($sig->{team});
		$self->add_plrcache($p);	# recache with new signature

	} else {
		# Create a new player using the event signature
		$p = new PS::Plr($sig, @$self{qw( gametype modtype timestamp )});
		if (my $p1 = $self->plrcached($p->id, 'plrid')) {
			# make sure this player isn't in memory already
			#warn "* CACHE: plr cached after new: '$p' == '$p1'\n";
			$self->del_plrcache($p);
			undef $p;
			$p = $p1;
		} else {
			$p->skill( $self->conf->baseskill );
		}

		$self->add_plrcache($p);
		$self->plr_online($p);
	}
	return $p;
}

sub parseprops {
	my ($self, $str, $timestamp) = @_;
	my ($var, $val);     
	my $props = {};
	$str = '' unless defined $str;
	# Find each variable pattern: (variable "value")
	while ($str =~ s/^\s*\((\S+)(?:\s+"([^"]*|.*?(?:<[^>]*>))")?\)//) {
		$var = $1;
		# if "value" doesn't exist the var is a true 'boolean' 
		$val = (defined $2) ? $2 : 1;
		if (exists $props->{$var}) {
			# convert to array if its not already
			$props->{$var} = [ $props->{$var} ] unless ref $props->{$var};
			push(@{$props->{$var}}, $val);		# add to array
		} else {
			$props->{$var} = $val;
		}
	}
	#$props->{game} = $self;
	$props->{timestamp} = $timestamp || $self->{timestamp};
	#$props->{game_event} = $self->{_event};

	# HL2 started recording the hitbox information on attacked events.
	# parse it out here and normalize the property.
	if (exists $props->{hitgroup}) {
		if ($props->{hitgroup} eq 'generic') {
			delete $props->{hitgroup};
		} else {
			$props->{hitgroup} =~ s/\s+//g;		# remove spaces
		}
	}

	return wantarray ? %$props : $props;
}

# normalize a team name
sub team_normal {
	my ($self, $team) = @_;
	$team = lc $team;
	
	#$team =~ s/[^A-Z0-9_]//g;				# remove all non-alphanumeric characters
	$team = 'spectator' if $team eq 'spectators';		# some MODS have a trailing 's'.
	$team = '' if $team eq 'unassigned';			# don't use ZERO

	return $team;
}

# normalize a weapon name
sub weapon_normal {
	my ($self, $weapon) = @_;
	$weapon ||= 'unknown';
	#$weapon = lc $weapon;
	if ($weapon eq 'hegrenade') {
		$weapon = 'grenade'
	} elsif ($weapon eq 'glock') {
		$weapon = 'glock18';
	}
	return $weapon;
}

# sorting method that the Feeder class can use to sort a list of log filenames
# returns a NEW array reference of the sorted logs. Does not change original
# reference.
sub logsort {
	my $self = shift;
	my $list = shift;		# array ref to a list of log filenames
	return [ sort { $self->logcompare($a, $b) } @$list ];
}

# compare method that can compare 2 log files for the game and return (-1,0,1)
# depending on their order smart logic tries to account for logs from a previous
# year as being < instead of > this year
sub logcompare { 
	my ($self, $x, $y) = @_; 

	# Fast path -- $a and $b are in the same month 
	if ( substr($x, 0, 3) eq substr($y, 0, 3) ) { 
		return lc $x cmp lc $y; 
	} 

	# Slow path -- handle year wrapping. localtime returns the month offset
	# by 1 so we add 2 to get the NEXT month
	my $month = (localtime())[4] + 2;

	return ( 
		substr($x, 1, 2) <= $month <=> substr($y, 1, 2) <= $month 
		or 
		lc $x cmp lc $y 
	); 
}

1;
