package PS::Game::halflife;

use strict;
use warnings;
use base qw( PS::Game );

use util qw( :net :date );
use Encode;
use Time::Local qw( timelocal_nocheck );
use PS::Player;
use PS::Map;
use PS::Role;
use PS::Weapon;

our $VERSION = '1.00';

sub new {
	my $proto = shift;
	my $conf = shift;
	my $db = shift;
	my $class = ref($proto) || $proto;
	my $self = { debug => 0, class => $class, conf => $conf, db => $db };
	bless($self, $class);

#	$self->debug($self->{class} . " initializing");

	return $self->_init;
}

sub _init {
	my $self = shift;
	$self->SUPER::_init;
	$self->{conf}->load('game_halflife');
	$self->load_events(*DATA);

	$self->{_noeventmethod} = {};	# keep track of what events had no method available so a warning is only issued once

	$self->{lastsave} = time();	# last time we did a global save of all data
	$self->{lastprefixstr} = "";
	$self->{lastprefix} = 0;

#	$self->{bans}{ipaddr} = {};	# Current 'permanent' bans from the current log by IP ADDR
#	$self->{bans}{worldid} = {};	# ... by worldid / steamid

	$self->{maps} = {};		# map objects (there should really only be 1 map object loaded at a time)
	$self->{weapons} = {};		# weapon objects
	$self->{plrs} = {};		# player objects, keyed on uid
	$self->initcache;

	$self->{ipcache} = {};		# player IPADDR cache, keyed on uid (not uniqueid)

	$self->{auto_plr_bans} = $self->{conf}->get_main('auto_plr_bans');

	$self->{curmap} = $self->{conf}->get_game_halflife('defaultmap') 
		|| $self->{conf}->get_main('defaultmap')
		|| 'unknown';

	return $self;
}

sub initcache {
	my $self = shift;

	$self->{c}{signature} = {};	# players keyed on their signature string
	$self->{c}{uniqueid} = {};	# players keyed on their uniqueid (not UID)
}

# add a plr to the cache by uniqueid and/or signature
# uniqueid's are lowercased
sub addcache {
	my ($self, $p, $sig, $uniqueid) = @_;
	$self->{c}{signature}{$sig} = $p if defined $sig;
	$self->{c}{uniqueid}{lc $uniqueid} = $p if defined $uniqueid;
}

# remove a plr from the caches
sub delcache {
	my ($self, $sig, $uniqueid) = @_;
	delete $self->{c}{signature}{$sig} if defined $sig;
	delete $self->{c}{uniqueid}{lc $uniqueid} if defined $uniqueid;
}

# return the cached plr from the cache (uniqueid or signature) or undef if not found
sub cached {
	my ($self, $key, $cache) = @_;
	return undef unless defined $key;
	$cache ||= 'signature';
	$key = lc $key if $cache ne 'signature';
	return exists $self->{c}{$cache}{$key} ? $self->{c}{$cache}{$key} : undef;
}

sub restore_state {
	my $self = shift;
	my $state = shift || return;
	my $map;

	$self->{timestamp} = $state->{timestamp};
	if ($state->{map}) {
		$self->{curmap} = $state->{map};
		$map = $self->get_map;
	}

	return unless $state->{plrs};
	# reinstate the players that were online previously ...
	foreach my $plr (@{$state->{plrs}}) {
		my $plrids = { name => $plr->{name}, worldid => $plr->{worldid}, ipaddr => $plr->{ipaddr} };
		my $p = PS::Player->new($plrids, $self);
		next unless $p;
		$p->plrids;
		$p->signature($plr->{plrsig});
		$p->timerstart($self->{timestamp});
		$p->uid($plr->{uid});
		$p->{team} = $plr->{team};
		$p->{role} = $plr->{role};

		$self->{plrs}{ $plr->{uid} } = $p;
		$self->addcache($p, $p->signature, $p->uniqueid);
	}
#	print "state restored at timestamp: " . localtime($state->{timestamp}) . "\n";
#	print "map restored: " . $map->name . "\n" if $map;
#	print "player IDs restored: ", join(', ', map { $self->{plrs}{$_}->{plrid} } keys %{$self->{plrs}}), "\n";
#	die;
}

