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

#	$self->{bans}{ipaddr} = {};	# Current 'permanent' bans from the current log by IP ADDR
#	$self->{bans}{worldid} = {};	# ... by guid / steamid

	# keep track of objects in memory
	$self->{maps} = {};		# loaded map objects, keyed on id
	$self->{weapons} = {};		# loaded weapon objects, keyed on id

	$self->init_online;
	$self->init_plrcache;
	$self->init_ipcache;

	#$self->{auto_plr_bans} = $self->{conf}->main->auto_plr_bans;

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

	#$event = decode('UTF-8',$event);	# HL logs are UTF-8 encoded
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
			if ($self->conf->main->errlog->report_timestamps) {
				# do not warn on lines with "unable to contact
				# the authentication server, 31)."
				$self->warn("Invalid timestamp from source " . $feed->curlog . " line " . $feed->curline . ": $event")
					unless substr($prefix,0,6) eq 'unable';
			}
			return;
		}

		$timestamp = timegm_nocheck($6, $5, $4, $2, $1-1, $3-1900) - ($feed->gmt_offset || 0);
		
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
	} elsif ($self->conf->main->errlog->report_unknown) {
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

	$w->action_attacked($k, $v, $m, $props);
	$k->action_attacked($v, $w, $m, $props);
	$v->action_injured( $k, $w, $m, $props);
}

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
		$p->action_disconnect($m, $props);
		$p->save;

		# start a new player entity using the original as the base.
		# stats are not cloned/transfered to the new entity.
		my $p2 = $p->clone;
		$p2->name($name);	# change players name

		# remove the original player from memory
		$self->del_plrcache($p);
		$self->plr_offline($p);		# mark old player OFFLINE
		
		# cache the new player into memory
		$self->add_plrcache($p2);
		$self->plr_online($p2);		# mark new player ONLINE

		#$self->scan_for_clantag($p2) if $self->{clantag_detection} and !$p2->clanid;

	} else {
		$self->del_plrcache($p);
		$p->action_changed_name($name, $props);
		$self->add_plrcache($p);
	}
}

sub event_changed_role {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $role) = @$args;
	return;
	my $p1 = $self->get_plr($plrstr);
	return unless ref $p1;
	$role = $self->role_normal($role); 
	$p1->{role} = $role;

	my $r1 = $self->get_role($role, $p1->{team}) || return;
	$p1->{roles}{ $r1->{roleid} }{joined}++;
	$r1->{basic}{joined}++;
}

sub event_chat {
	my ($self, $timestamp, $args) = @_;
	return unless $self->conf->main->plr_chat_max > 0;
	my ($plrstr, $teamonly, $msg, $propstr) = @$args;
	my $p = $self->get_plr($plrstr);
	my $props = $self->parseprops($propstr);
	return unless ref $p;
	# hell, let banned players chat it up!
	#return if $self->isbanned($p1);

	#$msg = encode('UTF-8', $msg);
	#$msg = encode_utf8($msg);
	$p->action_chat($msg, $teamonly, $props);
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
	
	$p->action_connected($ip, $props);
	$m->action_connected($p, $props);

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
	
	$p->action_disconnect($self->get_map, $props);

	# remove the player from our cache since they're no longer online
	$self->del_plrcache($p);

	# scan the player for a valid clantag
	if ($self->conf->main->clantag_detection and !$p->clanid) {
		my ($tag, $clan) = $self->scan_for_clantag($p);
		if ($tag and $clan->{clanid}) {
			$p->clanid($clan->{clanid});
		}
	}

	# save any pending stats they might have.
	$p->save;

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

	$p->action_entered($m, $props);
	
	# consider the player ONLINE and actively in the game.
	$self->plr_online($p);
}

# "Player<uid><STEAM_ID><>" joined team "CT"
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

	$p->action_joined_team($team, $m, $props);
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
	my $kr = $self->get_role($k->role);
	my $vr = $self->get_role($v->role);
	my $props = $self->parseprops($propstr);

	$k->action_kill( $v, $w, $m, $props);	# record a kill for the KILLER
	$w->action_kill( $k, $v, $m, $props);	# record a kill for the WEAPON
	$m->action_kill( $k, $v, $w, $props);	# record a kill for the MAP
	$v->action_death($k, $w, $m, $props);	# record a death for the VICTIM

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
		$self->debug3("Updating player ranks...", 0);
		$self->update_plr_ranks;
		$self->{rank_kills} = 0;
		$self->{rank_time} = time;
	}
	

=pod
	# check for spatial stats on this event
	if ($props->{attacker_position}) {
		$m->spatial(
			$self, 
			$k, $props->{attacker_position}, 
			$v, $props->{victim_position}, 
			$w, $props->{headshot}
		);
	}

