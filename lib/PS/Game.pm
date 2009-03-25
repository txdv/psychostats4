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
# 	$Id$
#
package PS::Game;

use strict;
use warnings;
use base qw( PS::Core );

use Carp;
use FindBin;
use File::Spec::Functions;
use POSIX qw(floor strftime mktime);
use Time::Local;
use Safe;
use Encode qw( encode_utf8 decode_utf8 );

use PS::SourceFilter;
use PS::Award;
use PS::Conf;
use PS::Map;
use PS::Plr;
use PS::Map;
use PS::Role;
use PS::Weapon;
use util qw( :date :time :numbers :net print_r deep_copy is_regex bench );
use serialize;

our $VERSION = '4.00.' . (('$Rev$' =~ /(\d+)/)[0] || '000');

our ($CONF, $OPT);

sub new {
	my $proto = shift;
	my $gametype = shift;
	my $modtype = shift;
	my $db = shift;
	my $class = ref($proto) || $proto;
	my $self = {
		gametype => $gametype,
		modtype => $modtype,
		db => $db,
	};
	
	__PACKAGE__->fatal("A 'gametype' must be specified to create a $class object.") unless $gametype;
	$class .= "::$gametype";
	$class .= "::$modtype" if $modtype;

	$self->{class} = $class;

	# add our subclass into the frey ...
	eval "require $class";
	if ($@) {
		__PACKAGE__->fatal("\n-----\nCompilation errors with Game class '$class':\n$@\n-----\n");
	}

	bless($self, $class);
	return $self->init;
}

# called from sub-classes to initialize the object
sub init {
	my $self = shift;
	my $db = $self->{db};
	my $opt = $self->opt;
	my $main = $self->conf->main;

	# prepare any queries we'll be running repeatedly...
	#$db->prepare('get_clantags', 	'SELECT * FROM t_config_clantags WHERE type=? ORDER BY idx');
	$db->prepare('get_clantags', 	'SELECT id,clantag,alias,pos,type,blacklist FROM t_config_clantags ORDER BY idx');
	$db->prepare('get_clan', 	'SELECT clanid,locked,rank,firstseen FROM t_clan WHERE clantag=? LIMIT 1');
	$db->prepare('insert_clan', 	'INSERT INTO t_clan (clantag, firstseen) VALUES (?,?)');
	$db->prepare('insert_clan_profile', 'INSERT INTO t_clan_profile SET clantag=?');
	$db->prepare('get_plr_alias', 	'SELECT to_uniqueid FROM t_config_plraliases WHERE from_uniqueid=? LIMIT 1');

	# initialize game event pattern matching ...
	$self->{evconf} = {};
	$self->{evorder} = [];
	$self->{evorder_idx} = 0;
	$self->{evregex} = undef;
	$self->{evloaded} = {};			# keep track of which dynamic event methods were loaded already
	$self->load_events('','');		# load global events
	$self->load_events(undef,'');		# load events for the gametype
	$self->load_events(undef,undef);	# load events for the game:mod
	$self->init_events;

	# initialize player action bonuses ... 
	$self->{bonuses} = {};
	$self->load_bonuses('','');		# load global bonuses
	$self->load_bonuses(undef,'');		# load bonuses for the gametype
	$self->load_bonuses(undef,undef);	# load bonuses for the game:mod

	# DEBUG option to dump the events list to help verify the proper bonuses
	# are being loaded
	#if ($opt->dumpbonuses) {
	#	my $width = 0;
	#	foreach my $ev (keys %{$self->{bonuses}}) {
	#		$width = length($ev) if length($ev) > $width;
	#	}
	#	printf("%-${width}s %5s %5s %5s %5s\n", "Player Bonuses", "E","ET", "V", "VT");
	#	foreach my $ev (sort keys %{$self->{bonuses}}) {
	#		printf("%-${width}s %5d,%5d,%5d,%5d\n", 
	#			$ev,
	#			$self->{bonuses}{$ev}{enactor},
	#			$self->{bonuses}{$ev}{enactor_team},
	#			$self->{bonuses}{$ev}{victim},
	#			$self->{bonuses}{$ev}{victim_team}
	#		);
	#	}
	#	#main::exit();
	#}

	$self->{_plraliases} = {};		# stores a cache of player aliases fetched from the database
	$self->{_plraliases_age} = time;

	$self->{_clans} = {};			# load clan cache
	$self->{_clans_age} = time;

	$self->{banned}{worldid} = {};
	$self->{banned}{ipaddr} = {};
	$self->{banned}{name} = {};
	$self->{banned_age} = time;

	# load some config options locally... 
	#$self->{baseskill} = $main->baseskill;
	#$self->{maxdays} = $main->maxdays;

	$self->{uniqueid} = $main->uniqueid;

	# initialize the skill function for KILLS
	$self->add_calcskill_func('kill', $main->calcskill_kill);
	
	# initialize the clantags from the config for pattern matching against
	# player names.
	if ($self->conf->main->clantag_detection) {
		$self->load_clantags;
	}

	# track the last time we saved all stats for this game.
	$self->{last_saved} = time;
	# how often to save game stats. 10 seconds by default (real time).
	$self->{save_interval} = 10; #$self->conf->main->game_save_interval || 10;

	return $self;
}

# load configured clantags and prepare a compiled list for scanning through.
sub load_clantags {
	my ($self) = @_;
	my $db = $self->{db};
	my $clantags = $db->execute_fetchall('get_clantags');

	$self->{clantags} = [];
	$self->{clantags_blacklist} = [];

	# shown warnings for any invalid regex's that will be ignored...
	foreach my $ct (@$clantags) {
		$ct->{$_} = decode_utf8($ct->{$_}) for qw( clantag alias );
		
		if ($ct->{type} eq 'regex') {
			if (!is_regex($ct->{clantag})) {
				$self->warn("Error in clantag #$ct->{id} definition: /$ct->{clantag}/: $@");
				next;
			}
			# compile the regex
			$ct->{regex} = qr/$ct->{clantag}/;
		} elsif ($ct->{type} eq 'plain') {
			if ($ct->{clantag} eq '') {
				$self->warn("Empty clantag #$ct->{id} will be ignored.");
				next;
			}
		}
		if ($ct->{blacklist}) {
			push(@{$self->{clantags_blacklist}}, [ @$ct{qw( type clantag regex pos )} ]);
		} else {
			push(@{$self->{clantags}}, [ @$ct{qw( type clantag regex pos alias )} ]);
		}
	}
	#print_r($self->{clantags});
	#print_r($self->{clantags_blacklist});
}

my $clantag_debug = 0;
# Returns the clantag match if the name matches one of clantag definitions.
# Returns undef if no match.
sub clantag_match {
	my ($self, $name, $list, $blacklist) = @_;
	my ($type, $tag, $regex, $pos, $alias, $match, $idx);
	$idx = 0;
	foreach my $ct (@$list) {
		($type, $tag, $regex, $pos, $alias) = @$ct;
		;;; warn "  Checking $type: $tag\n" if $clantag_debug;
		if ($type eq 'regex') {
			if (my @m = ($name =~ $regex)) {
				# only use grouping $1 for match
				$match = defined $alias ? $alias : $1; #join('', @m);
				$match = '' unless defined $match;
				;;; warn "    regex matched '$match'\n" if $clantag_debug;
			}
		} elsif ($type eq 'plain') {
			if ($pos eq 'left') {
				$match = defined $alias ? $alias : $tag
					if index($name, $tag) == 0;
			} else { # right
				$match = defined $alias ? $alias : $tag
					if index(scalar reverse($name), scalar reverse($tag)) == 0;
			}
		}

		# make sure the match isn't actually blacklisted
		if (defined $match) {
			if ($blacklist) {
				;;; warn "  Checking blacklist for $match\n" if $clantag_debug;
				if (defined $self->clantag_match($name, $blacklist)) {
					;;; warn "    $match IS blacklisted\n" if $clantag_debug;
				} else {
					# make sure the clan isn't locked
					my $clan = $self->get_clan($match, 1);
					last if !$clan or !$clan->{locked};
					;;; warn "    $match is locked\n" if $clantag_debug;
				}
			} else {
				# make sure the clan isn't locked
				my $clan = $self->get_clan($match, 1);
				last if !$clan or !$clan->{locked};
				;;; warn "    $match is locked\n" if $clantag_debug;
			}
			$match = undef;
		}
		++$idx;
	}
	if (wantarray) {
		return defined $match ? ($match, [ $idx, @{$list->[$idx]} ]) : undef;
	} else {
		return $match;
	}
}

# scans the given player name for a matching clantag from the database. Creates
# a new clan+profile if it's a new tag. $p is either a PS::Plr object, or a
# plain scalar string to match. Returns the matching clanid or undef.
# In array context it returns the tag, and a clan hashref of information.
sub scan_for_clantag {
	my ($self, $p) = @_;
	my $name = ref $p ? $p->name : $p;
	my ($match, $ct, $tag, $clan);
	
	;;; warn "Scanning \"$name\" for clantags.\n" if $clantag_debug;
	($match, $ct) = $self->clantag_match($name, @$self{qw( clantags clantags_blacklist )});
	# $match = matched clantag string
	# $ct    = [ index, type, clantag, regex, pos, alias ]
	if (defined $match) {
		;;; warn "  Matches: $match\n" if $clantag_debug;
		$tag = defined $ct->[5] ? $ct->[5] : $match;		# use alias if defined
		if (wantarray) {
			return ($tag, $self->get_clan($tag));
		} else {
			return $tag;
		}
	}
	return undef;
}

# returns the basic clan info based on the clantag given. Creates a new clan
# record if it doesn't already exist (unless $check_only is true)
sub get_clan {
	my ($self, $clantag, $check_only) = @_;
	my $clan;
	if (time - $self->{_clans_age} > 60*10) {
		# clear the cache after X minutes ... 
		%{$self->{_clans}} = ();
		$self->{_clans_age} = time;
	}
	
	$clan = $self->{_clans}{$clantag}
		|| $self->{db}->execute_fetchrow('get_clan', $clantag);
	if ($clan or $check_only) {
		return $self->{_clans}{$clantag} = $clan;
	}

	my $time = $self->{timestamp} || timegm(localtime);
	# create the clan record
	if (!$self->{db}->execute('insert_clan', $clantag, $time)) {
		$self->warn("Error inserting clan '$clantag' into DB: " . $self->{db}->lasterr);
		return undef;
	}

	# create the current record in memory (clanid is auto_increment)
	$clan = $self->{_clans}{$clantag} = {
		clanid => $self->{db}->last_insert_id,
		locked => 0,
		rank => 0,
		firstseen => $time
	};

	# create the clan profile. This will silently ignore duplicate entries
	if (!$self->{db}->execute('insert_clan_profile', $clantag)) {
		$self->warn("Error inserting clan profile for '$clantag' into DB: " . $self->{db}->lasterr);
	}
	
	return $clan;
}