# handle the event that comes in from the Feeder (log line)
use constant 'PREFIX_LENGTH' => 25;
sub event {
	my $self = shift;
	my ($src, $event, $line) = @_;
	my ($prefix, $timestamp);
	chomp($event);
	return if length($event) < PREFIX_LENGTH;	#			"123456789*123456789*12345"
	$prefix = substr($event, 0, PREFIX_LENGTH);	# PREFIX (25 chars): 	"L MM/DD/YYYY - hh:mm:ss: "

	$self->{_src} = $src;
	$self->{_event} = $event;
	$self->{_line} = $line;

	# avoid performing the prefix regex as much as possible (possible performance gain)
	if ($prefix eq $self->{lastprefixstr}) {
		$timestamp = $self->{lasttimestamp};
	} else {
		if ($prefix !~ /^L (\d\d)\/(\d\d)\/(\d\d\d\d) - (\d\d):(\d\d):(\d\d)/) {
			if ($self->{report_unknown}) {
				# do not warn on lines with "unable to contact the authentication server, 31)."
				$self->warn("Invalid timestamp for source '$src' line $line event '$event'") unless substr($prefix,0,6) eq 'unable';
			}
			return;
		}
		$timestamp = timelocal_nocheck($6, $5, $4, $2, $1-1, $3-1900);
		$self->{lasttimestamp} = $timestamp;
		$self->{lastprefixstr} = $prefix;
	}
	$self->{timestamp} = $timestamp;
	substr($event, 0, PREFIX_LENGTH, '');					# remove prefix from the event
#	$event = substr($event, PREFIX_LENGTH);					# remove prefix from the event

	# SEARCH FOR A MATCH ON THE EVENT USING OUR LIST OF REGEX'S
	# If a match is found we dispatch the event to the proper event method 'event_{match}'
	my ($re, $params) = &{$self->{evregex}}($event);			# finds an EVENT match (fast)
	if ($re) {
		return if $self->{evconf}{$re}{options}{ignore};		# should this match be ignored?
		my $func = $self->{evconf}{$re}{event} || 'event_' . $re;	# use specified $event or 'event_$re'
		if ($self->can($func)) {
			$self->$func($timestamp, $params);			# call event handler
		} else {
			$self->warn("Event '$re' ignored (No event method available). Further warnings supressed.") unless $self->{_noeventmethod}{$re};
			$self->{_noeventmethod}{$re}++;				# keep tally the total times we saw this
		}

	} else {
		$self->warn("Unknown log event was ignored from source $src line $line: $event") if $self->{report_unknown};
	}
}

sub process_feed {
	my ($self, $feeder) = @_;
	my $total = $self->SUPER::process_feed($feeder);

	# after the feed ends make sure all stats in memory are saved.
	# the logstartend event does everything we need to save all in-memory stats.
#	$self->event_logstartend($self->{lasttimestamp}, [ 'started' ]);	
	$self->event_logstartend($self->{timestamp}, [ 'started' ]);	

	return $total;
}

# parses the player string and returns the player object matching the uniqueid. 
# creates a new player object if no current player matches.
sub get_plr {
	my ($self, $str, $plrids_only) = @_;
	my ($p,$plrstr,$plrids,$name,$uid,$worldid,$team,$ipaddr,$uniqueid);

	# return the cached player via their signature if they exist (small performance gain)
	if (!$plrids_only) {
		$p = $self->cached($str);
#		print "SIMPLE: $str\n" if $p and $str =~ /:8868013/;
		return $p if defined $p;
	}

	$plrstr = $str;					# save the full player string
	# using multiple substr calls inplace of a single regex is a lot faster 
	$team = substr($str, rindex($str,'<'), 128, '');
	$team = lc substr($team, 1, -1);
	$team = "" if $team eq 'unassigned';		# CS:Source uses 'Unassigned' for new players that haven't joined a team
	$team =~ tr/ /_/;				# convert spaces to _ on team names (some mods are known to do this)
	$team =~ tr/a-z0-9_//cs;			# remove all non-alphanumeric characters
	$worldid = substr($str, rindex($str,'<'), 128, '');
	$worldid = substr($worldid, 1, -1);
	$uid = substr($str, rindex($str,'<'), 128, '');
	$uid = substr($uid, 1, -1);
	if (!$worldid or $uid eq '-1') {		# ignore any players with an ID of -1 ... its invalid!
		$::ERR->debug1("Ignoring invalid player identifier in src '$self->{_src}' line '$self->{_line}': '$plrstr'",3);
		return undef;
	}
#	print "get_plr: ",decode('utf8',$plrstr),"\n" if $worldid eq 'STEAM_0:0:7702999';
	$name = decode('utf8',$str); # $str;
#	print "get_plr: ",$name,"\n" if $worldid eq 'STEAM_0:0:7702999';
#	$name = decode('utf8', $name);
#	$name = encode($self->{charset} || 'iso-8859-1', decode('utf8', $name));
	$name =~ s/^\s+//;
	$name =~ s/\s+$//;
	$name = '- no name -' if $name eq "";		# do not allow blank names
	$ipaddr = exists $self->{ipcache}{$uid} ? $self->{ipcache}{$uid} : 0;

	# For BOTS: replace STEAMID's with the player name otherwise all bots will be combined into the same STEAMID
	if ($worldid eq 'BOT' or $worldid eq '0') {
		return undef if $self->{ignore_bots};
		$worldid = "BOT:" . lc substr($name, 0, 128);	# limit the total characters
	}

	if ($self->{uniqueid} eq 'worldid') {
		$worldid = $self->get_plr_alias($worldid);
	} elsif ($self->{uniqueid} eq 'name') {
		$name = $self->get_plr_alias($name);
	} elsif ($self->{uniqueid} eq 'ipaddr') {
		$ipaddr = ip2int($self->get_plr_alias(int2ip($ipaddr)));
	}

	# include the team in case we want to use it in the calling function. The variable is 'teamstr' 
	# so that we don't confuse it with the actual known team for a player.
	$plrids = { name => $name, worldid => $worldid, ipaddr => $ipaddr, teamstr => $team };
	return { %$plrids, uid => $uid } if $plrids_only;	

	$p = undef;

	# based on their UID the player already existed (changed teams or name since the last event)
	if (exists $self->{plrs}{$uid}) {
		$p = $self->{plrs}{$uid};
#		print "UID EXISTS: $plrstr\n" if $plrstr =~ /:8868013/;
		$p->plrids($plrids);							# update new player ids
		$self->delcache($p->signature($plrstr));				# delete previous and set new sig
		$self->addcache($p, $plrstr);
	} elsif ($p = $self->cached($plrids->{$self->{uniqueid}}, 'uniqueid')) {
		# the only time the UIDs won't match is when a player has extra events that follow a disconnect event.
		# this happens with a couple of minor events like dropping the bomb in CS. The bomb drop event is triggered
		# after the player disconnect event and thus causes confusion with internal routines. So I cache the uniqueid
		# of the player and then fix the 'uid' if needed here...
#		print "CACHED: $plrstr\n" if $plrstr =~ /:8868013/;
		if ($p->{uid} ne $uid) {
			delete $self->{plrs}{ $p->{uid} };
			$self->{plrs}{$uid} = $p;
			$p->uid($uid);
		}
	} else {
#		print "* NEW: $plrstr\n" if $plrstr =~ /:8868013/;
		$p = PS::Player->new($plrids, $self);
		return undef unless $p;
		$p->active(1);		# always mark active for now......
		$p->signature($plrstr);
		$p->timerstart($self->{timestamp});
		$p->uid($uid);
		$p->plrids;		# update plr_ids info
		$self->{plrs}{$uid} = $p;
		$self->addcache($p, $plrstr, $p->uniqueid);
		$self->scan_for_clantag($p) if $self->{clantag_detection} and !$p->clanid;
	}

	$p->{teamstr} = $team;
	return $p;
}