=cut
}

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
		$m->action_mapended($props);
		$m->save;
		undef $m;
	}
	$self->{maps} = {};

	# start up the new map in memory
	$self->{curmap} = $mapname;
	$m = $self->get_map;
	
	$m->action_mapstarted($props);
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

	# 'mini_round_start' is a TF2 trigger, but its more efficient to put it
	# here instead of Game/halflife/tf2.pm
	if ($trigger eq 'round_start' or $trigger eq 'mini_round_start') {
		$self->{roundstart} = $timestamp;
		$m->action_round($props);

		foreach my $p ($self->get_online_plrs) {
			# do some per-round cleanup (ie: reset dead flag)
			$p->action_round($m, $props);
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

	$m->action_spatial($k, $v, $w, $props);
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

	$p->action_suicide($m, $w, $props);
	$m->action_suicide($p, $w, $props);
	$w->action_suicide($p, $m, $props);

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
	return unless $self->minconnected;
	return unless $props->{weapon};
	
	my $weapon = $self->weapon_normal($props->{weapon});
	my $w = $self->get_weapon($weapon);

	$p->action_weaponstats($trigger, $w, $props);
	$w->action_weaponstats($trigger, $p, $props);
	#$r->action_weaponstats($trigger, $p, $w, $props);
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

# parses the player signature string and returns the player object matching it.
# if an invalid player string is given then undef is returned.
sub get_plr {
	my ($self, $plrsig) = @_;
	my ($p,$str,$team,$guid,$uid,$name,$ipaddr);

	#print_r([ map { $self->{c_eventsig}{$_}{ids} } keys %{$self->{c_eventsig}} ]);

	# Return the cached player via the player sig, if previously cached.
	if ($p = $self->plrcached($plrsig)) {
		#warn "* CACHE(eventsig): \"$p\"\n"; # == \"" . $p->eventsig . "\"\n";
		return $p;
	}

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

	# completely ignore the HLTV client.
	if ($guid eq 'HLTV') {
		return undef;
	}

	# UID is unique to each player until a server restarts
	$uid = substr($str, rindex($str,'<'), 128, '');
	$uid = substr($uid, 1, -1);
	
	# ignore any players with an UID of -1 or no STEAMID
	# treat $uid as a string to avoid numerical errors due to invalid events
	if (!$guid or $uid eq '-1') {
		;;; $self->debug1("Ignoring invalid player identifier from logsource '$self->{_src}' line $self->{_line}: '$plrsig'",0);
		return undef;
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
		return undef if $self->conf->main->ignore_bots;
		# limit the total characters (128 - 4)
		$guid = "BOT_" . uc substr($name, 0, 124);
	}

	# lookup the alias for the player guid
	#if ($self->{uniqueid} eq 'worldid') {
	#	$guid = $self->get_plr_alias($guid);
	#} elsif ($self->{uniqueid} eq 'name') {
	#	$name = $self->get_plr_alias($name);
	#} elsif ($self->{uniqueid} eq 'ipaddr') {
	#	$ipaddr = ip2int($self->get_plr_alias(int2ip($ipaddr)));
	#}

	# If we get to this point the player signature did not match a current
	# player in memory so we need to try and figure out if they are really a
	# new player or a known player that changed their name, teams or has
	# reconnected within the same log file.

	# * The signature potentially identifies a unique player in the DB
	my $sig = {
		name => $name,		# *
		guid => $guid,		# *
		ipaddr => $ipaddr,	# *
		eventsig => $plrsig,
		team => $team,
		uid => $uid
	};
	
	# based on their UID the player already existed (something in their
	# signature changed since their last event)
	if ($p = $self->plrcached($uid, 'uid')) {
		$self->del_plrcache($p);	# remove old cached signature
		$p->name($name);
		$p->guid($sig->{$self->{uniqueid}});
		$p->ipaddr($ipaddr);
		$p->team($team);
		$self->add_plrcache($p);	# recache with new signature

	#} elsif ($p = $self->plrcached($sig->{$self->{uniqueid}}, 'guid')) {
		# the only time the UIDs won't match is when a player has extra
		# events that follow a disconnect event. this happens with a
		# couple of minor events like dropping the bomb in CS. The bomb
		# drop event is triggered after the player disconnect event and
		# thus causes confusion with internal routines. So I cache the
		# uniqueid of the player and then fix the 'uid' if needed
		# here...
		#if ($p->uid ne $uid) {
		#	$p->team($team);
		#	#$p->plrids($plrids);
		#	$self->delcache($p->uid($uid), 'uid');
		#	$self->delcache($p->signature($plrsig), 'signature');
		#	$self->addcache($p, $p->uid, 'uid');
		#	$self->addcache($p, $p->signature, 'signature');
		#}

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
			$p->skill( $self->conf->main->baseskill );
		}

		$self->add_plrcache($p);
		#$self->scan_for_clantag($p) if $self->{clantag_detection} and !$p->clanid;
	}

	return $p;
}

sub parseprops {
	my ($self, $str, $timestamp) = @_;
	my ($var, $val);     
	my $props = {};
	$str = '' unless defined $str;
	while ($str =~ s/^\s*\((\S+)(?:\s+"([^"]*|.*?(?:<[^>]*>))")?\)//) {	# (variable "value")
		$var = $1;
		$val = (defined $2) ? $2 : 1;			# if "value" doesn't exist the var is a true 'boolean' 
		if (exists $props->{$var}) {
			# convert to array if its not already
			$props->{$var} = [ $props->{$var} ] unless ref $props->{$var};
			push(@{$props->{$var}}, $val);		# add to array
		} else {
			$props->{$var} = $val;
		}
	}
	$props->{timestamp} = $timestamp || $self->{timestamp};
	$props->{event} = $self->{_event};

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
	$weapon = 'grenade' if $weapon eq 'hegrenade';
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