# creates a skill function based on the type and configured function.
# creates functions named calcskill_$type_func() and calcskill_$type_init()
sub add_calcskill_func {
	my ($self, $type, $func) = @_;
	my $calcskill = 'calcskill_' . $type;		# calcskill_kill
	my $calcskill_func = $calcskill . '_' . $func;	# calcskill_kill_default
	my $calcskill_init = $calcskill_func . '_init';	# calcskill_kill_init

	# If the function exists there is no reason to redefine it...	
	if ($self->can($calcskill_func)) {
		return undef;	
	}
	
	# try to load the skill calculation code
	my $file = catfile($FindBin::Bin, 'lib', 'PS', 'Skill', $calcskill_func . '.pl');
	if (-f $file) {
		my $code = "";
		if (open(F, "<$file")) {
			$code = join('', <F>);
			close(F);
		} else {
			$self->fatal("Error reading skill code file ($file): $!");
		}

		# sanity check; if the eval fails then ignore this code
		my $eval = new Safe;
		$eval->permit(qw( sort ));
		$eval->reval($code);
		if ($@) {
			$self->fatal("Error in skill code '$calcskill': $@");
		} else {
			# eval it in the current scope
			# this has to be done since reval() makes it private in its own scope
			eval $code;
		}
	} else {
		$self->fatal(
			"Error reading skill code '" . $func . "' file $file\n" . 
			"File does not exist.\n" . 
			"Are you sure you're using the correct skill calculation?\n" . 
			"Try changing the 'Skill Calculation' config setting to 'default'."
		);
	}

	# if there is still no method available, we die ... 
	if (!$self->can($calcskill_func)) { # or $self->{$calcskill} eq 'func' or $self->{$calcskill} eq 'init') {
		$self->fatal("Invalid skill function configured ($func) " . 
			"Try using 'default' or 'alternative' instead."
		);
	}

	{ 	# LOCALIZE BLOCK
		# make an alias in the object for the skill function. This way,
		# we don't have to constantly dereference a variable to call the
		# function in the 'event_kill' routine.
		# $self->calcskill_kill_func will now work
		no strict 'refs';
		my $func = __PACKAGE__ . '::' . $calcskill . '_func';
		# only define the static method once.
		if (!$self->can($calcskill . '_func')) {
			*$func 	 = $self->can($calcskill_func) || sub { die "Abstract call to $calcskill_func\n" };
		}
		# create specific init sub. A generic function is not created.
		# $self->calcskill_kill_{$type}_init
		$func 	 = __PACKAGE__ . '::' . $calcskill_init;
		*$func 	 = $self->can($calcskill_init) || sub { };

		# run init code for skill calculation, if available
		$self->$calcskill_init;
	}

}

sub init_ipcache {
	$_[0]->{ipcache} = {};		# player IPADDR cache, keyed on UID
}

# there are anomalys that cause players to not always be detected by a single 
# criteria. So we cache each way we know we can reference a player. 
sub init_plrcache {
	my $self = shift;

	$self->{c_eventsig} = {};	# players keyed on their signature string
	$self->{c_guid} = {};		# players keyed on their GUID
	$self->{c_uid} = {};		# players keyed on UID
	$self->{c_plrid} = {};		# players keyed on plrid
}

# add a plr to the cache
sub add_plrcache {
	my ($self, $p, $sig, $cache) = @_;
	if ($sig) {
		# $sig is a cache signature name
		$cache ||= 'eventsig';
		$self->{'c_'.$cache}{$sig} = $p;
	} else {
		# add plr to all caches
		$self->{c_eventsig}{$p->eventsig} = $p;
		$self->{c_guid}{$p->guid} = $p if $p->guid;
		$self->{c_uid}{$p->uid} = $p if $p->uid;
		$self->{c_plrid}{$p->id} = $p if $p->id;
	}
}

# remove a plr from the cache
sub del_plrcache {
	my ($self, $sig, $cache) = @_;
	if (!ref $sig) {
		# $sig is a cache signature name
		$cache ||= 'eventsig';
		delete $self->{'c_'.$cache}{$sig};
	} else {
		# remove plr from all caches ($sig is a ref for PS::Plr)
		delete $self->{c_eventsig}{$sig->eventsig};
		delete $self->{c_guid}{$sig->guid} if $sig->guid;
		delete $self->{c_uid}{$sig->uid} if $sig->uid;
		delete $self->{c_plrid}{$sig->id} if $sig->id;
	}
}

# get a list of all unique players currently cached.
sub get_plrcache {
	my ($self) = @_;
	my %uniq;
	my @list;
	@list = (
		grep { !$uniq{\$_}++ }		# REF is unique
		values %{$self->{c_eventsig}}
	);
	return wantarray ? @list : \@list;
}

# return the cached plr or undef if not found
sub plrcached {
	my ($self, $sig, $cache) = @_;
	return undef unless defined $sig;
	$cache ||= 'eventsig';
	return exists $self->{'c_'.$cache}{$sig} ? $self->{'c_'.$cache}{$sig} : undef;
}

# debug method; prints out some information about the player caches
sub show_plrcache {
	my ($self) = @_;
	#printf("CACHE INFO: sig:% 3d  guid: % 3d  uid:% 3d  plrid: % 3d  active: % 3d\n",
	#printf("CACHE INFO: s:% 3d g: % 3d u:% 3d p: % 3d a: % 3d\n",
	printf("PLRCACHE: % 3d % 3d % 3d % 3d % 3d (sig,guid,uid,plrid,active)\n",
		scalar keys %{$self->{'c_eventsig'}},
		scalar keys %{$self->{'c_guid'}},
		scalar keys %{$self->{'c_uid'}},
		scalar keys %{$self->{'c_plrid'}}
	);
}

sub init_online { $_[0]->{c_online} = {} }

# keep track of players that are currently online (based on UID)
sub plr_online {
	my ($self, $p) = @_;
	$self->{c_online}{$p->uid} = $p;
}

# returns true if the plr given is considered online or not.
sub plr_is_online { exists $_[0]->{c_online}{$_[1]->uid}; }

# remove a player from the online pool.
sub plr_offline {
	my ($self, $p) = @_;
	delete $self->{c_online}{$p->uid};
}

# return a list of online players, optionally based on their team (get_team)
sub get_online_plrs {
	my ($self, $team) = @_;
	my @list;
	if ($team) {
		@list = grep { $_->team eq $team } values %{$self->{c_online}};
	} else {
		@list = values %{$self->{c_online}};
	}
	return wantarray ? @list : [ @list ];
}

# return the cached IP or undef if not found
sub ipcached {
	my ($self, $uid, $var) = @_;
	return undef unless exists $self->{ipcache}{$uid};
	# return the ip and time it was updated if in array context. Otherwise
	# simply return the IP address.
	return wantarray
		# the bit of "line noise" below is a hash slice, if you look
		# closely enough.
		? @{%{$self->{ipcache}{$uid}}}{qw( ipaddr time )}
		: $self->{ipcache}{$uid}{$var || 'ipaddr'};
}

sub get_ipcache {
	my ($self) = @_;
	return $self->{ipcache};
}

# add an IP to the internal cache keyed on the UID given.
sub add_ipcache {
	my ($self, $uid, $ip, $timestamp) = @_;
	$self->{ipcache}{$uid}{ipaddr} = $ip;
	$self->{ipcache}{$uid}{time} = $timestamp || $self->{timestamp};
	#$self->debug2("Cached IP " . int2ip($ip) . " at " . gmtime($self->{ipcache}{$uid}{time}), 0);
}

# remove an IP from the cache based on the UID given.
sub del_ipcache {
	my ($self, $uid) = @_;
	delete $self->{ipcache}{$uid};
}

# cleanup the IP cache and remove stale items (older than 1 hour; GAME TIME)
sub clean_ipcache {
	my ($self, $age) = @_;
	my $time = $self->{timestamp} || 0;
	my $total = scalar keys %{$self->{ipcache}};
	my @stale;
	$age ||= 60*60;
	@stale = grep { $time - $self->{ipcache}{$_}{time} > $age } keys %{$self->{ipcache}};
	if (@stale) {
		;;; $self->debug4("Removing " . @stale . "/$total stale IP addresses from cache.", 0);
		delete @{$self->{ipcache}}{ @stale };
	}
}

# normalize a role name
sub role_normal { defined $_[1] ? lc $_[1] : '' }

# normalize a team name
sub team_normal { defined $_[1] ? lc $_[1] : '' }

# normalize a weapon name
sub weapon_normal { defined $_[1] ? lc $_[1] : '' }

# returns a PS::Map object matching the map $name given
sub get_map {
	my ($self, $name) = @_;
	$name ||= $self->{curmap} || 'unknown';
	if (exists $self->{maps}{$name}) {
		return $self->{maps}{$name};
	}
	return $self->{maps}{$name} = new PS::Map($name, @$self{qw( gametype modtype timestamp )});
}

# returns a PS::Weapon object matching the weapon $name given
sub get_weapon {
	my ($self, $name) = @_;
	$name = $self->weapon_normal($name) || return undef;
	if (exists $self->{weapons}{$name}) {
		return $self->{weapons}{$name};
	}
	return $self->{weapons}{$name} = new PS::Weapon($name, @$self{qw( gametype modtype timestamp )});
}

# returns a PS::Role object matching the role $name given
sub get_role {
	my ($self, $name, $team) = @_;
	return undef unless $name;
	$name = $self->role_normal($name) || return undef;
	if (exists $self->{roles}{$name}) {
		return $self->{roles}{$name};
	}
	return $self->{roles}{$name} = new PS::Role($name, $team, @$self{qw( gametype modtype timestamp )});
}

# Add's a player BAN to the database. 
# Does nothing If the ban already exists unless $overwrite is true
# ->addban(plr, {extra})
sub addban {
	my $self = shift;
	my $plr = shift;
	my $matchtype = 'worldid';
	my $matchstr = $plr->worldid;
	my $opts = ref $_[0] ? shift : { @_ };
	my $overwrite = 0;
	if (exists $opts->{overwrite}) {
		$overwrite = $opts->{overwrite};
		delete $opts->{overwrite};
	}

#	$matchtype = 'worldid' unless $matchtype =~ /^(worldid|ipaddr|name)$/;

	my $db = $self->{db};
	my $str = $db->quote($matchstr);
	my ($exists,$enabled) = $db->get_row_array("SELECT id,enabled FROM $db->{t_config_plrbans} WHERE matchtype='$matchtype' AND matchstr=$str LIMIT 1");

	if (!$exists or $overwrite) {
		my $set = {
			'matchtype'	=> $matchtype,
			'matchstr'	=> $matchstr,
			'enabled'	=> defined $opts->{enabled} ? $opts->{enabled} : 1,
			'ban_date'	=> $opts->{ban_date} || time,
			'ban_reason'	=> $opts->{reason} || ''
		};

		if ($exists) {
			$db->update($db->{t_config_plrbans}, $set, 'id', $exists);
		} else {
			# add the ban to the config
			$set->{id} = $db->next_id($db->{t_config_plrbans});
			$db->insert($db->{t_config_plrbans}, $set);
		}

		# add a plrban record for historical purposes (only if there isn't already an active ban)
		my ($active) = $db->get_row_array("SELECT 1 FROM $db->{t_plr_bans} WHERE plrid=" . $plr->plrid . " AND unban_date IS NULL");
		if (!$active) {
			$set = {
				'plrid'		=> $plr->plrid,
				'ban_date'	=> $opts->{ban_date} || time,
				'ban_reason'	=> $opts->{reason}
			};
			$db->insert($db->{t_plr_bans}, $set);
		}

	} elsif (defined $opts->{enabled} and $opts->{enabled} ne $enabled) {
		$db->update($db->{t_config_plrbans}, { enabled => $opts->{enabled} }, [ id => $exists ]);
	}
}

# Removes a player BAN from the database. 
# ->unban(plr)
sub unban {
	my $self = shift;
	my $plr = shift;
	my $opts = ref $_[0] ? shift : { @_ };
	my $matchtype = 'worldid';
	my $matchstr = $plr->worldid;

#	$matchtype = 'worldid' unless $matchtype =~ /^(worldid|ipaddr|name)$/;

	my $db = $self->{db};
	my $str = $db->quote($matchstr);

	# delete the config record
	$db->query("DELETE FROM $db->{t_config_plrbans} WHERE matchtype='$matchtype' AND matchstr=$str");

	# update the active ban record for the player
	my ($active) = $db->get_row_array("SELECT ban_date FROM $db->{t_plr_bans} WHERE plrid=" . $plr->plrid . " AND unban_date IS NULL");
	if ($active) {
		$db->update($db->{t_plr_bans}, 
			{ unban_date => $opts->{unban_date} || time, 'unban_reason' => $opts->{reason} }, 
			[ plrid => $plr->plrid, 'ban_date' => $active ]
		);
	}
}