sub get_map {
	my ($self, $name) = @_;
	$name ||= $self->{curmap} || 'unknown';

	if (exists $self->{maps}{$name}) {
		return $self->{maps}{$name};
	}

	$self->{maps}{$name} = PS::Map->new($name, $self->{conf}, $self->{db});
	$self->{maps}{$name}->timerstart($self->{timestamp});
	$self->{maps}{$name}->statdate($self->{timestamp});
	return $self->{maps}{$name};
}

sub get_weapon {
	my ($self, $name) = @_;
	$name ||= 'unknown';

	if (exists $self->{weapons}{$name}) {
		return $self->{weapons}{$name};
	}

	$self->{weapons}{$name} = PS::Weapon->new($name, $self->{conf}, $self->{db});
	$self->{weapons}{$name}->statdate($self->{timestamp});
	return $self->{weapons}{$name};
}

sub get_role {
	my ($self, $name, $team) = @_;

	# do not create a role if it's not defined
	return undef unless $name;

	$name ||= 'unknown';

	if (exists $self->{roles}{$name}) {
		return $self->{roles}{$name};
	}

	$self->{roles}{$name} = PS::Role->new($name, $team, $self->{conf}, $self->{db});
	$self->{roles}{$name}->statdate($self->{timestamp});
	return $self->{roles}{$name};
}

sub event_kill {
	my ($self, $timestamp, $args) = @_;
	my ($killer, $victim, $weapon, $propstr) = @$args;
	my $p1 = $self->get_plr($killer) || return;
	my $p2 = $self->get_plr($victim) || return;
	$self->_do_connected($timestamp, $p1) unless $p1->{_connected};
	$self->_do_connected($timestamp, $p2) unless $p2->{_connected};

	$p1->{basic}{lasttime} = $timestamp;
	$p2->{basic}{lasttime} = $timestamp;
	return unless $self->minconnected;
	return if $self->isbanned($p1) or $self->isbanned($p2);

	my $m = $self->get_map;
	my $r1 = $self->get_role($p1->{role}, $p1->{team});
	my $r2 = $self->get_role($p2->{role}, $p1->{team});
	my $props = $self->parseprops($propstr);

	$weapon = 'unknown' unless $weapon;
	$weapon =~ tr/ /_/;				# convert spaces to '_'

	my $w = $self->get_weapon($weapon);

	# I directly access the player variables in the objects (bad OO design), 
	# but the speed advantage is too great to do it the "proper" way.

	my $ffkill = (($p1->{team} and $p2->{team}) and ($p1->{team} eq $p2->{team}));

	$p1->update_streak('kills', 'deaths');
	$p1->{basic}{kills}++;
	$p1->{mod}{ $p1->{team} . "kills"}++ if $p1->{team};		# Kills while ON the team
#	$p1->{mod}{ $p2->{team} . "kills"}++;				# Kills against the team
	$p1->{mod_maps}{ $m->{mapid} }{ $p1->{team} . "kills"}++ if $p1->{team};
	$p1->{weapons}{ $w->{weaponid} }{kills}++;
	$p1->{maps}{ $m->{mapid} }{kills}++;
	$p1->{roles}{ $r1->{roleid} }{kills}++ if $r1;
	$p1->{victims}{ $p2->{plrid} }{kills}++;

	$p2->{isdead} = 1;
	$p2->update_streak('deaths', 'kills');
	$p2->{basic}{deaths}++;
	$p2->{mod}{ $p2->{team} . "deaths"}++ if $p2->{team};		# Deaths while ON the team
#	$p2->{mod}{ $p1->{team} . "deaths"}++;				# Deaths against the team
	$p2->{mod_maps}{ $m->{mapid} }{ $p2->{team} . "deaths"}++ if $p2->{team};
	$p2->{weapons}{ $w->{weaponid} }{deaths}++;
	$p2->{maps}{ $m->{mapid} }{deaths}++;
	$p2->{roles}{ $r2->{roleid} }{deaths}++ if $r2;
	$p2->{victims}{ $p1->{plrid} }{deaths}++;

	if ($props->{headshot}) {
#		$p1->{basic}{headshotkills}++;
#		$p1->{weapons}{ $w->{weaponid} }{headshotkills}++;
		$p1->{victims}{ $p2->{plrid} }{headshotkills}++;

#		$p2->{basic}{headshotdeaths}++;
#		$p2->{weapons}{ $w->{weaponid} }{headshotdeaths}++;
		$p2->{victims}{ $p1->{plrid} }{headshotdeaths}++;

#		$w->{basic}{headshotkills}++;

		$r1->{basic}{headshotkills}++ if $r1;
	}

	$m->{basic}{lasttime} = $timestamp;
	$m->{basic}{kills}++;
	$m->{mod}{ $p1->{team} . 'kills'}++ if $p1->{team};		# kills on the team
#	$m->{mod}{ $p2->{team} . 'kills'}++;		# how many plr2 (victim) teammates were killed

#	$w->{basic}{lasttime} = $timestamp;
	$w->{basic}{kills}++;

	$r1->{basic}{kills}++ if $r1;

	$r2->{basic}{deaths}++ if $r2;

	# friendly-fire kills
	if ($ffkill) {
		$p1->{maps}{ $m->{mapid} }{ffkills}++;
		$p1->{weapons}{ $w->{weaponid} }{ffkills}++;
		$p1->{basic}{ffkills}++;

		$p2->{weapons}{ $w->{weaponid} }{ffdeaths}++;
		$p2->{maps}{ $m->{mapid} }{ffdeaths}++;
		$p2->{basic}{ffdeaths}++;

		$m->{basic}{ffkills}++;
		$w->{basic}{ffkills}++;
		$r1->{basic}{ffkills}++ if $r1;

		$self->plrbonus('ffkill', 'enactor', $p1);
	}

	# allow mods to add their own stats for kills
#	$self->mod_event_kill($p1, $p2, $w, $m);

	my $func = $self->{calcskill_kill};
	$self->$func($p1, $p2, $w);
}

sub event_connected {
	my ($self, $timestamp, $args) = @_;
#	my ($plrstr, $uid, $ipstr, $props) = @$args;
	my ($plrstr, $ipstr, $props) = @$args;
	my $ip = lc((split(/:/,$ipstr,2))[0]);
	$ip = '127.0.0.1' if $ip eq 'localhost' or $ip eq 'loopback';
	$ip = '127.0.0.1' if $ip !~ /(?:\d{1,3}\.){3}\d{1,3}/;

	# strip out the worldid/uid and do not use get_plr() since players will have a STEAM_ID_PENDING
	my $str = $plrstr;
	substr($str, rindex($str,'<'), 128, '');				# remove the team
	my $worldid = substr(substr($str, rindex($str,'<'), 128, ''), 1, -1);
	my $uid = substr(substr($str, rindex($str,'<'), 128, ''), 1, -1);

	$self->{ipcache}{$uid} = ip2int($ipstr);				# save the IP addr
	return if index(uc $worldid, "PENDING") > 0;				# do nothing if it's STEAM_ID_PENDING
	$self->_do_connected($timestamp, $plrstr);
}

sub event_connected_steamid { # the regex definition is currently set to 'ignore'
# Since valve logs the $plrstr differently on the 'validated' events I'm going to simply ignore these events.
# I don't need to track the users connected state here. 
# The player will be marked as 'connected' in the 'entered' event instead.
#
#	my ($self, $timestamp, $args) = @_;
#	my ($plrstr, $validated) = @$args;
#	my $p1 = $self->get_plr($plrstr) || return;
#	$p1->plrids
#	$self->_do_connected($timestamp, $plrstr);
}

# the connected and connected_steamid events call this to increment the plr/map stats
sub _do_connected {
	my ($self, $timestamp, $plrstr) = @_;
	# $plrstr can be a reference to a player object or a player signature
	my $p1 = ref $plrstr ? $plrstr : $self->get_plr($plrstr) || return;
	my $m = $self->get_map;
	my $bot = $p1->is_bot;
#	$p1->plrids;
	$p1->{_connected}++;
	if (!$bot or !$self->{ignore_bots_conn}) {
		$p1->{basic}{connections}++;
		if ($m) {
			$p1->{maps}{ $m->{mapid} }{connections}++;
			$m->{basic}{connections}++;
		}
	}
}

sub event_disconnected {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr) = @$args;
	my $p1 = $self->get_plr($plrstr) || return;

	$p1->disconnect($timestamp, $self->get_map);
	$p1->save;
	$p1->active(0);

#	delete $self->{plrs}{ $p1->uid };
#	$self->delcache($p1->signature, $p1->uniqueid);
#	$p1 = undef;
}

sub event_entered_game {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $props) = @$args;
	my $p1 = $self->get_plr($plrstr) || return;
	my $m = $self->get_map;

#	$self->_do_connected($timestamp, $plrstr) unless $p1->{_connected};
	$self->_do_connected($timestamp, $p1) unless $p1->{_connected};

	$p1->{basic}{games}++;
	$p1->{maps}{ $m->{mapid} }{games}++;
	$p1->{maps}{ $m->{mapid} }{lasttime} = $timestamp;

	# start new timer and save last timer if one was present
	if (my $time = $p1->timerstart($timestamp) and $p1->active) {
		if ($time > 0) {				# ignore negative values
			$p1->{basic}{onlinetime} += $time;
			$p1->{maps}{ $m->{mapid} }{onlinetime} += $time;
		}
	}
	$p1->active(1);
}