# returns true if any of the criteria given matches an enabled BAN record
# ->isbanned(worldid => '', ipaddr => '', name => '')
sub isbanned {
	my $self = shift;
	my $m = ref $_[0] eq 'HASH' ? shift : ref $_[0] ? $_[0]->{plrids} : { @_ };
	my $banned = 0;
#	either pass a hash of values, or a PS::Player record, or key => value pairs

	# clear the banned cache every X minutes (real-time)
	if (time - $self->{banned_age} > 60*5) {
		#;;; $self->debug("CLEARING BANNED CACHE");
		$self->{banned}{worldid} = {};
		$self->{banned}{ipaddr} = {};
		$self->{banned}{name} = {};
		$self->{banned_age} = time;
	}

	my ($matchstr);
	foreach my $match (qw( worldid name ipaddr )) {
		next unless exists $m->{$match} and defined $m->{$match};
		return $self->{banned}{$match}{ $m->{$match} } if exists $self->{banned}{$match}{ $m->{$match} };

		$matchstr = ($match eq 'ipaddr') ? int2ip($m->{$match}) : $m->{$match}; 
		($banned) = $self->{db}->get_row_array(
			"SELECT id FROM $self->{db}{t_config_plrbans} " . 
			"WHERE enabled=1 AND matchtype='$match' AND " . $self->{db}->quote($matchstr) . " LIKE matchstr"
		);
		$self->{banned}{$match}{ $m->{$match} } = $banned;
		return $banned if $banned;
	}

	return 0;
}

# prepares the EVENT patterns for fast matching
sub init_events {
	my $self = shift;
	return if $self->{evregex};	# only need to initialize once
	# sort all event patterns in the order they were configured
	$self->{evorder} = [
		sort { $self->{evconf}{$a}{idx} <=> $self->{evconf}{$b}{idx} }
		keys %{$self->{evconf}}
	];
	$self->{evregex} = $self->create_event_regex_func;
}

# returns a sub ref for a function that returns a pattern match against the
# game events configured for fast pattern matching.
sub create_event_regex_func {
	my $self = shift;
	my $code = '';
	my $env = new Safe;

	foreach my $ev (@{$self->{evorder}}) {
		my $regex = $self->{evconf}{$ev}{regex};
		my $event = 'event_' . ($self->{evconf}{$ev}{alias} || $ev);
		# make sure a regex was configured
		unless ($regex) {
			$self->warn("Ignoring event '$ev' (No regex defined)");
			next;
		}
		# make sure regex is /surrounded/
		unless ($regex =~ m|^/.+/$|) {
			$self->warn("Ignoring event '$ev' (Invalid terminators)");
			next;
		}
		# verify a method exists (unless its configured to be ignored)
		if (!$self->{evconf}{$ev}{ignore} and !$self->can($event)) {
			$self->warn("Ignoring event '$ev' (No method available)");
			$self->{evconf}{$ev}{ignore} = 1;
		}

		# test the regex syntax (safely; avoid code injections)
		$env->reval($regex);
		if ($@) {
			$self->warn("Invalid regex for event '$ev' regex $regex:\n$@");
			next;
		}
		$code .= "  return ('$ev',\\\@parms) if \@parms = (\$_[0] =~ $regex);\n";
	}
	
	# debug: -dumpevents on command line to see the sub that is created
	if ($self->opt->dumpevents) {
		print "sub {\n  my \@parms = ();\n$code  return (undef,undef);\n}\n";
		main::exit();
	}
	return eval "sub { my \@parms = (); $code return (undef,undef) }";
}

# abstract event method. All sub classes must override.
sub event { $_[0]->fatal($_[0]->{class} . " has no 'event' method implemented. HALTING.") }
sub event_ignore { }

# Loads the player bonuses config for the current game. May be called several
# times to overload previous values.
# game/mod type 'undef' means to use the current config settings. a blank string
# will load global values
sub load_bonuses {
	my $self = shift;
	my ($gametype, $modtype) = @_;
	my $db = $self->{db};
	my $g = defined $gametype ? $gametype : $self->{gametype}; #$self->conf->main->gametype;
	my $m = defined $modtype  ? $modtype  : $self->{modtype};  #$self->conf->main->modtype;
	my $match = '';
	my @bonuses = ();

	$match .= $g ? $db->quote($g) . " REGEXP CONCAT('^', gametype, '\$')" : "(gametype='' OR gametype IS NULL)";
	$match .= " AND ";
	$match .= $m ? $db->quote($m) . " REGEXP CONCAT('^', modtype, '\$')" : "(modtype='' OR modtype IS NULL)";
	@bonuses = $db->get_rows_hash("SELECT * FROM $db->{t_config_plrbonuses} WHERE $match");

	foreach my $b (@bonuses) {
		$self->{bonuses}{ $b->{eventname} } = $b;
	}
}

# Loads the event config for the current game.
# May be called several times to overload previous values.
# game/mod type 'undef' means to use the current config settings. A blank string
# will load global values.
sub load_events {
	my $self = shift;
	my ($gametype, $modtype) = @_;
	my $db = $self->{db};
	my $g = defined $gametype ? $gametype : $self->{gametype};
	my $m = defined $modtype  ? $modtype  : $self->{modtype};
	my $path = catfile($FindBin::Bin, 'lib', 'PS', 'Events', '');
	my $match = '';
	my @events = ();

	$match .= $g ? $db->quote($g) . " REGEXP CONCAT('^', gametype, '\$')" : "(gametype='' OR gametype IS NULL)";
	$match .= " AND ";
	$match .= $m ? $db->quote($m) . " REGEXP CONCAT('^', modtype, '\$')" : "(modtype='' OR modtype IS NULL)";
	@events = $db->get_rows_hash(
		"SELECT * " .
		"FROM $db->{t_config_events} WHERE $match ORDER BY idx"
	);

	foreach my $e (@events) {
		# load the event code if there is a file matching the event
		my $event = 'event_' . ($e->{codefile} || $e->{eventname});
		my $file = $event . '.pl';
		if (-f "$path$file") {
			# sanity check; do not allow files larger then 512k
			if (-s "$path$file" > 1024*512) {
				$self->warn("Error in event code '$event': File size too large (>512k)");
				next;
			}

			my $code = "";
			if (open(F, "<$path$file")) {
				$code = join('', <F>);
				close(F);
			} else {
				$self->warn("Error reading event code 'event': $!");
				next;
			}

			# sanity check; if the eval fails then ignore this code
			my $eval = new Safe;
			$eval->reval($code);
			if ($@) {
				$self->warn("Error in event code '$event': $@");
				next;
			} else {
				# Eval it in the current scope. This has to be
				# done since reval() makes it private in its own
				# scope
				eval $code;
			}

			$self->{evloaded}{$event} = "$path$file";
		} else {
			my $func = $e->{alias} ? 'event_' . $e->{alias} : $event;
			# only error if a codefile was specified in the config
			if (!$self->can($func) and $e->{codefile}) {
				$self->warn("Error loading event code for '$event': $path$file: File does not exist");
				next;
			}
		}

		my $i = 0;
		if (exists $self->{evconf}{$e->{eventname}}) {
			$i = $self->{evconf}{$e->{eventname}}{idx};
			$self->{evorder_idx} = $i if $i > $self->{evorder_idx};
		} else {
			$i = ++$self->{evorder_idx};
		}
		$self->{evconf}{$e->{eventname}} = {
			alias		=> $e->{alias},
			regex		=> $e->{regex},
			idx		=> $i, 
			ignore		=> $e->{ignore},
			codefile	=> $e->{codefile}
		};
	}
}

# returns the 'alias' for the uniqueid given.
# If no alias exists the same uniqueid given is returned.
sub get_plr_alias {
	my ($self, $uniqueid) = @_;
	my $alias;
	if (time - $self->{_plraliases_age} > 60*15) {
		# clear the aliases cache after 15 mins (real-time)
		$self->{_plraliases} = {};
		$self->{_plraliases_age} = time;
	}
	if (exists $self->{_plraliases}{$uniqueid}) {
		$alias = $self->{_plraliases}{$uniqueid};
	} else {
		$alias = $self->{db}->execute_selectcol('get_plr_alias', $uniqueid);
		$alias = decode_utf8($alias);
		$self->{_plraliases}{$uniqueid} = $alias;
		;;;# debugging
		;;;if (defined $alias) {
		;;;	$self->debug3("ALIAS: $uniqueid  =>  $alias", 0);
		;;;}
	}
	return (defined $alias and $alias ne '') ? $alias : $uniqueid;
}

# collects variables from the current object to save into the state.
# subclasses that require special variables should override to save their vars.
sub collect_state_vars {
	my ($self) = @_;
	my $state = {};

	# collect stateful game variables ...
	$state->{timestamp}	= $self->{timestamp};
	$state->{curmap}	= $self->{curmap};
	$state->{ipcache}	= $self->get_ipcache;
	$state->{players}	= [];

	foreach my $p ($self->get_online_plrs) {
		push(@{$state->{players}}, $p->freeze);
	}
	
	return $state;
}

# retores state variables for the game.
# Subclasses should override for special vars as needed.
sub restore_state_vars {
	my ($self, $state) = @_;

	$self->{timestamp} 	= $state->{timestamp} || 0;
	$self->{curmap} 	= $state->{curmap} || 'unknown';
	$self->{ipcache}	= $state->{ipcache} ? deep_copy($state->{ipcache}) : {};
	
	# restore the players that were online
	if ($state->{players}) {
		foreach my $plr (@{$state->{players}}) {
			my $p = PS::Plr->unfreeze($plr);
			$self->add_plrcache($p);
			$self->plr_online($p);
		}
	}
}

# saves the current game state which is associated with the $feed.
sub save_state {
	my ($self, $feed, $srv, $db) = @_;
	my $id = $feed->capture_state->{id};
	my ($st, $str, $curstate, $newstate);
	my $state = {};
	return unless $id;		# feed state should already exist
	$db ||= $self->{db};
	$srv ||= $feed->server;

	$state = $self->collect_state_vars;

	# load the current game state for the feeder
	$st = $db->prepare('SELECT game_state FROM t_state WHERE id=?');
	$st->execute($id) or return 0;
	$st->bind_columns(\$str);
	$st->fetch;
	$st->finish;

	# Must decode string as UTF8
	$str = decode_utf8($str);

	# unserialize the game state into a real variable
	$curstate = $self->unserialize_state($str);
	$curstate = {} unless ref $curstate eq 'HASH';

	# add our local state to the saved state
	$curstate->{$srv} = $state;

	# serialize the new state
	$newstate = $self->serialize_state($curstate);
	$newstate = encode_utf8($newstate);

	# save it all!
	$st = $db->prepare('UPDATE t_state SET game_state=? WHERE id=?');
	if (!$st->execute($newstate, $id)) {
		$self->warn("Error saving game state: " . $st->errstr);
		return 0;
	}

	return 1;
}