sub event_joined_team {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $team, $props) = @$args;
	my $p1 = $self->get_plr($plrstr) || return;
	my $m = $self->get_map;
	$self->_do_connected($timestamp, $p1) unless $p1->{_connected};

	$team = lc $team;
	$team =~ tr/ /_/;
	$team =~ tr/a-z0-9_//cs;				# remove all non-alphanumeric characters
	$team = 'spectator' if $team eq 'spectators';		# some MODS have a trailing 's'.
	$team = '' if $team eq 'unassigned';

	$self->{plrs}{ $p1->uid } = $p1;
	$self->delcache($p1->signature($plrstr));		# delete old sig from cache and set new sig
	$self->addcache($p1, $p1->signature, $p1->uniqueid);	# add current sig and uniqueid to cache

	$p1->{team} = $team;

	# do not record team events for spectators
#	return if $team eq 'spectator';

	$p1->{basic}{lasttime} = $timestamp;
	if ($team) {
		$p1->{mod_maps}{ $m->{mapid} }{"joined$team"}++;
		$p1->{mod}{"joined$team"}++;
		$m->{mod}{"joined$team"}++;
	}
}

sub event_changed_name {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $name) = @$args;
	my $p1 = $self->get_plr($plrstr) || return;
##	$self->_do_connected($timestamp, $p1) unless $p1->{_connected};

	# The get_plr method will automatically take care of the plr caches for name changes
	$p1->plrids({ name => decode('utf8',$name), worldid => $p1->worldid, ipaddr => $p1->ipaddr });
	$p1->{basic}{lasttime} = $timestamp;

}

sub event_suicide {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $weapon, $props) = @$args;
	my $p1 = $self->get_plr($plrstr) || return;
	$self->_do_connected($timestamp, $p1) unless $p1->{_connected};

	return unless $self->minconnected;
	return if $self->isbanned($p1);
	my $m = $self->get_map;

	$weapon = 'unknown' unless $weapon;
	$weapon =~ tr/ /_/;

	if (lc $weapon ne 'world') {
		$p1->{basic}{lasttime} = $timestamp;
		$p1->{basic}{deaths}++;
		$p1->{basic}{suicides}++;
		$m->{basic}{suicides}++;
		$self->plrbonus('suicide', 'enactor', $p1);	# 'suicide' award bonus/penalty for killing yourself (idiot!)
#	} else {
#		# plr changed teams, do not count the suicide
	}

}

# standard round start/end for several mods
sub event_round {
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

# watch all chat events for player settings
sub event_chat {
	return; ############################################################
	my ($self, $timestamp, $args) = @_;
	return unless $self->{uniqueid} eq 'worldid';		# only allow user commands when we track by WORLDID
	return unless $self->{usercmds}{enabled};
	my ($plrstr, $teamonly, $msg, $props) = @$args;
	my $p1 = $self->get_plr($plrstr) || return;
	return if $self->isbanned($p1);

	$msg = decode('utf8',$msg);
	return unless $msg =~ /^$self->{usercmds}{prefix}(.+)\s+(.+)/o;
	my ($cmd, $param) = ($1, $2);

}

sub event_startedmap {
	my ($self, $timestamp, $args) = @_;
	my ($startorload, $mapname, $props) = @$args;
	my $m;

	# ignore 'map loaded' events, we only care about 'map started' events
	return unless lc $startorload eq 'started';

	# a previous map was already loaded, save it now
	if ($m = $self->{maps}{ $self->{curmap} }) {
		my $time = $m->timer;
		$m->{basic}{onlinetime} += $time if ($time > 0);
		$m->save;
		delete $self->{maps}{ $self->{curmap} };
	}

	# start up the new map in memory
	$self->{curmap} = $mapname;
	$m = $self->get_map;
	$m->statdate($timestamp);
	$m->{basic}{games}++;
}

sub event_logstartend {
	my ($self, $timestamp, $args) = @_;
	my ($startedorclosed) = @$args;
	my $m = $self->get_map;

	# A log 'started' event is almost ALWAYS guaranteed to happen (unlike 'closed' events)
	# we use this time to close out any previous maps and save all current player data in memory
	return unless lc $startedorclosed eq 'started';

	$self->{db}->begin;

#	print scalar keys %{$self->{plrs}}, " players online.\n" if scalar keys %{$self->{plrs}};
	# SAVE PLAYERS
	while (my ($uid,$p1) = each %{$self->{plrs}}) {
		$p1->end_all_streaks;					# do not count streaks across map/log changes
		$p1->disconnect($timestamp, $m);
		$p1->save if $p1->active;
		$p1 = undef;
	}
	$self->{plrs} = {};
	$self->initcache;

	# SAVE WEAPONS
	while (my ($wid,$w) = each %{$self->{weapons}}) {
		$w->save;
		$w = undef;
	}
	$self->{weapons} = {};

	# SAVE ROLES
	while (my ($rid,$r) = each %{$self->{roles}}) {
		$r->save;
		$r = undef;
	}
	$self->{roles} = {};

	# SAVE MAPS
	while (my ($mid,$m) = each %{$self->{maps}}) {
		my $time = $m->timer;
#		print "$timestamp - $m->{basic}{lasttime}\n";
		$time ||= $timestamp - $m->{basic}{lasttime} if $timestamp - $m->{basic}{lasttime} > 0;
#		print $m->name . ": time=$time\n";
		$m->{basic}{onlinetime} += $time if ($time > 0);
		$m->save;
		$m = undef;
	}
	$self->{maps} = {};

	$self->{db}->commit;
}

sub event_attacked {
	my ($self, $timestamp, $args) = @_;
	my ($killer, $victim, $weapon, $propstr) = @$args;
	my $p1 = $self->get_plr($killer) || return;
	my $p2 = $self->get_plr($victim) || return;
	$self->_do_connected($timestamp, $p1) unless $p1->{_connected};
	$self->_do_connected($timestamp, $p2) unless $p2->{_connected};

	return unless $self->minconnected;
	return if $self->isbanned($p1) or $self->isbanned($p2);

	my $r1 = $self->get_role($p1->{role}, $p1->{team});
	my $r2 = $self->get_role($p2->{role}, $p1->{team});

	$p1->{basic}{lasttime} = $timestamp;
	$p2->{basic}{lasttime} = $timestamp;

	my $w = $self->get_weapon($weapon);
	my $props = $self->parseprops($propstr);

	no warnings;
	my $dmg = int($props->{damage} + $props->{damage_armor});

	if ($r1) {
		$r1->{basic}{shots}++;
		$r1->{basic}{hits}++;
		$r1->{basic}{damage} += $dmg;
	}
	if ($r2) {
		$r2->{basic}{shots}++;
		$r2->{basic}{hits}++;
		$r2->{basic}{damage} += $dmg;
	}

	$w->{basic}{shots}++;
	$w->{basic}{hits}++;
	$w->{basic}{damage} += $dmg;

	$p1->{basic}{shots}++;
	$p1->{basic}{hits}++;
	$p1->{basic}{damage} += $dmg;

	$p1->{weapons}{ $w->{weaponid} }{shots}++;
	$p1->{weapons}{ $w->{weaponid} }{hits}++;
	$p1->{weapons}{ $w->{weaponid} }{damage} += $dmg;

	# HL2 started recording the hitbox information on attacked events
	if ($props->{hitgroup} and $props->{hitgroup} ne 'generic') {
		my $loc = $props->{hitgroup};
		$loc =~ s/\s+//g;
		$loc = "shot_$loc";
		$w->{basic}{$loc}++;
		$p1->{weapons}{ $w->{weaponid} }{$loc}++;
		$r1->{basic}{$loc}++ if $r1;
		$r2->{basic}{$loc}++ if $r2;
	}
}

# generic player trigger
sub event_plrtrigger {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $trigger, $props) = @$args;
	my $p1 = $self->get_plr($plrstr) || return;
	return if $self->isbanned($p1);
	$self->_do_connected($timestamp, $p1) unless $p1->{_connected};

	$p1->{basic}{lasttime} = $timestamp;
	return unless $self->minconnected;
	my $m = $self->get_map;

	$trigger = lc $trigger;
	$self->plrbonus($trigger, 'enactor', $p1);
	if ($trigger eq 'weaponstats' or $trigger eq 'weaponstats2') {
		$self->event_weaponstats($timestamp, $args);

	} else {
		if ($self->{report_unknown}) {
			$self->warn("Unknown player trigger '$trigger' from src $self->{_src} line $self->{_line}: $self->{_event}");
		}
	}
}