# restores the game state from a previous run.
# $feed is the feeder used.
# $srv is the server identifier used to save the previous game state.
sub restore_state {
	my ($self, $feed, $srv, $db) = @_;
	my $id = $feed->capture_state->{id};
	my ($st, $str, $state);
	return unless $id;		# feed state should already exist
	$db ||= $self->{db};
	$srv ||= $feed->server;

	# load the current game state for the feeder
	$st = $db->prepare('SELECT game_state FROM t_state WHERE id=?');
	$st->execute($id) or return 0;	# SQL error ...
	$st->bind_columns(\$str);
	$st->fetch;
	$st->finish;

	# Must decode string as UTF8
	$str = decode_utf8($str);
	
	$state = $self->unserialize_state($str);
	$self->post_process_state($state);

	# If there is no state saved for the specified server then we're done
	return 0 unless exists $state->{$srv};
	$self->restore_state_vars($state->{$srv});
	
	return 1;
}

# serialize a state hash variable into a string for DB storage.
sub serialize_state {
	my ($self, $state) = @_;
	my $str = serialize($state);
	return $str;
}

# unserializes a state string into a real hash variable.
sub unserialize_state {
	my ($self, $str) = @_;
	my $state = $str ? unserialize($str) : {};
	return $state;
}

# Post process a state variable.
# This is used to massage or fix certain elements within the state due to
# differences in the serialization process between PHP and Perl.
sub post_process_state {
	my ($self, $state) = @_;

	if (ref $state) {
		# correct some 'side-effects' of the serialize() process,
		# some arrays are serialized as hashes. We must fix that.
		foreach my $server (keys %$state) {
			# convert player hash into an array
			if (ref $state->{$server}{players} eq 'HASH') {
				$state->{$server}{players} = [ values %{$state->{$server}{players}} ];
			}

			# convert player 'ids' hashes into arrays
			foreach my $plr (@{$state->{$server}{players}}) {
				if ($plr->{ids}) {
					foreach my $var (keys %{$plr->{ids}}) {
						foreach my $key (keys %{$plr->{ids}{$var}}) {
							$plr->{ids}{$var}{$key} = [
								map { $plr->{ids}{$var}{$key}{$_} }
								sort keys %{$plr->{ids}{$var}{$key}}
							];
						}
					}
				}
			}
		}
	}
	return $state;
}

# assign bonus points to players
# ->plrbonus('trigger', 'enactor type', $PLR/LIST, ... )
sub plrbonus {
	my $self = shift;
	my $trigger = shift;
	my ($newskill, $type, $entity, $val, $list);

	# do nothing if the bonus trigger doesn't exist
	return unless exists $self->{bonuses}{$trigger};

	while (@_) {
		$type = shift;
		next unless exists $self->{bonuses}{$trigger}{$type};
		$entity = shift || next;
		$val = $self->{bonuses}{$trigger}{$type};
		$list = (ref $entity eq 'ARRAY') ? $entity : [ $entity ];

		# assign bonus to players in our list
		foreach my $p (@$list) {
			next unless defined $p;
			$p->points($val) if $val > 0;	# add to points
			$p->skill($val, 1);		# add to skill
			#printf("%-32s received %3d points for %s (%s)\n", $p->name, $val, $trigger, $type);
		}
	}
}

# wrapper to update current players by checking ranking rules, assigning rank
# and activity values, etc.
sub update_plrs {
	my ($self, $quiet) = @_;
	
	$self->debug3("Updating player activity...", 0) unless $quiet;
	$self->update_plr_activity( $self->conf->main->plr_min_activity );
	
	$self->debug3("Updating player rank flags...", 0) unless $quiet;
	$self->update_allowed_plr_ranks( $self->conf->main->ranking->VARS );
	
	$self->debug3("Updating player ranks...", 0) unless $quiet;
	$self->update_plr_ranks;
}

# Updates the activity percentage for all players. If $force_all is true all
# player activity will be set to the $plr_min_activity value instead of
# calculating it for each player.
sub update_plr_activity {
	my ($self, $plr_min_activity, $force_all) = @_;
	my $db = $self->{db};
	my ($st, $lastseen);

	$plr_min_activity = abs($plr_min_activity || 0);
	return unless $plr_min_activity or $force_all;
	
	# determine the most recent timestamp available for players
	$lastseen = $db->max($db->{t_plr}, 'lastseen');
	return unless $lastseen;

	if ($force_all) {
		$st = $db->prepare("UPDATE t_plr SET activity=$plr_min_activity");
	} else {
		my $min_act = $plr_min_activity * 60*60*24;
		$st = $db->prepare(
			"UPDATE t_plr SET " . 
			"activity = IF($min_act > $lastseen - lastseen, " . 
			"LEAST(100, 100 / $min_act * ($min_act - ($lastseen - lastseen)) ), 0) "
		);
	}

	;;;bench('update_plr_activity');
	if (!$st->execute) {
		$self->warn("Error updating player activity values: " . $st->errstr);
	} else {
		my $affected = $st->rows;
		$self->debug3("$affected player activity values updated.", 0) if $affected;
	}
	;;;bench('update_plr_activity');
}