sub event_weaponstats {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $trigger, $propstr) = @$args;
	my $p1 = $self->get_plr($plrstr) || return;

	$self->_do_connected($timestamp, $p1) unless $p1->{_connected};
	return if $self->isbanned($p1);

	$p1->{basic}{lasttime} = $timestamp;
	return unless $self->minconnected;

	my $props = $self->parseprops($propstr);
	my $weapon = $props->{weapon} || return;
	my $w = $self->get_weapon($weapon) || return;
	my $r1 = $self->get_role($p1->{role}, $p1->{team});

	# dereference once
	my $plrweapon = $p1->{weapons}{ $w->{weaponid} };
	my $plrrole = $r1 ? $p1->{roles}{ $r1->{roleid} } : undef;

	if ($trigger eq 'weaponstats') {
		no warnings;
		# dereference vars so we dont do it over and over below
		my ($hits, $shots, $dmg, $hs) = map { int($_ || 0) } @$props{ qw( hits shots damage headshots ) };

		$w->{basic}{hits} 			+= $hits; 
		$w->{basic}{shots} 			+= $shots;
		$w->{basic}{damage} 			+= $dmg; 
		$w->{basic}{headshotkills}		+= $hs;

		$p1->{basic}{hits} 			+= $hits; 
		$p1->{basic}{shots} 			+= $shots;
		$p1->{basic}{damage} 			+= $dmg;
		$p1->{basic}{headshotkills} 		+= $hs;

		$plrweapon->{hits} 			+= $hits;
		$plrweapon->{shots} 			+= $shots;
		$plrweapon->{damage} 			+= $dmg;
		$plrweapon->{headshotkills}		+= $hs;

		if ($r1) {
			$plrrole->{hits} 		+= $hits;
			$plrrole->{shots}	 	+= $shots;
			$plrrole->{damage} 		+= $dmg;
			$plrrole->{headshotkills}	+= $hs;

			$r1->{basic}{hits} 		+= $hits;
			$r1->{basic}{shots} 		+= $shots;
			$r1->{basic}{damage} 		+= $dmg;
			$r1->{basic}{headshotkills} 	+= $hs;
		}

	} elsif ($trigger eq 'weaponstats2') {
		no warnings;
		# dereference vars so we dont do it over and over below
		my ($head,$chest,$stomach,$leftarm,$rightarm,$leftleg,$rightleg) = map { int($_ || 0) } 
			@$props{ qw( head chest stomach leftarm rightarm leftleg rightleg ) };

#		print "($head,$chest,$stomach,$leftarm,$rightarm,$leftleg,$rightleg)\n";

		$w->{basic}{shot_head} 			+= $head;
		$w->{basic}{shot_chest} 		+= $chest;
		$w->{basic}{shot_stomach} 		+= $stomach;
		$w->{basic}{shot_leftarm} 		+= $leftarm;
		$w->{basic}{shot_rightarm} 		+= $rightarm;
		$w->{basic}{shot_leftleg} 		+= $leftleg;
		$w->{basic}{shot_rightleg} 		+= $rightleg;

		$plrweapon->{shot_head} 		+= $head;
		$plrweapon->{shot_chest} 		+= $chest;
		$plrweapon->{shot_stomach} 		+= $stomach;
		$plrweapon->{shot_leftarm} 		+= $leftarm;
		$plrweapon->{shot_rightarm} 		+= $rightarm;
		$plrweapon->{shot_leftleg} 		+= $leftleg;
		$plrweapon->{shot_rightleg} 		+= $rightleg;

		if ($r1) {
			$plrrole->{shot_head} 		+= $head;
			$plrrole->{shot_chest} 		+= $chest;
			$plrrole->{shot_stomach} 	+= $stomach;
			$plrrole->{shot_leftarm} 	+= $leftarm;
			$plrrole->{shot_rightarm}	+= $rightarm;
			$plrrole->{shot_leftleg} 	+= $leftleg;
			$plrrole->{shot_rightleg} 	+= $rightleg;

			$r1->{basic}{shot_head} 	+= $head;
			$r1->{basic}{shot_chest} 	+= $chest;
			$r1->{basic}{shot_stomach} 	+= $stomach;
			$r1->{basic}{shot_leftarm} 	+= $leftarm;
			$r1->{basic}{shot_rightarm} 	+= $rightarm;
			$r1->{basic}{shot_leftleg} 	+= $leftleg;
			$r1->{basic}{shot_rightleg} 	+= $rightleg;
		}

	} else {
		if ($self->{report_unknown}) {
			$self->warn("Unknown weaponstats trigger '$trigger' from source $self->{_src} line $self->{_line}: $self->{_event}");
		}
	}

}

sub event_ban {
	my ($self, $timestamp, $args) = @_;
	my ($type, $plrstr, $duration, $who, $propstr) = @$args;

	return unless $self->{auto_plr_bans};

	$type = lc $type;
	if (substr($type,0,3) eq 'ban') {		# STEAMID
		my $plr = $self->get_plr($plrstr, 1);	# does not create a player object
		return unless $plr->{worldid};
#		$self->{bans}{worldid}{ $plr->{worldid} } = 1;
		$self->addban('worldid', $plr->{worldid}, reason => 'Auto Ban', 'bandate' => $timestamp);
	} elsif ($type eq 'addip') {	# IP ADDR
		my $props = $self->parseprops($propstr);
		return unless $props->{IP};
#		$self->{bans}{ipaddr}{ ip2int($props->{IP}) } = 1;
		$self->addban('ipaddr', $props->{IP}, reason => 'Auto Ban', 'bandate' => $timestamp);
	} else {
		$self->warn("Unknown BAN type ignored from source $self->{_src} line $self->{_line}: $self->{_event}");
	} 
}

sub event_unban {
	my ($self, $timestamp, $args) = @_;
	my ($type, $plrstr, $who, $propstr) = @$args;

	return unless $self->{auto_plr_bans};

	$type = lc $type;
	if ($type eq 'id') {		# STEAMID
		my $plr = $self->get_plr($plrstr, 1);	# does not create a player object
		return unless $plr->{worldid};
#		$self->{bans}{worldid}{ $plr->{worldid} } = 0;
	} elsif ($type eq 'ip') {	# IP ADDR
		my $props = $self->parseprops($propstr);
		return unless $props->{IP};
#		$self->{bans}{ipaddr}{ ip2int($props->{IP}) } = 0;
	}
}

sub event_plugin {
	my ($self, $timestamp, $args) = @_;
	my ($plugin, $str, $propstr) = @$args;

#	print "[$plugin] $str\n";

#	if (lc $plugin eq 'statsme') {
#		$self->event_weaponstats($timestamp, [ ]);
#	}
}

#sub event_ipaddress {
#	my ($self, $timestamp, $args) = @_;
#	my ($plrstr, $propstr) = @$args;
#	my $plr = $self->get_plr($plrstr,1) || return;		# does not create a player object
#	my $props = $self->parseprops($propstr);
#	return unless $plr->{uid} and $props->{address};
#	$self->{ipcache}{$plr->{uid}} = ip2int($props->{address});	# save the IP address
#}

sub event_rcon {
	my ($self, $timestamp, $args) = @_;
	my ($bad, $challenge, $pw, $cmd, $ipport) = @$args;

}