# Updates all players that are allowed to rank based on their current stats.
# $rules is a hash of min/max rules to use for determining who can rank.
# If $rules is a scalar then all players are set to be ranked based on the value
# given (either 0 or 1).
sub update_allowed_plr_ranks {
	my ($self, $rules, $force_all) = @_;
	my ($st, @min, @max, $where);
	my $db = $self->{db};
	my $cpref = $db->{dbtblcompiledprefix};
	my $type = $self->{modtype} ? $self->{gametype} . '_' . $self->{modtype} : $self->{gametype};

	if (!ref $rules) {
		# force all players 
		if ($rules) {	# everyone ranks
			# rank=0 means a player can rank, but has no actual rank
			# value yet. The update_plr_ranks() needs to be called
			# to update the actual rank values of players.
			$st = $db->prepare('UPDATE t_plr SET rank=0 WHERE rank IS NULL');
		} else {	# no one ranks
			$st = $db->prepare('UPDATE t_plr SET rank=NULL WHERE rank IS NOT NULL');
		}
		
	} elsif (!($st = $db->prepared('update_allowed_plr_ranks_' . $type))) {
		# prepare the queries if they haven't been already
	
		# collect min/max rule keys
		@min = ( map { s/^player_min_//; $_ } grep { /^player_min_/ && $rules->{$_} ne '' } keys %$rules );
		@max = ( map { s/^player_max_//; $_ } grep { /^player_max_/ && $rules->{$_} ne '' } keys %$rules );

		# build where clause to match players that meet requirements
		$where = join(' AND ', 
			(map { $_ . ' >= ' . $rules->{'player_min_' . $_} } @min),
			(map { $_ . ' <= ' . $rules->{'player_max_' . $_} } @max)
		);
		$self->debug3("Ranking rules for players: $where", 0);
		$where = '1' unless $where;	# force all if no rules are defined

		# query to update rank flag for all players based on rules
		$st = $db->prepare('update_allowed_plr_ranks_' . $type, 
			"UPDATE t_plr p, ${cpref}plr_data_${type} c " .
			"SET rank=IF($where, IF(rank,rank,0), NULL) " .
			"WHERE p.plrid=c.plrid"
		);
	}

	;;;bench('update_allowed_plr_ranks');
	if (!$st->execute) {
		$self->warn("Error updating allowed ranks for players: " .
			    $st->errstr . "\nCMD=" . $st->{Statement});
	} else {
		my $affected = $st->rows;
		$self->debug3("$affected player rank flags updated.", 0) if $affected;
	}
	;;;bench('update_allowed_plr_ranks');
}

# Assigns a rank to all players based on their skill. update_allowed_plr_ranks()
# should be called before this in order to properly rank players based on the
# current rules.
sub update_plr_ranks {
	my ($self, $timestamp) = @_;
	my $db = $self->{db};
	my ($plrid, $rank, $rank_prev, $rank_time, $skill);
	my ($get, $set, $newrank, $prevskill, $cmd);
	$timestamp ||= $self->{timestamp} || timegm(localtime);

	# prepare the query that will fetch the player list
	if (!defined($get = $db->prepared('get_plr_ranks'))) {
		# TODO: Allow this query to be customizable so different ranking
		# calculations can be done.
		$get = $db->prepare('get_plr_ranks',
			#'SELECT plrid,rank,rank_prev,rank_time,skill FROM t_plr ' .
			'SELECT plrid,rank,skill FROM t_plr ' .
			'WHERE rank IS NOT NULL AND skill IS NOT NULL ' .
			'AND gametype=? AND modtype=? ' .
			'ORDER BY skill DESC'
		);
	}
	if (!$get->execute(@$self{qw( gametype modtype )})) {
		$@ = "Error executing DB query:\n$get->{Statement}\n" . $db->errstr;
		return undef;
	}

	# prepare the query that will update a player rank
	if (!defined($set = $db->prepared('set_plr_rank'))) {
		$set = $db->prepare('set_plr_rank',
			'UPDATE t_plr SET ' .
			#'rank_prev=IF(DATE(FROM_UNIXTIME(?)) > DATE(FROM_UNIXTIME(rank_time)), rank, rank_prev), ' .
			#'rank_time=IF(rank_prev = rank, ?, rank_time), ' . 
			'rank=? ' . 
			'WHERE plrid=?'
		);
	}

	# Loop through all ranked players and set their new rank. Note; this
	# loop does not scale well and its speed depends on total players.
	# Players with the same value will have the same rank.
	$newrank = 0;
	;;;bench('update_plr_ranks');
	#$get->bind_columns(\($plrid, $rank, $rank_prev, $rank_time, $skill));
	$get->bind_columns(\($plrid, $rank, $skill));
	;;;my $affected = 0;
	while ($get->fetch) {
		++$newrank if !defined($prevskill) || $prevskill != $skill;
		#if (!$set->execute($timestamp, $timestamp, $newrank, $plrid)) {
		if ($rank != $newrank) {
			if (!$set->execute($newrank, $plrid)) {
				$self->warn("Error updating player ranks: CMD=$set->{Statement}; ERR=" . $set->errstr);
				last;
			}
			;;;++$affected;
		}
		#my $cmd = $set->{Statement};
		#$cmd =~ s/\?/$_/e foreach ($timestamp, $timestamp, $newrank, $plrid);
		#$cmd =~ s/\?/$_/e foreach ($newrank, $plrid);
		#print "$cmd\n";

		$prevskill = $skill;
	}
	;;;$self->debug3("$affected players changed rank.", 0) if $affected;
	;;;bench('update_plr_ranks');
	
	return 1;
}

# daily process for awards
sub daily_awards {
	my $self = shift;
	my $db = $self->{db};
	my $conf = $self->conf;
	my $lastupdate = $self->conf->getinfo('daily_awards.lastupdate');
	my $start = time;
	my $last = time;
	my $oneday = 60 * 60 * 24;
	my $oneweek = $oneday * 7;
	my $startofweek = $conf->main->awards->startofweek;
	my $weekcode = '%V'; #$startofweek eq 'monday' ? '%W' : '%U';
	my $dodaily = $conf->main->awards->daily;
	my $doweekly = $conf->main->awards->weekly;
	my $domonthly = $conf->main->awards->monthly;
	my $fullmonthonly = !$conf->main->awards->allow_partial_month;
	my $fullweekonly = !$conf->main->awards->allow_partial_week;
	my $fulldayonly = !$conf->main->awards->allow_partial_day;

	$self->info(sprintf("Daily 'awards' process running (Last updated: %s)", 
		$lastupdate ? scalar localtime $lastupdate : 'never'
	));

	if (!$dodaily and !$doweekly and !$domonthly) {
		$self->info("Awards are disabled. Aborting award calculations.");
		return;
	}

	# gather awards that match our gametype/modtype and are valid ...
	my $g = $db->quote($conf->main->gametype);
	my $m = $db->quote($conf->main->modtype);
	my @awards = $db->get_rows_hash("SELECT * FROM $db->{t_config_awards} WHERE enabled=1 AND (gametype=$g or gametype='' or gametype IS NULL) AND (modtype=$m or modtype='' or modtype IS NULL)");

	my ($oldest, $newest) = $db->get_row_array("SELECT MIN(statdate), MAX(statdate) FROM $db->{t_plr_data}");
	if (!$oldest and !$newest) {
		$self->info("No historical stats available. Aborting award calculations.");
		return;
	}

	$oldest = ymd2time($oldest);
	$newest = ymd2time($newest);

	my $days = [];
	if ($dodaily) {
		my $curdate = $oldest;
		while ($curdate <= $newest) {
			last if $fulldayonly and $curdate + $oneday > $newest;
			push(@$days, $curdate);
#			$self->verbose(strftime("daily: %Y-%m-%d\n", localtime($curdate)));
			$curdate += $oneday;						# go forward 1 day
		}
	}

	my $weeks = [];
	if ($doweekly) {
		# curdate will always start on the first day of the week
		my $curdate = $oldest - ($oneday * (localtime($oldest))[6]);
		$curdate += $oneday if $startofweek eq 'monday';
		while ($curdate <= $newest) {
			last if $fullweekonly and $curdate + $oneweek - $oneday > $newest;
			push(@$weeks, $curdate);
#			$self->verbose(strftime("weekly:  #$weekcode: %Y-%m-%d\n", localtime($curdate)));
			$curdate += $oneweek;						# go forward 1 week
		}
	}

	my $months = [];
	if ($domonthly) {
		# curdate will always start on the 1st day of the month (@ 2am, so DST time changes will not affect values)
		my $curdate = timelocal(0,0,2, 1,(localtime($oldest))[4,5]);	# get oldest date starting on the 1st of the month
		while ($curdate <= $newest) {
			my $onemonth = $oneday * daysinmonth($curdate);
			last if $fullmonthonly and $curdate + $onemonth - $oneday > $newest;
			push(@$months, $curdate);
#			$self->verbose(strftime("monthly: #$weekcode: %Y-%m-%d\n", localtime($curdate)));
			$curdate += $onemonth;						# go forward 1 month
		}
	}

	# loop through awards and calculate
	foreach my $a (@awards) {
		my $award = PS::Award->new($a, $self);
		if (!$award) {
			$self->warn("Award '$a->{name}' can not be processed due to errors: $@");
			next;
		} 
		$award->calc('month', $months) if $domonthly;
		$award->calc('week', $weeks) if $doweekly;
		$award->calc('day', $days) if $dodaily;
	}

	$self->conf->setinfo('daily_awards.lastupdate', time);
	$self->info("Daily process completed: 'awards' (Time elapsed: " . compacttime(time-$start,'mm:ss') . ")");
}

# updates the decay of all players
sub daily_decay {
	my $self = shift;
	my $conf = $self->conf;
	my $db = $self->{db};
	my $lastupdate = $self->conf->getinfo('daily_decay.lastupdate') || 0;
	my $start = time;
	my $decay_hours = $conf->main->decay->hours;
	my $decay_type = $conf->main->decay->type;
	my $decay_value = $conf->main->decay->value;
	my ($sth, $cmd);

	if (!$decay_type) {
		$self->info("Daily 'decay' process skipped, decay is disabled.");
		return;
	}

	$self->info(sprintf("Daily 'decay' process running (Last updated: %s)", 
		$lastupdate ? scalar localtime $lastupdate : 'never'
	));

	# get the newest date available from the database
	my ($newest) = $db->get_list("SELECT MAX(lasttime) FROM $db->{c_plr_data}");
#	my $oldest = $newest - 60*60*$decay_hours;

	$cmd = "SELECT plrid,lastdecay,skill,($newest - lastdecay) / ($decay_hours*60*60) AS length FROM $db->{t_plr} WHERE skill > " . $db->quote($self->{baseskill});
	if (!($sth = $db->query($cmd))) {
		$db->fatal("Error executing DB query:\n$cmd\n" . $db->errstr . "\n--end of error--");
	}

	$db->begin;

	my ($newskill, $value);
	while (my ($id,$lastdecay,$skill,$length) = $sth->fetchrow_array) {
		next unless $length >= 1.0;
		$newskill = $skill;
		$value = $decay_value * $length;
		if ($decay_type eq 'flat') {
			$newskill -= $value;
		} else {	# decay eq 'percent'
			$newskill -= $newskill * $value / 100;
		}
		$newskill = $self->{baseskill} if $newskill < $self->{baseskill};
#		print "id $id: len: $length, val: $value, old: $skill, new: $newskill\n";
		$db->update($db->{t_plr}, { lastdecay => $newest, skill => $newskill }, [ plrid => $id ]);
	}

	$db->commit;

	$self->conf->setinfo('daily_decay.lastupdate', time);

	$self->info("Daily process completed: 'decay' (Time elapsed: " . compacttime(time-$start,'mm:ss') . ")");
}

# daily process for updating clans. Toggles clans from being displayed based on the clan config settings.
sub daily_clans {
	my $self = shift;
	my $db = $self->{db};
	my $lastupdate = $self->conf->getinfo('daily_clans.lastupdate');
	my $start = time;
	my $last = time;
	my $types = PS::Player->get_types;
	my ($cmd, $sth, $sth2, $rules, @min, @max, $allowed, $fields);

	$self->info(sprintf("Daily 'clans' process running (Last updated: %s)", 
		$lastupdate ? scalar localtime $lastupdate : 'never'
	));

	return 0 unless $db->table_exists($db->{c_plr_data});

	# gather our min/max rules ...
	$rules = { %{$self->conf->main->ranking || {}} };
#	delete @$rules{ qw(IDX SECTION) };
	@min = ( map { s/^clan_min_//; $_ } grep { /^clan_min_/ && $rules->{$_} ne '' } keys %$rules );
	@max = ( map { s/^clan_max_//; $_ } grep { /^clan_max_/ && $rules->{$_} ne '' } keys %$rules );

	# add extra fields to our query that match values in our min/max arrays
	# if the matching type in $types is a reference then we know it's a calculated field and should 
	# be an average instead of a summary.
	my %uniq = ( 'members' => 1 );
	$fields = join(', ', map { (ref $types->{$_} ? 'avg' : 'sum') . "($_) $_" } grep { !$uniq{$_}++ } (@min,@max));

	# load a clan list including basic stats for each
	$cmd  = "SELECT c.*, count(*) members ";
	$cmd .= ", $fields " if $fields;
	$cmd .= "FROM $db->{t_clan} c, $db->{t_plr} plr, $db->{c_plr_data} data ";
	$cmd .= "WHERE (c.clanid=plr.clanid AND plr.allowrank) AND data.plrid=plr.plrid ";
	$cmd .= "GROUP BY c.clanid ";
#	print "$cmd\n";	
	if (!($sth = $db->query($cmd))) {
		$db->fatal("Error executing DB query:\n$cmd\n" . $db->errstr . "\n--end of error--");
	}

	$db->begin;
	my (@rank,@norank);
	while (my $row = $sth->fetchrow_hashref) {
		# does the clan meet all the requirements for ranking?
		$allowed = (
			((grep { ($row->{$_}||0) < $rules->{'clan_min_'.$_} } @min) == 0) 
			&& 
			((grep { ($row->{$_}||0) > $rules->{'clan_max_'.$_} } @max) == 0)
		) ? 1 : 0;
		if (!$allowed and $::DEBUG) {
			$self->info("Clan failed to rank \"$row->{clantag}\" => " . 
				join(', ', 
					map { "$_: " . $row->{$_} . " < " . $rules->{"clan_min_$_"} } grep { $row->{$_} < $rules->{"clan_min_$_"} } @min,
					map { "$_: " . $row->{$_} . " > " . $rules->{"clan_max_$_"} } grep { $row->{$_} > $rules->{"clan_max_$_"}} @max
				)
			);
		}

		# update the clan if their allowrank flag has changed
		if ($allowed != $row->{allowrank}) {
			# SQLite doesn't like it when i try to read/write to the database at the same time
			if ($db->type eq 'sqlite') {
				if ($allowed) {
					push(@rank, $row->{clanid});
				} else {
					push(@norank, $row->{clanid});
				}
			} else {
				$db->update($db->{t_clan}, { allowrank => $allowed }, [ clanid => $row->{clanid} ]);
			}
		}
	}

	# mass update clans if we didn't do it above
        $db->query("UPDATE $db->{t_plr} SET allowrank=1 WHERE plrid IN (" . join(',', @rank) . ")") if @rank;
        $db->query("UPDATE $db->{t_plr} SET allowrank=0 WHERE plrid IN (" . join(',', @norank) . ")") if @norank;
	$db->commit;

	$self->conf->setinfo('daily_clans.lastupdate', time);
	$self->info("Daily process completed: 'clans' (Time elapsed: " . compacttime(time-$start,'mm:ss') . ")");
}

# daily process for updating player activity. this should be run before daily_players.
sub daily_activity {
	my $self = shift;
	my $db = $self->{db};
	my $lastupdate = $self->conf->getinfo('daily_activity.lastupdate');
	my $start = time;
	my $last = time;
	my ($cmd, $sth);

	return 0 unless $db->table_exists($db->{c_map_data}) and $db->table_exists($db->{c_plr_data});

	$self->info(sprintf("Daily 'activity' process running (Last updated: %s)", 
		$lastupdate ? scalar localtime $lastupdate : 'never'
	));

	# the maps table is a small table and is a good target to determine the
	# most recent timestamp in the database.
	my $lasttime = $db->max($db->{c_map_data}, 'lasttime');
	my $min_act = $self->conf->main->plr_min_activity || 5;
	$min_act *= 60*60*24;
	if ($lasttime) {
		# this query is smart enough to only update the players that have new
		# activity since the last time it was calculated.
		if ($db->type eq 'mysql') {
			$cmd = "UPDATE $db->{t_plr} p, $db->{c_plr_data} d SET " . 
				"p.lastactivity = $lasttime, " . 
				"p.activity = IF($min_act > $lasttime - d.lasttime, " . 
				"LEAST(100, 100 / $min_act * ($min_act - ($lasttime - d.lasttime)) ), 0) " . 
				"WHERE p.plrid=d.plrid AND $lasttime > p.lastactivity";
		} else {
			# need to figure something out for SQLite
			die("Unable to calculate activity for DB::" . $db->type);
		}
		my $ok = $db->query($cmd);
	}
	
	$self->conf->setinfo('daily_activity.lastupdate', time);

	$self->info("Daily process completed: 'activity' (Time elapsed: " . compacttime(time-$start,'mm:ss') . ")");
}

sub _delete_stale_players {
	my $self = shift;
	my $oldest = shift || return;
	my $db = $self->{db};
	my $conf = $self->conf;
	my $sql_oldest = $db->quote($oldest);
	my @delete;

	return 0 unless $db->table_exists($db->{c_plr_data});

	$db->begin;

	# keep track of what stats are being deleted 
	$db->do("INSERT INTO plrids SELECT DISTINCT plrid FROM $db->{t_plr_data} WHERE statdate <= $sql_oldest");
	my $total = $db->count('plrids');

	# delete basic data
	$db->do("INSERT INTO deleteids SELECT dataid FROM $db->{t_plr_data} WHERE statdate <= $sql_oldest");
	$db->do("DELETE FROM $db->{t_plr_data_mod} WHERE dataid IN (SELECT id FROM deleteids)") if $db->{t_plr_data_mod};
	$db->do("DELETE FROM $db->{t_plr_data} WHERE dataid IN (SELECT id FROM deleteids)");
	$db->truncate('deleteids');

	# delete player maps
	$db->do("INSERT INTO deleteids SELECT dataid FROM $db->{t_plr_maps} WHERE statdate <= $sql_oldest");
	$db->do("DELETE FROM $db->{t_plr_maps_mod} WHERE dataid IN (SELECT id FROM deleteids)") if $db->{t_plr_maps_mod};
	$db->do("DELETE FROM $db->{t_plr_maps} WHERE dataid IN (SELECT id FROM deleteids)");
	$db->truncate('deleteids');

	# delete player roles
	if ($self->has_roles) {
		$db->do("INSERT INTO deleteids SELECT dataid FROM $db->{t_plr_roles} WHERE statdate <= $sql_oldest");
		$db->do("DELETE FROM $db->{t_plr_roles_mod} WHERE dataid IN (SELECT id FROM deleteids)") if $db->{t_plr_roles_mod};
		$db->do("DELETE FROM $db->{t_plr_roles} WHERE dataid IN (SELECT id FROM deleteids)");
		$db->truncate('deleteids');
	}
	
	# delete remaining historical stats (no 'mod' tables for these)
	$db->do("DELETE FROM $db->{t_plr_victims} WHERE statdate <= $sql_oldest");
	$db->do("DELETE FROM $db->{t_plr_weapons} WHERE statdate <= $sql_oldest");

	# sessions are stored slightly differently
	$db->do("DELETE FROM $db->{t_plr_sessions} WHERE FROM_UNIXTIME(sessionstart,'%Y-%m-%d') <= $sql_oldest");

	# only delete the compiled data if maxdays_exclusive is enabled
	if ($self->conf->main->maxdays_exclusive) {
		# Any player in deleteids hasn't played since the oldest date allowed, so get rid of them completely
		$db->do("INSERT INTO deleteids SELECT plrid FROM $db->{c_plr_data} WHERE lastdate <= $sql_oldest");
		$db->do("DELETE FROM $db->{t_plr} WHERE plrid IN (SELECT id FROM deleteids)");
		$db->do("DELETE FROM $db->{t_plr_ids_name} WHERE plrid IN (SELECT id FROM deleteids)");
		$db->do("DELETE FROM $db->{t_plr_ids_ipaddr} WHERE plrid IN (SELECT id FROM deleteids)");
		$db->do("DELETE FROM $db->{t_plr_ids_worldid} WHERE plrid IN (SELECT id FROM deleteids)");
		$db->truncate('deleteids');

		# delete the compiled data. 
		$db->do("DELETE FROM $db->{c_plr_data} WHERE lastdate <= $sql_oldest");
		$db->do("DELETE FROM $db->{c_plr_maps} WHERE lastdate <= $sql_oldest");
		$db->do("DELETE FROM $db->{c_plr_roles} WHERE lastdate <= $sql_oldest");
		$db->do("DELETE FROM $db->{c_plr_victims} WHERE lastdate <= $sql_oldest");
		$db->do("DELETE FROM $db->{c_plr_weapons} WHERE lastdate <= $sql_oldest");
	}

	$db->commit;

	return $total;
}

sub _delete_stale_maps {
	my $self = shift;
	my $oldest = shift || return;
	my $db = $self->{db};
	my $conf = $self->conf;
	my $sql_oldest = $db->quote($oldest);
	my @delete;

	return 0 unless $db->table_exists($db->{c_map_data});

	$db->begin;

	# keep track of what stats are being deleted 
	$db->do("INSERT INTO mapids SELECT DISTINCT mapid FROM $db->{t_map_data} WHERE statdate <= $sql_oldest");
	my $total = $db->count('mapids');
	
	# delete basic data
	$db->do("INSERT INTO deleteids SELECT dataid FROM $db->{t_map_data} WHERE statdate <= $sql_oldest");
	$db->do("DELETE FROM $db->{t_map_data_mod} WHERE dataid IN (SELECT id FROM deleteids)") if $db->{t_map_data_mod};
	$db->do("DELETE FROM $db->{t_map_data} WHERE dataid IN (SELECT id FROM deleteids)");
	$db->truncate('deleteids');

	# only delete the compiled data if maxdays_exclusive is enabled
	if ($self->conf->main->maxdays_exclusive) {
		$db->do("DELETE FROM $db->{c_map_data} WHERE lastdate <= $sql_oldest");
	}

	$db->commit;

	return $total;
}

sub _delete_stale_roles {
	my $self = shift;
	my $oldest = shift || return;
	my $db = $self->{db};
	my $conf = $self->conf;
	my $sql_oldest = $db->quote($oldest);
	my @delete;

	return 0 unless $db->table_exists($db->{c_role_data});

	$db->begin;

	# keep track of what stats are being deleted 
	$db->do("INSERT INTO roleids SELECT DISTINCT roleid FROM $db->{t_role_data} WHERE statdate <= $sql_oldest");
	my $total = $db->count('roleids');
	
	# delete basic data
	$db->do("INSERT INTO deleteids SELECT dataid FROM $db->{t_role_data} WHERE statdate <= $sql_oldest");
	$db->do("DELETE FROM $db->{t_role_data_mod} WHERE dataid IN (SELECT id FROM deleteids)") if $db->{t_role_data_mod};
	$db->do("DELETE FROM $db->{t_role_data} WHERE dataid IN (SELECT id FROM deleteids)");
	$db->truncate('deleteids');

	# only delete the compiled data if maxdays_exclusive is enabled
	if ($self->conf->main->maxdays_exclusive) {
		$db->do("DELETE FROM $db->{c_role_data} WHERE lastdate <= $sql_oldest");
	}

	$db->commit;

	return $total;
}

sub _delete_stale_weapons {
	my $self = shift;
	my $oldest = shift || return;
	my $db = $self->{db};
	my $conf = $self->conf;
	my $sql_oldest = $db->quote($oldest);
	my @delete;

	return 0 unless $db->table_exists($db->{c_weapon_data});

	$db->begin;

	# keep track of what stats are being deleted 
	$db->do("INSERT INTO weaponids SELECT DISTINCT weaponid FROM $db->{t_weapon_data} WHERE statdate <= $sql_oldest");
	my $total = $db->count('weaponids');

	# delete basic data
	$db->do("DELETE FROM $db->{t_weapon_data} WHERE statdate <= $sql_oldest");

	# only delete the compiled data if maxdays_exclusive is enabled
	if ($self->conf->main->maxdays_exclusive) {
		$db->do("DELETE FROM $db->{c_weapon_data} WHERE lastdate <= $sql_oldest");
	}

	$db->commit;

	return $total;
}

sub _delete_stale_hourly {
	my $self = shift;
	my $oldest = shift || return;
	my $db = $self->{db};
	my $conf = $self->conf;
	my $sql_oldest = $db->quote($oldest);
	my @delete;

	return 0 unless $db->table_exists($db->{t_map_hourly});

	$db->begin;

	# keep track of what stats are being deleted 
	my $total = $db->count($db->{t_map_hourly}, "statdate <= $sql_oldest");

	# delete basic data
	$db->do("DELETE FROM $db->{t_map_hourly} WHERE statdate <= $sql_oldest");

	$db->commit;

	return $total;
}

sub _delete_stale_spatial {
	my $self = shift;
	my $oldest = shift || return;
	my $db = $self->{db};
	my $conf = $self->conf;
	my $sql_oldest = $db->quote($oldest);
	my @delete;

	return 0 unless $db->table_exists($db->{t_map_spatial});

	$db->begin;

	# keep track of what stats are being deleted 
	my $total = $db->count($db->{t_map_spatial}, "statdate <= $sql_oldest");

	# delete basic data
	$db->do("DELETE FROM $db->{t_map_spatial} WHERE statdate <= $sql_oldest");

	$db->commit;

	return $total;
}

sub _update_player_stats {
	my $self = shift;
	my $oldest = shift || return;
	my $db = $self->{db};
	my $conf = $self->conf;
	my $sql_oldest = $db->quote($oldest);
	my $total = 0;
	my ($cmd, $fields, $o, $types, $sth);

	return 0 unless $self->conf->main->maxdays_exclusive and $db->table_exists($db->{c_plr_data});

	$o = PS::Player->new(undef, $self);
	$types = $o->get_types;
	$types->{firstdate} = '=';		# need to add these so save_stats will write them
	$types->{lastdate} = '=';
	$fields = $db->_values($types);

	$db->begin;

	$cmd  = "SELECT plrid, MIN(statdate) firstdate, MAX(statdate) lastdate, $fields FROM $db->{t_plr_data} data ";
	$cmd .=	"LEFT JOIN $db->{t_plr_data_mod} USING (dataid) " if $db->{t_plr_data_mod};
	$cmd .= "WHERE plrid IN (SELECT id FROM plrids) ";
	$cmd .= "GROUP BY plrid ";
	if (!($sth = $db->query($cmd))) {
		$db->fatal("Error executing DB query:\n$cmd\n" . $db->errstr . "\n--end of error--");
	}

	while (my $row = $sth->fetchrow_hashref) {
		$total++;
		map { !$row->{$_} ? delete $row->{$_} : 0 } keys %$row;		# remove undef/zero
		$db->delete($db->{c_plr_data}, [ 'plrid' => $row->{plrid} ]);
		$db->save_stats($db->{c_plr_data}, $row, $types);
		last if $::GRACEFUL_EXIT > 0;
	}

	$db->commit;

	# remove and update clans now that players were updated
	

	return $total;
}

sub _update_player_weapons {
	my $self = shift;
	my $oldest = shift || return;
	my $db = $self->{db};
	my $conf = $self->conf;
	my $sql_oldest = $db->quote($oldest);
	my $total = 0;
	my ($cmd, $fields, $o, $types, $sth);

	return 0 unless $self->conf->main->maxdays_exclusive and $db->table_exists($db->{c_plr_weapons});

	$o = PS::Player->new(undef, $self);
	$types = $o->get_types_weapons;
	$types->{firstdate} = '=';		# need to add these so save_stats will write them
	$types->{lastdate} = '=';
	$fields = $db->_values($types);

	$db->begin;

	$cmd  = "SELECT plrid,weaponid,MIN(statdate) firstdate, MAX(statdate) lastdate,$fields FROM $db->{t_plr_weapons} ";
	$cmd .= "WHERE plrid IN (SELECT id FROM plrids) ";
	$cmd .= "GROUP BY plrid,weaponid ";
	if (!($sth = $db->query($cmd))) {
		$db->fatal("Error executing DB query:\n$cmd\n" . $db->errstr . "\n--end of error--");
	}

	while (my $row = $sth->fetchrow_hashref) {
		$total++;
		map { !$row->{$_} ? delete $row->{$_} : 0 } keys %$row;		# remove undef/zero
		$db->delete($db->{c_plr_weapons}, [ 'plrid' => $row->{plrid}, 'weaponid' => $row->{weaponid} ]);
		$db->save_stats($db->{c_plr_weapons}, $row, $types);
		last if $::GRACEFUL_EXIT > 0;
	}

	$db->commit;

	return $total;
}

sub _update_player_roles {
	my $self = shift;
	my $oldest = shift || return;
	my $db = $self->{db};
	my $conf = $self->conf;
	my $sql_oldest = $db->quote($oldest);
	my $total = 0;
	my ($cmd, $fields, $o, $types, $sth);

	return 0 unless $self->conf->main->maxdays_exclusive and $db->table_exists($db->{c_plr_roles});

	$o = PS::Player->new(undef, $self);
	$types = $o->get_types_roles;
	$types->{firstdate} = '=';		# need to add these so save_stats will write them
	$types->{lastdate} = '=';
	$fields = $db->_values($types);

	$db->begin;

	$cmd  = "SELECT plrid,roleid,MIN(statdate) firstdate, MAX(statdate) lastdate,$fields FROM $db->{t_plr_roles} ";
	$cmd .=	"LEFT JOIN $db->{t_plr_roles_mod} USING (dataid) " if $db->{t_plr_roles_mod};
	$cmd .= "WHERE plrid IN (SELECT id FROM plrids) ";
	$cmd .= "GROUP BY plrid,roleid ";
	if (!($sth = $db->query($cmd))) {
		$db->fatal("Error executing DB query:\n$cmd\n" . $db->errstr . "\n--end of error--");
	}

	while (my $row = $sth->fetchrow_hashref) {
		$total++;
		map { !$row->{$_} ? delete $row->{$_} : 0 } keys %$row;		# remove undef/zero
		$db->delete($db->{c_plr_roles}, [ 'plrid' => $row->{plrid}, 'roleid' => $row->{roleid} ]);
		$db->save_stats($db->{c_plr_roles}, $row, $types);
		last if $::GRACEFUL_EXIT > 0;
	}

	$db->commit;

	return $total;
}

sub _update_player_victims {
	my $self = shift;
	my $oldest = shift || return;
	my $db = $self->{db};
	my $conf = $self->conf;
	my $sql_oldest = $db->quote($oldest);
	my $total = 0;
	my ($cmd, $fields, $o, $types, $sth);

	return 0 unless $self->conf->main->maxdays_exclusive and $db->table_exists($db->{c_plr_victims});

	$o = PS::Player->new(undef, $self);
	$types = $o->get_types_victims;
	$types->{firstdate} = '=';		# need to add these so save_stats will write them
	$types->{lastdate} = '=';
	$fields = $db->_values($types);

	$db->begin;

	$cmd  = "SELECT plrid,victimid,MIN(statdate) firstdate, MAX(statdate) lastdate,$fields FROM $db->{t_plr_victims} ";
	$cmd .= "WHERE plrid IN (SELECT id FROM plrids) ";
	$cmd .= "GROUP BY plrid,victimid ";
	if (!($sth = $db->query($cmd))) {
		$db->fatal("Error executing DB query:\n$cmd\n" . $db->errstr . "\n--end of error--");
	}
	while (my $row = $sth->fetchrow_hashref) {
		$total++;
		map { !$row->{$_} ? delete $row->{$_} : 0 } keys %$row;		# remove undef/zero
		$db->delete($db->{c_plr_victims}, [ 'plrid' => $row->{plrid}, 'victimid' => $row->{victimid} ]);
		$db->save_stats($db->{c_plr_victims}, $row, $types);
		last if $::GRACEFUL_EXIT > 0;
	}

	$db->commit;

	return $total;
}

sub _update_player_maps {
	my $self = shift;
	my $oldest = shift || return;
	my $db = $self->{db};
	my $conf = $self->conf;
	my $sql_oldest = $db->quote($oldest);
	my $total = 0;
	my ($cmd, $fields, $o, $types, $sth);

	return 0 unless $self->conf->main->maxdays_exclusive and $db->table_exists($db->{c_plr_maps});

	$o = PS::Player->new(undef, $self);
	$types = $o->get_types_maps;
	$types->{firstdate} = '=';		# need to add these so save_stats will write them
	$types->{lastdate} = '=';
	$fields = $db->_values($types);

	$db->begin;

	$cmd  = "SELECT plrid,mapid,MIN(statdate) firstdate, MAX(statdate) lastdate,$fields FROM $db->{t_plr_maps} ";
	$cmd .= "LEFT JOIN $db->{t_plr_maps_mod} USING (dataid) " if $db->{t_plr_maps_mod};
	$cmd .= "WHERE plrid IN (SELECT id FROM plrids) ";
	$cmd .= "GROUP BY plrid,mapid ";
	if (!($sth = $db->query($cmd))) {
		$db->fatal("Error executing DB query:\n$cmd\n" . $db->errstr . "\n--end of error--");
	}
	while (my $row = $sth->fetchrow_hashref) {
		$total++;
		map { !$row->{$_} ? delete $row->{$_} : 0 } keys %$row;		# remove undef/zero
		$db->delete($db->{c_plr_maps}, [ 'plrid' => $row->{plrid}, 'mapid' => $row->{mapid} ]);
		$db->save_stats($db->{c_plr_maps}, $row, $types);
		last if $::GRACEFUL_EXIT > 0;
	}

	$db->commit;

	return $total;
}

sub _update_map_stats {
	my $self = shift;
	my $oldest = shift || return;
	my $db = $self->{db};
	my $conf = $self->conf;
	my $sql_oldest = $db->quote($oldest);
	my $total = 0;
	my ($cmd, $fields, $o, $types, $sth);

	return 0 unless $self->conf->main->maxdays_exclusive and $db->table_exists($db->{c_map_data});

	$o = PS::Map->new(undef, $conf, $db);
	$types = $o->get_types;
	$types->{firstdate} = '=';		# need to add these so save_stats will write them
	$types->{lastdate} = '=';
	$fields = $db->_values($types);

	$db->begin;

	$cmd  = "SELECT mapid, MIN(statdate) firstdate, MAX(statdate) lastdate, $fields FROM $db->{t_map_data} data ";
	$cmd .=	"LEFT JOIN $db->{t_map_data_mod} USING (dataid) " if $db->{t_map_data_mod};
	$cmd .= "WHERE mapid IN (SELECT id FROM mapids) ";
	$cmd .= "GROUP BY mapid ";
	if (!($sth = $db->query($cmd))) {
		$db->fatal("Error executing DB query:\n$cmd\n" . $db->errstr . "\n--end of error--");
	}
	while (my $row = $sth->fetchrow_hashref) {
		$total++;
		map { !$row->{$_} ? delete $row->{$_} : 0 } keys %$row;		# remove undef/zero
		$db->delete($db->{c_map_data}, [ 'mapid' => $row->{mapid} ]);
		$db->save_stats($db->{c_map_data}, $row, $types);
		last if $::GRACEFUL_EXIT > 0;
	}

	$db->commit;

	return $total;
}

sub _update_weapon_stats {
	my $self = shift;
	my $oldest = shift || return;
	my $db = $self->{db};
	my $conf = $self->conf;
	my $sql_oldest = $db->quote($oldest);
	my $total = 0;
	my ($cmd, $fields, $o, $types, $sth);

	return 0 unless $self->conf->main->maxdays_exclusive and $db->table_exists($db->{c_weapon_data});

	$o = PS::Weapon->new(undef, $conf, $db);
	$types = $o->get_types;
	$types->{firstdate} = '=';		# need to add these so save_stats will write them
	$types->{lastdate} = '=';
	$fields = $db->_values($types);

	$db->begin;

	$cmd  = "SELECT weaponid, MIN(statdate) firstdate, MAX(statdate) lastdate, $fields FROM $db->{t_weapon_data} data ";
	$cmd .= "WHERE weaponid IN (SELECT id FROM weaponids) ";
	$cmd .= "GROUP BY weaponid ";
	if (!($sth = $db->query($cmd))) {
		$db->fatal("Error executing DB query:\n$cmd\n" . $db->errstr . "\n--end of error--");
	}
	while (my $row = $sth->fetchrow_hashref) {
		$total++;
		map { !$row->{$_} ? delete $row->{$_} : 0 } keys %$row;		# remove undef/zero
		$db->delete($db->{c_weapon_data}, [ 'weaponid' => $row->{weaponid} ]);
		$db->save_stats($db->{c_weapon_data}, $row, $types);
		last if $::GRACEFUL_EXIT > 0;
	}

	$db->commit;

	return $total;
}

sub _update_role_stats {
	my $self = shift;
	my $oldest = shift || return;
	my $db = $self->{db};
	my $conf = $self->conf;
	my $sql_oldest = $db->quote($oldest);
	my $total = 0;
	my ($cmd, $fields, $o, $types, $sth);

	return 0 unless $self->conf->main->maxdays_exclusive and $db->table_exists($db->{c_role_data});

	$o = PS::Role->new(undef, '', $conf, $db);
	$types = $o->get_types;
	$types->{firstdate} = '=';		# need to add these so save_stats will write them
	$types->{lastdate} = '=';
	$fields = $db->_values($types);

	$db->begin;

	# if exclusive update stats to remove old data
	$cmd  = "SELECT roleid, MIN(statdate) firstdate, MAX(statdate) lastdate, $fields FROM $db->{t_role_data} data ";
	$cmd .=	"LEFT JOIN $db->{t_role_data_mod} USING (dataid) " if $db->{t_role_data_mod};
	$cmd .= "WHERE roleid IN (SELECT id FROM roleids) ";
	$cmd .= "GROUP BY roleid ";
	if (!($sth = $db->query($cmd))) {
		$db->fatal("Error executing DB query:\n$cmd\n" . $db->errstr . "\n--end of error--");
	}
	while (my $row = $sth->fetchrow_hashref) {
		$total++;
		map { !$row->{$_} ? delete $row->{$_} : 0 } keys %$row;		# remove undef/zero
		$db->delete($db->{c_role_data}, [ 'roleid' => $row->{roleid} ]);
		$db->save_stats($db->{c_role_data}, $row, $types);
		last if $::GRACEFUL_EXIT > 0;
	}

	$db->commit;

	return $total;
}

# daily process for maxdays histories (this must be the longest and most complicated function in all of PS3)
sub daily_maxdays {
	my $self = shift;
	my $db = $self->{db};
	my $conf = $self->conf;
	my $lastupdate = $self->conf->getinfo('daily_maxdays.lastupdate');
	my $start = time;
	my $last = time;
	my ($cmd, $sth, $ok, $fields, @delete, @ids, $o, $types, $total, $alltotal,%t);

	$self->info(sprintf("Daily 'maxdays' process running (Last updated: %s)", 
		$lastupdate ? scalar localtime $lastupdate : 'never'
	));

	# determine the oldest date to delete
	my $oldest = strftime("%Y-%m-%d", localtime(time-60*60*24*($self->{maxdays}+1)));
	# I think it'll be better to use the newest date in the database instead
	# of the current time to determine where to trim stats. This way the
	# database won't lose stats if it stops getting new logs for a period of
	# time.
	#my ($oldest) = $db->get_list("SELECT MAX(statdate) - INTERVAL $self->{maxdays} DAY FROM $db->{t_plr_data}");
	goto MAXDAYS_DONE unless $oldest;	# will be null if there's no historical data available
	my $sql_oldest = $db->quote($oldest);

	$self->verbose("Deleting stale stats older than $oldest ...");

	# delete the temporary tables if they exist
	$db->droptable($_) for (qw( deleteids plrids mapids roleids weaponids ));

	# first create temporary tables to store ids (dont want to use potentially huge arrays in memory)
	$ok = 1;
	$ok = ($ok and $db->do("CREATE TEMPORARY TABLE deleteids (id INT UNSIGNED PRIMARY KEY)"));
	$ok = ($ok and $db->do("CREATE TEMPORARY TABLE plrids (id INT UNSIGNED PRIMARY KEY)"));
	$ok = ($ok and $db->do("CREATE TEMPORARY TABLE mapids (id INT UNSIGNED PRIMARY KEY)"));
	$ok = ($ok and $db->do("CREATE TEMPORARY TABLE roleids (id INT UNSIGNED PRIMARY KEY)"));
	$ok = ($ok and $db->do("CREATE TEMPORARY TABLE weaponids (id INT UNSIGNED PRIMARY KEY)"));
	# temporary tables could not be created
	if (!$ok) {
		$self->fatal("Error creating temporary tables for maxdays process: " . $db->errstr);
	}

	$t{plrs} = $total = $self->_delete_stale_players($oldest);
	$self->info(sprintf("%s stale players deleted!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$t{maps} = $total = $self->_delete_stale_maps($oldest);
	$self->info(sprintf("%s stale maps deleted!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$t{weapons} = $total = $self->_delete_stale_weapons($oldest);
	$self->info(sprintf("%s stale weapons deleted!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$t{roles} = $total = $self->_delete_stale_roles($oldest);
	$self->info(sprintf("%s stale roles deleted!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$t{hourly} = $total = $self->_delete_stale_hourly($oldest);
	$self->info(sprintf("%s stale hourly stats deleted!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$t{hourly} = $total = $self->_delete_stale_spatial($oldest);
	$self->info(sprintf("%s stale spatial stats deleted!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$self->verbose("Recalculating compiled stats ...");
	$self->verbose("This may take several minutes ... ");
	$total = 0;

	$total = $self->_update_map_stats($oldest) if $t{maps};
	$self->info(sprintf("%s maps updated!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$total = $self->_update_role_stats($oldest) if $t{roles};
	$self->info(sprintf("%s roles updated!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$total = $self->_update_weapon_stats($oldest) if $t{weapons};
	$self->info(sprintf("%s weapons updated!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$total = $self->_update_player_stats($oldest) if $t{plrs};
	$self->info(sprintf("%s players updated!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$total = 0;
	$total = $self->_update_player_maps($oldest) if $t{plrs};
	$self->info(sprintf("%s player maps updated!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$total = $self->_update_player_roles($oldest) if $t{plrs};
	$self->info(sprintf("%s player roles updated!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$total = $self->_update_player_victims($oldest) if $t{plrs};
	$self->info(sprintf("%s player victims updated!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$total = $self->_update_player_weapons($oldest) if $t{plrs};
	$self->info(sprintf("%s player weapons updated!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	# if NOTHING was updated then dont bother optimizing tables
	if ($alltotal) {
		$self->info("Optimizing database tables ...");
		# optimize all the tables, since we probably just deleted a lot of data

		$db->optimize(map { $db->{$_} } grep { /^[ct]\_/ } keys %$db);	# do them ALL! muahahahah
	}

MAXDAYS_DONE:
	$db->droptable($_) for (qw( deleteids plrids mapids roleids weaponids ));

	$self->conf->setinfo('daily_maxdays.lastupdate', time);
	$self->info("Daily process completed: 'maxdays' (Time elapsed: " . compacttime(time-$start,'mm:ss') . ")");
}

# rescans for player -> clan relationships and rebuilds the clan database
sub rescan_clans {
	my $self = shift;
	my $db = $self->{db};
	my $total = $db->count($db->{t_plr}, [ allowrank => 1, clanid => 0 ]);
	$self->info("$total ranked players will be scanned.");

	my $clanid;
	my $cur = 0;
	my $clans = {};
	my $members = 0;
	my $time = time - 1;
	my $sth = $db->query(
		"SELECT p.plrid,pp.uniqueid,pp.name " .
		"FROM $db->{t_plr} p, $db->{t_plr_profile} pp " .
		"WHERE p.uniqueid=pp.uniqueid and p.allowrank=1 and p.clanid=0"
	);
	while (my ($plrid,$uniqueid,$name) = $sth->fetchrow_array) {
		local $| = 1;	# do not buffer STDOUT
		$cur++;
		if ($time != time or $cur == $total) { # only update every second
			$time = time;
			$self->verbose(sprintf("Scanning player %d / %d [%6.2f%%]\r", $cur, $total, $cur / $total * 100), 1);
		}
		$clanid = $self->scan_for_clantag($name) || next;
		$clans->{$clanid}++;
		$members++;
		$db->update($db->{t_plr}, { clanid => $clanid }, [ plrid => $plrid ]);
	}
	$self->verbose("");
	$self->info(sprintf("%d clans with %d members found.", scalar keys %$clans, $members));

	return ($clans, $members);
}

# delete's all clans and removes player relationships to them.
sub delete_clans {
	my $self = shift;
	my $profile_too = shift;
	my $db = $self->{db};
	$db->query("UPDATE $db->{t_plr} SET clanid=0 WHERE clanid <> 0");
	$db->truncate($db->{t_clan});
	$db->truncate($db->{t_clan_profile}) if $profile_too;
}

sub reset_all {
	my $self = shift;
	my $db = $self->{db};
	my $del = @_ == 1 ? { map { $_  => $_[0] } qw(players clans weapons heatmaps) } : { @_ };
	
}

# Resets the current "gametype_modtype" in the database. Does not remove shared
# information like player records from another game.
sub reset_game {
	my $self = shift;
	my $del = (@_ == 1)
		? { map { $_  => $_[0] } qw(players clans maps roles weapons heatmaps) }
		: { @_ };
	my $db = $self->{db};
	my $gametype = $self->{gametype};
	my $modtype  = $self->{modtype} || undef;
	my $type = $gametype . ($modtype ? '_' . $modtype : '');
	my $errors = 0;
	my $where = "gametype=? AND modtype" . ($modtype ? '=?' : ' IS NULL');
	my @bind = $modtype ? ($gametype, $modtype) : ($gametype);
	my ($cmd,$st);

	# Delete overall map, role and weapon stats
	for my $t (qw(map role weapon)) {
		my $t1 = $db->{'t_' . $t};
		my $t2 = $db->{'t_' . $t . '_data'};
		my $t3 = $t2 . '_' . $type;
		my $st = $db->prepare(
			"DELETE QUICK t2 FROM $t1 t, $t2 t2 " .
			"WHERE $where AND t2.${t}id=t.${t}id "
		);
		$self->debug1("Deleting ${t}s ...",0);
		if (!$st->execute(@bind)) {
			$self->warn("Reset error on $t2: " . $db->errstr);
			$errors++;
		} else {
			if ($del->{$t . 's'}) {
				$db->do("DELETE FROM t_$t WHERE $where",0);
			}
			$db->truncate($t3);
			$db->optimize($t1, $t2);
		}
		$st->finish;
	}
	
	# Delete player specific stats 
	for my $t (qw(data map role session victim weapon)) {
		my $t2 = $db->{'t_plr_' . $t . ($t ne 'data' ? 's' : '')};
		my $t3 = $t2 . '_' . $type;
		my $st = $db->prepare(
			"DELETE QUICK t2 FROM t_plr p, $t2 t2 " .
			"WHERE $where AND t2.plrid=p.plrid "
		);
		$self->debug1("Deleting player $t" . ($t ne 'data' ? 's' : '') . " ...",0);
		if (!$st->execute(@bind)) {
			$self->warn("Reset error on $t2: " . $db->errstr);
			$errors++;
		} else {
			$db->truncate($t3);
			$db->optimize($t2);
		}
		$st->finish;
	}

	# Delete player ids
	for my $t (qw(guid ipaddr name)) {
		my $st = $db->prepare(
			"DELETE QUICK t2 FROM t_plr p, t_plr_ids_$t t2 " .
			"WHERE $where AND t2.plrid=p.plrid "
		);
		$self->debug1("Deleting player ${t}s ...",0);
		if (!$st->execute(@bind)) {
			$self->warn("Reset error on " . $db->{'t_plr_ids_' . $t} . ": " . $db->errstr);
			$errors++;
		} else {
			$db->optimize($db->{'t_plr_ids_' . $t});
		}
	}

	# Delete player chat
	$self->debug1("Deleting player chat ...",0);
	if (!$db->do("DELETE QUICK c FROM t_plr p, t_plr_chat c " .
		"WHERE $where AND c.plrid=p.plrid ", @bind)) {
		$self->warn("Reset error on " . $db->{t_plr_chat} . ": " . $db->errstr);
		$errors++;
	} else {
		$db->optimize($db->{t_plr_chat});
	}

	# Delete players and optionally their profiles
	if ($del->{players}) {
		$cmd = "DELETE pp, p FROM t_plr p, t_plr_profile pp " .
		       "WHERE p.uniqueid=pp.uniqueid AND $where";
	} else {
		$cmd = "DELETE FROM t_plr WHERE $where";
	}
	$st = $db->prepare($cmd);
	$self->debug1("Deleting players " . ($del->{players} ? 'and profiles' : '') . " ...",0);
	if (!$st->execute(@bind)) {
		$self->warn("Reset error on $db->{t_plr}: " . $db->errstr);
		$errors++;
	} else {
		$db->optimize($db->{t_plr}, $db->{t_plr_profile});
	}

	#$self->debug1("Unranking all clans ...", 0);
	#$db->do("UPDATE t_clan SET rank=NULL");
	$self->debug1("Deleting clans ...",0);
	$db->truncate($db->{t_clan});
	if ($del->{clans}) {
		$db->truncate($db->{t_clan_profile});
	}

	# Delete all game specific compiled tables
	foreach my $t (@{$db->{compiled_tables}}) {
		my $tbl = $db->{'c_' . $t} . '_' . $type || next;
		next unless $db->table_exists($tbl);
		$self->debug1("Deleting compiled table $t ...",0);
		if (!$db->truncate($tbl)) {
			$self->warn("Reset error on $tbl: " . $db->errstr);
			$errors++;
		}
	}
	
	# Delete current state
	$st = $db->prepare("DELETE s FROM t_state s, t_config_logsources l " .
			   "WHERE l.gametype=? AND l.modtype" . ($modtype ? '=?' : ' IS NULL')
	);
	$self->debug1("Deleting game state ...",0);
	if (!$st->execute(@bind)) {
		$self->warn("Reset error on $db->{t_state}: " . $db->errstr);
		$errors++;
	} else {
		$db->optimize($db->{t_state});
	}

	return ($errors == 0);
}

# save all in-memory stats to the database for this game. Does not remove
# objects from memory. This is used when you want to save stats for players w/o
# having to wait for a player to disconnect, or a map to change, etc...
sub save {
	my ($self, $end) = @_;
	my ($id, $tag, $clan, $o);
	
	$self->{last_saved} = time;
	
	# SAVE PLAYERS
	foreach $o ($self->get_plrcache) {
		$o->save($self);
	}

	# SAVE MAPS
	while (($id,$o) = each %{$self->{maps}}) {
		$o->save;
	}

	# SAVE ROLES
	while (($id,$o) = each %{$self->{roles}}) {
		$o->save;
	}

	# SAVE WEAPONS
	while (($id,$o) = each %{$self->{weapons}}) {
		$o->save;
	}

	if ($end) {
		%{$self->{maps}} = ();
		%{$self->{roles}} = ();
		%{$self->{weapons}} = ();
	}
}

# returns true if the total number of players connected is >= the configured
# 'minconnected' option (the return value is actually total connected).
# otherwise 0 is returned.
sub minconnected { 
	my ($self) = @_;
	return 1 if $self->conf->main->minconnected == 0;
	my $list = $self->get_online_plrs;
	return @$list >= $self->conf->main->minconnected ? scalar @$list : 0;
}

# takes an array of log filenames and returns a sorted result
sub logsort { sort @{$_[1]} }
# return -1,0,1 (<=>), depending on outcome of comparison of 2 log files
sub logcompare { $_[1] cmp $_[2] }

sub conf () { $CONF }
sub opt () { $OPT }

# setup global helper objects (package method)
# PS::Game::configure( ... )
sub configure {
	my %args = @_;
	foreach my $k (keys %args) {
		no strict 'refs';
		my $var = __PACKAGE__ . '::' . $k;
		$$var = $args{$k};
	}
}

1;