sub event_kick {
	my ($self, $timestamp, $args) = @_;
	my ($plrstr, $who, $propstr) = @$args;

}

sub parseprops {
	my ($self, $str) = @_;
	my ($var, $val);     
	my $props = {};
	$str = '' if !defined $str;
	while ($str =~ s/^\s*\((\S+)(?:\s+"([^"]+)")?\)//) {	# (variable "value")       
		$var = $1;
		$val = (defined $2) ? $2 : 1;			# if "value" doesn't exist the var is a true 'boolean' 
		$props->{$var} = $val;
	}
	return wantarray ? %$props : $props;
}

# sorting method that the Feeder class can use to sort a list of log filenames
# returns a NEW array reference of the sorted logs. Does not change original reference.
sub logsort {
	my $self = shift;
	my $list = shift;		# array ref to a list of log filenames
	return [ sort { $self->logcompare($a, $b) } @$list ];
}

# compare method that can compare 2 log files for the game and return (-1,0,1) depending on their order
# smart logic tries to account for logs from a previous year as being < instead of > this year
sub logcompare { 
	my ($self, $x, $y) = @_; 

	# Fast path -- $a and $b are in the same month 
	if ( substr($x, 0, 3) eq substr($y, 0, 3) ) { 
		return lc $x cmp lc $y; 
	} 

	# Slow path -- handle year wrapping. localtime returns the month offset by 1 so we add 2 to get the NEXT month
	my $month = (localtime())[4] + 2;

	return ( 
		substr($x, 1, 2) <= $month <=> substr($y, 1, 2) <= $month 
		or 
		lc $x cmp lc $y 
	); 
}

1;

# DO NOT REMOVE THE __DATA__ BLOCK BELOW. IT CONTAINS RUNTIME INFORMATION FOR EVENT MATCHING
__DATA__

[kill]
  regex = /^"([^"]+)" killed "([^"]+)" with "([^"]*)"(.*)/

[attacked]
  regex = /^"([^"]+)" attacked "([^"]+)" with "([^"]+)"(.*)/
  options = ignore

[plrtrigger]
  regex = /^"([^"]+)" triggered "([^"]+)"(.*)/

[round]
  regex = /^World triggered "([^"]+)"(.*)/

## PsychoStats PIP records these for more accurate IP ADDR tracking on players
# handled in the plrtrigger event directly
#[ipaddress]
#  regex = /^"([^"]+)" triggered "(?:ip)?address"(.*)/

## any 3rd party plugin that has a prefix: [BLAH]
[plugin]
  regex = /^\[([^\]]+)\]\s*(.*)/

[entered_game]
  regex = /^"([^"]+)" entered the game(.*)/

[joined_team]
  regex = /^"([^"]+)" joined team "([^"]+)"/

[suicide]
  regex = /^"([^"]+)" committed suicide with "([^"]+)"(.*)/

[changed_name]
  regex = /^"([^"]+)" changed name to "([^"]+)"/

[connected]
  regex = /^"([^"]+)" connected, address "([^"]+)"/

[connected_steamid]
  regex = /^"([^"]+)" (?:STEAM|VALVE) USERID (.+)/
  options = ignore

[disconnected]
  regex = /^"([^"]+)" disconnected/

[chat]
  regex = /^"([^"]+)" say(_team)* "(.*)"?(.*)/
#  options = ignore

[rcon]
  regex = /^(Bad )?Rcon: "rcon (-*\d+) "?(.*?)"? (.+?)(?:" from "([^"]+)")?/

[cheated]
  regex = /^Secure: "([^"]+)" was detected cheating/

# was kicked and banned "for 30.00 minutes" by "Console"
# was kicked and banned "permanently" by "Console"
# was banned "permanently" by "Console"
[ban]
#  regex = /^(Addip|Ban(?:id)?): "([^"]+)" was (?:kicked and )?(?:banned)(?: by IP)? "([^"]+)" by "([^"]+)"(.*)/
  regex = /^(Addip|Ban(?:id)?): "([^"]+)" was (?:kicked and )?(?:banned)(?: by IP)? "([^"]+)" by "([^"]+)"(.*)/

[unban]
  regex = /^Remove(id|ip): "([^"]+)" was unbanned by "([^"]+)"(.*)/

#[removeipban]
#  regex = /^Removeip: "([^"]+)" was unbanned by "([^"]+)"(.*)/

[kick]
  regex = /^Kick: "([^"]+)" was kicked by "([^"]+)"(.*)/

[pingkick]
  regex = /^"([^"]+)" kicked due to high ping(.*)/

[ffkick]
  regex = /^"([^"]+)" has been auto kicked from the game for TKing/

[startedmap]
  regex = /^(Started|Loading) map "([^"]+)"(.*)/

[logstartend]
  regex = /^Log file (started|closed)(.*)/

[ignored1]
  regex = /^[Ss]erver (?:cvars?|say|shutdown)/
  options = ignore

[ignored2]
  regex = /^(?:\]TSC\[|Succeeded|FATAL|-)/
  options = ignore

[ignored3]
  regex = /^(?:Config|Swear|server_(?:cvar|message))/
  options = ignore

# newer steam servers do not prepend "server_cvar" in front of cvars. 
[ignored4]
  regex = /^"[^"]+" = "/
  options = ignore

[ignored5]
  regex = /^CONSOLE :/
  options = ignore

