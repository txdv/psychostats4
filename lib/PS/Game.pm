# Factory class for all games
# A $conf and $db object must always be passed to new. If no gametype is found a fatal error is given.
# All game types should inherit this parent class for functionality.
package PS::Game;

use strict;
use warnings;
use base qw( PS::Debug );
use util qw( :date :time :numbers :net );
use PS::Config;			# for loadfile() function
use PS::Player;			# daily_clans(), daily_maxdays()
use PS::Weapon;			# daily_maxdays()
use PS::Map;			# daily_maxdays()
use PS::Award;			# daily_awards()
use Data::Dumper;
use File::Spec::Functions;
use POSIX qw(floor strftime mktime);
use Time::Local;
use Safe;

our $VERSION = '1.00';

our @DAILY = qw( all maxdays decay players clans ranks awards );

sub new {
	my $proto = shift;
	my $conf = shift;
	my $db = shift;
	my $class = ref($proto) || $proto;

	my $gametype = $conf->get_main('gametype');
	my $modtype  = $conf->get_main('modtype');

	$::ERR->fatal("No 'gametype' configured.") unless $gametype;
	$class .= "::$gametype";
	$class .= "::$modtype" if $modtype;

	# add our subclass into the frey ...
	eval "require $class";
	if ($@) {
		die("\n-----\nCompilation errors with Game class '$class':\n$@\n-----\n");
	}

	return $class->new($conf, $db);
}

sub _init {
	my $self = shift;
	my $db = $self->{db};
	my $conf = $self->{conf};

#	$::ERR->debug("Game->_init");

	$self->{evconf} = {};
	$self->{evorder} = [];
	$self->{evregex} = sub {};
#	$self->load_events(*DATA);

	$self->{_plraliases} = {};		# stores a cache of player aliases fetched from the database
	$self->{_plraliases_age} = time();

	$self->{banned}{worldid} = {};
	$self->{banned}{ipaddr} = {};
	$self->{banned}{name} = {};
	$self->{banned_age} = time;

	# this will be made configurable at some point (didn't want to put this in the DB yet) ...
	$self->{calcskill_kill} = 'calcskill_kill_default';

	# get() the option ONCE from the config instead of calling it over and over in the event code
	$self->{clantag_detection} = $conf->get_main('clantag_detection');
	$self->{report_unknown} = $conf->get_main('errlog.report_unknown');
	$self->{ignore_bots_conn} = $conf->get_main('ignore_bots_conn');
	$self->{ignore_bots} = $conf->get_main('ignore_bots');
	$self->{baseskill} = $conf->get_main('baseskill');
	$self->{uniqueid} = $conf->get_main('uniqueid');
	$self->{maxdays} = $conf->get_main('maxdays');
#	$self->{charset} = $conf->get_main('charset');
	$self->{usercmds} = $conf->get_main('usercmds');
	$self->{skillcalc} = $conf->get_main('skillcalc');
	$self->{plr_save_on} = $conf->get_main('plr_save_on');
	$self->{minconnected} = $conf->get_main('minconnected');
	$self->{maxdays_exclusive} = $conf->get_main('maxdays_exclusive');

	# initialize player bonuses (bonuses are not part of the normal config and must be loaded separately)
	my $g = $db->quote($conf->get_main('gametype'));
	my $m = $db->quote($conf->get_main('modtype'));
	my @list = $db->get_rows_hash("SELECT * FROM $db->{t_config_plrbonuses} WHERE (gametype=$g or gametype='') AND (modtype=$m or modtype='')");
	$self->{bonuses} = {};
	foreach my $b (@list) {
		$self->{bonuses}{ $b->{event} } = $b;
	}

	# initialize the adjustment levels for the ELO calculations
	$self->{_adj_onlinetime} = [];
	$self->{_adj} = [];
	foreach my $key (sort grep { /^kill_onlinetime_\d+$/ } keys %{$self->{skillcalc}}) {
		my $num = ($key =~ /(\d+)$/)[0];
		my $adjkey = "kill_adj_$num";
		next unless exists $self->{skillcalc}{$adjkey};			# only allow matching adjustments
		push(@{$self->{_adj}}, $self->{skillcalc}{$adjkey});
		push(@{$self->{_adj_onlinetime}}, $self->{skillcalc}{$key});
	}

	# initializes the clantags from the config.
	if ($self->{clantag_detection}) {
		$self->{clantags}{str} = $db->get_rows_hash("SELECT * FROM $db->{t_config_clantags} WHERE type='plain' ORDER BY idx");
		$self->{clantags}{regex} = $db->get_rows_hash("SELECT * FROM $db->{t_config_clantags} WHERE type='regex' ORDER BY idx");

		# build a sub-routine to scan the player for matching clantag regex's
		if (@{$self->{clantags}{regex}}) {
			my $code = '';
			my $idx = 0;
			my $env = new Safe;
			foreach my $ct (@{$self->{clantags}{regex}}) {
				my $regex = $ct->{clantag};

				# perform sanity check on user configured regex
				$env->reval("/$regex/");
				if ($@) {
					$::ERR->warn("Error in clantag definition #$ct->{id} (/$regex/): $@");
					next;
				}

				$code .= "  return [ $idx, \\\@m ] if \@m = (\$_[0] =~ /$regex/);\n";
				$idx++;
			}
#			print "sub {\n  my \@m = ();\n$code  return undef;\n}\n";
			$self->{clantags_regex_func} = eval "sub { my \@m = (); $code return undef }";
			if ($@) {
				$::ERR->fatal("Error in clantag regex function: $@");
			}
		} else {
			$self->{clantags_regex_func} = sub { undef };
		}
	}
	return $self;
}

sub minconnected { return ( ($_[0]->{minconnected} == 0) or (scalar keys %{$_[0]->{plrs}} > $_[0]->{minconnected}) ) }

# Add's a player BAN to the database. 
# Does nothing If the ban already exists unless $overwrite is true
# ->addban('matchtype', 'str', {extra})
sub addban {
	my $self = shift;
	my $matchtype = shift;
	my $matchstr = shift;
	my $opts = ref $_[0] ? shift : { @_ };
	my $overwrite = 0;
	if (exists $opts->{overwrite}) {
		$overwrite = $opts->{overwrite};
		delete $opts->{overwrite};
	}

	$matchtype = 'worldid' unless $matchtype =~ /^(worldid|ipaddr|name)$/;

	my $db = $self->{db};
	my $str = $db->quote($matchstr);
	my ($exists,$enabled) = $db->get_row_array("SELECT id,enabled FROM $db->{t_config_plrbans} WHERE matchtype='$matchtype' AND matchstr=$str LIMIT 1");

	if (!$exists) {
		$db->insert($db->{t_config_plrbans}, {
			'id'		=> $db->next_id($db->{t_config_plrbans}), 
			'matchtype'	=> $matchtype,
			'matchstr'	=> $matchstr,
			'enabled'	=> defined $opts->{enabled} ? $opts->{enabled} : 1,
			'bandate'	=> $opts->{bandate} || time,
			'reason'	=> $opts->{reason} || '',
		});
	} elsif (defined $opts->{enabled} and $opts->{enabled} ne $enabled) {
		$db->update($db->{t_config_plrbans}, { enabled => $opts->{enabled} }, [ id => $exists ]);
	}
}

# returns true if any of the criteria given matches an enabled BAN record
# ->isbanned(worldid => '', ipaddr => '', name => '')
sub isbanned {
	my $self = shift;
	my $m = ref $_[0] eq 'HASH' ? shift : ref $_[0] ? $_[0]->{plrids} : { @_ };
	my $banned = 0;
#	either pass a hash of values, or a PS::Player record, or key => value pairs

	# clear the banned cache every 60 seconds (real-time)
	if (time - $self->{banned_age} > 60) {
		$::ERR->debug("CLEARING BANNED CACHE");
		$self->{banned}{worldid} = {};
		$self->{banned}{ipaddr} = {};
		$self->{banned}{name} = {};
		$self->{banned_age} = time;
	}


	foreach my $match (grep { $_ eq 'worldid' || $_ eq 'ipaddr' || $_ eq 'name' } keys %$m) {
		return $self->{banned}{$match}{ $m->{$match} } if exists $self->{banned}{$match}{ $m->{$match} };

		my $matchstr = int2ip($m->{$match}) if $match eq 'ipaddr';
		my ($banned) = $self->{db}->get_row_array("SELECT id FROM $self->{db}{t_config_plrbans} WHERE enabled=1 AND matchtype='$match' AND " . $self->{db}->quote($matchstr) . " LIKE matchstr");

		$self->{banned}{$match}{ $m->{$match} } = $banned;
	}

	return 0;
}

# scans the given player name for a matching clantag from the database.
# creates a new clan+profile if it's a new tag.
# $p is either a PS::Player object, or a plain scalar string to match.
# if a PS::Player object is given it's updated directly.
# the clanid is always returned if a match is found.
sub scan_for_clantag {
	my ($self, $p) = @_;
	my ($ct, $tag, $id);
	my $name = ref $p ? $p->name : $p;

	# scan STRING clantags first (since they're faster and more specific)
	my $m = $self->clantags_str_func($name);
	if ($m) {
		$ct = $self->{clantags}{str}->[ $m->[0] ];
		$tag = ($ct->{overridetag} ne '') ? $ct->{overridetag} : $m->[1];
		$id = $self->get_clanid($tag);
		if ($id) {	# technically this should never be 0
			if (!$self->{db}->select($self->{db}->{t_clan}, 'locked', [ clanid => $id ])) {
				$p->clanid($id) if ref $p;
				return $id;
			}
		}
	}

	# scan REGEX clantags if we didn't find a match above ...
	$m = &{$self->{clantags_regex_func}}($name);
#	print Dumper($name, $m) if defined $m;
	if ($m) {
		$ct = $self->{clantags}{regex}->[ $m->[0] ];
		$tag = ($ct->{overridetag} ne '') ? $ct->{overridetag} : join('', @{$m->[1]});
		$id = $self->get_clanid($tag);
		if ($id) {	# technically this should never be 0
			if (!$self->{db}->select($self->{db}->{t_clan}, 'locked', [ clanid => $id ])) {
#				print Dumper($m);
				$p->clanid($id) if ref $p;
				return $id;
			}
		}
	}
	return undef;
}

# returns the clanid based on the clantag given. If no clan exists it is created.
sub get_clanid {
	my ($self, $tag) = @_;
	my $id = $self->{db}->select($self->{db}->{t_clan}, 'clanid', [ clantag => $tag ]);

	# create the clan if it didn't exist; Must default the clan to not be allowed to rank
#	$self->{db}->begin;
	if (!$id) {
		$id = $self->{db}->next_id($self->{db}->{t_clan}, 'clanid');
		$self->{db}->insert($self->{db}->{t_clan}, { clanid => $id, clantag => $tag, allowrank => 0 });
	}

	# create the clan profile if it didn't exist
	if (!$self->{db}->select($self->{db}->{t_clan_profile}, 'clantag', [ clantag => $tag ])) {
		$self->{db}->insert($self->{db}->{t_clan_profile}, { clantag => $tag, logo => '' });
	}
#	$self->{db}->commit;


	return $id;
}

sub clantags_str_func {
	my ($self, $name) = @_;
	my $idx = -1;
	my $tag = undef;
	foreach my $ct (@{$self->{clantags}{str}}) {
		$idx++;
		if ($ct->{pos} eq 'left') {
			if (index($name, $ct->{clantag}) == 0) {
				$tag = $ct->{clantag};
				last;
			}
		} else {	# right
			# reverse the name and clantag so that index() can accurately determine if the tag ONLY
			# starts at the END of the name. rindex could otherwise potentially find matching tags in the
			# middle of the player name instead. which is not what we want here.
			my $revtag  = reverse scalar $ct->{clantag};
			my $revname = reverse scalar $name;
			if (index($revname, $revtag) == 0) {
				$tag = $ct->{clantag};
				last;
			}
#			if (substr($name, -length($ct->{clantag})) eq $ct->{clantag}) {
#				$tag = $ct->{clantag};
#				last;
#			}
		}
	}
	return undef unless $tag;
	return wantarray ? ( $idx, $tag ) : [ $idx, $tag ];
}

# prepares the EVENT patterns
sub init_events {
	my $self = shift;

	# sort all event patterns in the order they were loaded
	$self->{evorder} = [ sort {$self->{evconf}{$a}{IDX} <=> $self->{evconf}{$b}{IDX}} keys %{$self->{evconf}} ];
	$self->{evregex} = $self->_build_regex_func;

	# transform the 'options' string into a hash for all events
	foreach my $ev (keys %{$self->{evconf}}) {
		my $str = $self->{evconf}{$ev}{options} || '';
		$self->{evconf}{$ev}{options} = { map { /([^=]+)=([^,\s]+)/ ? ($1 => $2) : ($_ => 1) } split(/\s*[, ]\s*/,$str) };
	}
}

sub _build_regex_func {
	my $self = shift;
	my $code = '';
	my $env = new Safe;

#	$code .= "  study \$_[0];\n";			# not sure if I want this yet... Haven't done any benchmarks yet

	foreach my $re (@{$self->{evorder}}) {
		my $regex = $self->{evconf}{$re}{regex};
		unless ($regex) {
			$self->warn("Ignoring event '$re' (No regex defined)");
			next;
		}
		unless ($regex =~ m|^/.+/$|) {		# make sure it begins and ends with a /
			$self->warn("Ingoring event '$re' (Invalid terminators)");
			next;
		}
		$env->reval($regex);
		if ($@) {
			$self->warn("Invalid regex for event '$re' regex $regex:\n$@");
			next;
		}
		$code .= "  return ('$re',\\\@parms) if \@parms = (\$_[0] =~ $regex);\n";
	}
	if ($self->{conf}->get_opt('dumpevents')) {
		print "sub {\n  my \@parms = ();\n$code  return (undef,undef);\n}\n";
		main::exit();
	}
	return eval "sub { my \@parms = (); $code return (undef,undef) }";
}

# Takes a Feeder object and processes all events from it.
sub process_feed {
	my $self = shift;
	my $feeder = shift;
	my $total = 0;

	$self->init_events;

	# loop through all events that the feeder has in it's queue
	my $lastsrc = '';
	while (defined(my $ev = $feeder->next_event)) {
		my ($src, $event, $line) = @$ev;
		if ($src ne $lastsrc) {
			$::ERR->verbose("Processing $src (" . $feeder->lines_per_second . " lines ps / " . $feeder->bytes_per_second(1) . ")");
			$lastsrc = $src;
			$self->new_source($src);
			$total++;
		}

		$self->event($src, $event, $line);
	}
	return $total;
}

# abstact event method. All sub classes must override.
sub event { $_[0]->fatal($_[0]->{class} . " has no 'event' method implemented. HALTING.") }

# Called everytime the log source changes
sub new_source { }

# Loads the event config for the current game
# may be called several times to overload previous values
# $fh is a filehandle that has already been opened (generally *DATA from the calling module)
sub load_events {
	my $self = shift;
	my $fh = shift;
	$self->{evconf} = PS::Config->loadfile( filename => $fh, oldconf => $self->{evconf}, noarrays => 1 );
}

# returns the 'alias' for the uniqueid given.
# If no alias exists the same uniqueid given is returned.
# caches results to speed things up.
sub get_plr_alias {
	my ($self, $uniqueid) = @_;
	my $alias;
	if (time - $self->{_plraliases_age} > 60*15) {		# clear the aliases cache after 15 mins (real-time)
		$self->{_plraliases} = {};
		$self->{_plraliases_age} = time;
	}
	if (exists $self->{_plraliases}{$uniqueid}) {
		$alias = $self->{_plraliases}{$uniqueid};
	} else {
		$alias = $self->{db}->select($self->{db}->{t_plr_aliases}, 'alias', [ uniqueid => $uniqueid ]);
		$self->{_plraliases}{$uniqueid} = $alias;
	}
	return (defined $alias and $alias ne '') ? $alias : $uniqueid;
}

# returns all player references on a certain team that are not dead.
# if $all is true then dead players are included.
sub get_team {
	my ($self, $team, $all) = @_;
	my (@list, @ids);
	@ids = grep { $self->{plrs}{$_}->{team} eq $team } keys %{$self->{plrs}};
	@ids = grep { !$self->{plrs}{$_}->{isdead} } @ids unless $all;
	@list = map { $self->{plrs}{$_} } @ids;
	return wantarray ? @list : \@list;
}

# returns a list of all connected players. 
sub get_plr_list { wantarray ? %{$_[0]->{plrs}} : $_[0]->{plrs} }

# The feeder object will call this method after it has loaded its state information.
# ->restore_state($state)
sub restore_state { }

# resets the isdead status of all players
sub reset_isdead {
	my ($self, $isdead) = @_;
	map { $self->{plrs}{$_}->isDead($isdead || 0) } keys %{$self->{plrs}};
}

sub calcskill_kill_default {
	my ($self,$k,$v,$w) = @_;

	my $kskill = $k->skill || $self->{baseskill};
	my $vskill = $v->skill || $self->{baseskill};

#	if ($k->plrid eq '98' or $v->plrid eq '98') {
#		print "1)" . $k->name . "(" . $kskill . ") killed " . $v->name . "(" . $vskill . ")\n";
#	}

	my $diff = $kskill - $vskill;					# difference in skill
	my $prob = 1 / ( 1 + 10 ** ($diff / $self->{baseskill}) );	# find probability of kill
	my $kadj = $self->{_adj}->[-1] || 100;
	my $vadj = $self->{_adj}->[-1] || 100;
	my $kmins = int $k->totaltime / 60;
	my $vmins = int $v->totaltime / 60;
	my $idx = 0;
	foreach my $level (@{$self->{_adj_onlinetime}}) {
		if ($kmins >= $level) {
			$kadj = $self->{_adj}->[$idx];
#			print "level: " . ("\t" x $idx+1) . "$level\n";
			last;
		}
		$idx++;
	}
	$idx = 0;
	foreach my $level (@{$self->{_adj_onlinetime}}) {
		if ($vmins >= $level) {
			$vadj = $self->{_adj}->[$idx];
#			print "level: " . ("\t" x $idx+1) . "$level\n";
			last;
		}
		$idx++;
	}
	
	my $kbonus = $kadj * $prob;
	my $vbonus = $vadj * $prob;

	my $weight = $w->weight;
	if (defined $weight and $weight != 0.0 and $weight != 1.0) {
		$kbonus *= $weight;
		$vbonus *= $weight;
	}

#	print "Bonus: $kbonus / $vbonus (prob: $prob)\n" if ($k->plrid eq '98' or $v->plrid eq '98');

	$kskill += $kbonus;
	$vskill -= $vbonus;

	$k->skill($kskill);
	$v->skill($vskill);

#	if ($k->plrid eq '98' or $v->plrid eq '98') {
#		print "2) " . $k->name . "(" . $k->skill . ") killed " . $v->name . "(" . $v->skill . ")\n\n";
#	}

	return (
		$kskill,						# killers new skill value
		$vskill,						# victims ...
		$kbonus,						# total bonus points given to killer
		$vbonus							# ... victim
	);
}

use constant 'PI' => 3.1415926;
use constant 'T'  => 1;
our $KMAX = 100;
our $KCNT = 0;
our $MEAN = 0;
our $VARIANCE = 0;
our $NORMVAR = 0;
# http://www.gamasutra.com/features/20000209/kreimeier_pfv.htm
# Does not work yet... Still trying to figure out the calculations
sub calcskill_kill_new {
	my ($self,$k,$v,$w) = @_;
	my ($vskill, $kskill, $delta, $expectancy, $change, $kbonus, $vbonus, $result);
	my $T = 1;
	my $MAX_GAIN = 25;
	my ($kprob, $vprob);

	$kskill = $k->skill;
	$vskill = $v->skill;

	# determine current meadian and variance of skill value of players in memory
#	if ($KCNT == 0) {
#		my $sum = 0;
#		my $tot = scalar keys %{$self->{plrs}};
#		$sum += $self->{plrs}{$_}->skill for keys %{$self->{plrs}};
#		$MEAN = (1/$tot) * $sum;						# mean value of skill
#		$VARIANCE = 1 / ($tot-1) * (($MEAN)^2);
#		$NORMVAR = 1 / sqrt(2*PI * $VARIANCE);					# normalize
#		$kprob = $NORMVAR * exp(($kskill - $MEAN)^2 / (2*$VARIANCE) );		# probibility of killer killing victim
##		$vprob = $NORMVAR * exp(($vskill - $MEAN)^2 / (2*$VARIANCE) );		# probibility of victim killing killer
#		printf "KS: %0.2f, VK: %0.2f, MEAN: %0.7f, VAR: %0.7f, NVAR: %0.7f, kprob: %0.7f (%d plrs)\n", 
#			$kskill, $vskill, $MEAN, $VARIANCE, $NORMVAR, $kprob, $tot;
#		$KCNT = $KMAX;
#	} else {
#		--$KCNT;
#	}

	$result = 1;
	$delta = $vskill - $kskill;
	$expectancy = 1.0 / (1.0 + exp((-$delta) / T));			# Fermi function
	$kbonus = $vbonus = $change = $MAX_GAIN * ($result-$expectancy);

	print "kk: $kskill, vk: $vskill, delta: $delta, exp: $expectancy, change: $change\n"; # if $k->plrid eq 193 or $v->plrid eq 193;

	$kskill += $change;
	$vskill -= $change;

	$k->skill($kskill);
	$v->skill($vskill);

	return (
		$kskill,						# killers new skill value
		$vskill,						# victims ...
		$kbonus,						# total bonus points given to killer
		$vbonus							# ... victim
	);
}

# update population variance for current player IQs (PQ)
#sub bootstrap {
#	my $self = shift;
#	my $sum = 0;
#	my $tot = scalar keys %{$self->{plrs}};
#	$sum += $self->{plrs}{$_}->skill for keys %{$self->{plrs}};
#	$MEAN = (1/$tot) * $sum;						# mean value of skill
#	$VARIANCE = 1 / ($tot-1) * (($MEAN)^2);
#	$NORMVAR = 1 / sqrt(2*PI * $VARIANCE);					# normalize
#	$kprob = $NORMVAR * exp(($kskill - $MEAN)^2 / (2*$VARIANCE) );		# probibility of killer killing victim
##	$vprob = $NORMVAR * exp(($vskill - $MEAN)^2 / (2*$VARIANCE) );		# probibility of victim killing killer
#	printf "KS: %0.2f, VK: %0.2f, MEAN: %0.7f, VAR: %0.7f, NVAR: %0.7f, kprob: %0.7f (%d plrs)\n", 
#		$kskill, $vskill, $MEAN, $VARIANCE, $NORMVAR, $kprob, $tot;
#}

# assign bonus points to players
# ->plrbonus('trigger', 'enactor type', $PLR/LIST, ... )
sub plrbonus {
	my $self = shift;
	my $trigger = shift;
	return unless exists $self->{bonuses}{$trigger};		# bonus trigger doesn't exist

#	print "plrbonus: $trigger:\n";
	while (@_) {
		my $type = shift;
		my $entity = shift || next;
		my $val = $self->{bonuses}{$trigger}{$type};
		my $list = (ref $entity eq 'ARRAY') ? $entity : [ $entity ];
#		print "plrbonus: $type\n";

		# assign bonus to players in our list
		foreach my $p (@$list) {
			next unless defined $p;
			$p->{basic}{totalbonus} += $val;
			$p->{skill} += $val;
#			printf("\t%-32s received %3d points for %s ($type)\n", $p->name, $val, $trigger);
		}
	}
}

# daily process for awards
sub daily_awards {
	my $self = shift;
	my $db = $self->{db};
	my $conf = $self->{conf};
	my $lastupdate = $self->{conf}->getinfo('daily_awards.lastupdate');
	my $start = time;
	my $last = time;
	my $oneday = 60 * 60 * 24;
	my $oneweek = $oneday * 7;
	my $startofweek = $conf->get_main('awards.startofweek');
	my $weekcode = '%V'; #$startofweek eq 'monday' ? '%W' : '%U';
	my $doweekly = $conf->get_main('awards.weekly');
	my $domonthly = $conf->get_main('awards.monthly');
	my $dodaily = 0;
	my $fullmonthonly = !$conf->get_main('awards.allow_partial_month');
	my $fullweekonly = !$conf->get_main('awards.allow_partial_week');

	$::ERR->info(sprintf("Daily 'awards' process running (Last updated: %s)", 
		$lastupdate ? scalar localtime $lastupdate : 'never'
	));

	if (!$doweekly and !$domonthly) {
		$::ERR->info("Weekly and Monthly awards are disabled. Aborting award calculations.");
		return;
	}

	# gather awards that match our gametype/modtype and are valid ...
	my $g = $db->quote($conf->get_main('gametype'));
	my $m = $db->quote($conf->get_main('modtype'));
	my @awards = $db->get_rows_hash("SELECT * FROM $db->{t_config_awards} WHERE enabled AND (gametype=$g or gametype='') AND (modtype=$m or modtype='')");

	my ($oldest, $newest) = $db->get_row_array("SELECT MIN(statdate), MAX(statdate) FROM $db->{t_map_data}");
	if (!$oldest and !$newest) {
		$::ERR->info("No historical stats available. Aborting award calculations.");
		return;
	}

	# generate a daily or weekly/monthly awards
	my $days = [];
	if (defined $conf->get_opt('award') and $conf->get_opt('start')) {
		$doweekly = $domonthly = $oldest = $newest = 0;
		$dodaily = 1;
		$oldest = ymd2time($conf->get_opt('start')) if $conf->get_opt('start') =~ /^\d\d\d\d-\d\d?-\d\d?$/;
		if ($oldest) {
			if ($conf->get_opt('end')) {
				if ($conf->get_opt('end') =~ /^\d\d\d\d-\d\d?-\d\d?$/) {
					$newest = ymd2time($conf->get_opt('end'));
				} elsif ($conf->get_opt('end') =~ /^\d+$/) {
					$newest = $oldest + 60*60*24*($conf->get_opt('end')-1);
				}
			} else {
				$newest = $oldest;
			}
		}
		if (!$oldest or !$newest) {
			$::ERR->warn("Invalid daily award date given. Aborting award calculations.");
			return;
		}
		# override @awards to the single award that was specified (by name or ID)
		if (my $a = $conf->get_opt('award')) {
			my $id = 0;
			if ($a =~ /^\d+$/) {
				$id = $a;
			} else {
				$id = $db->select($db->{t_config_awards}, 'id', [ name => $a ]);
			}
			@awards = $db->get_rows_hash("SELECT * FROM $db->{t_config_awards} WHERE enabled AND (gametype=$g or gametype='') AND (modtype=$m or modtype='') AND id=$id") if $id;
			if (!@awards or !$id) {
				$::ERR->warn("Invalid award name or ID given. Aborting award calculations.");
				return;
			}
		}

		# temp: right now I am only allowing it to generate a 'day' award on the date that was specified (not a range)
		push(@$days, $oldest);

	} else {
		$oldest = ymd2time($oldest);
		$newest = ymd2time($newest);
	}

	my $weeks = [];
	if ($doweekly) {
		# curdate will always start on the first day of the week
		my $curdate = $oldest - ($oneday * (localtime($oldest))[6]);
		$curdate += $oneday if $startofweek eq 'monday';
		while ($curdate <= $newest) {
			last if $fullweekonly and $curdate + $oneweek - $oneday > $newest;
			push(@$weeks, $curdate);
#			$::ERR->verbose(strftime("weekly:  #$weekcode: %Y-%m-%d\n", localtime($curdate)));
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
#			$::ERR->verbose(strftime("monthly: #$weekcode: %Y-%m-%d\n", localtime($curdate)));
			$curdate += $onemonth;						# go forward 1 month
		}
	}

	# loop through awards and calculate
	foreach my $a (@awards) {
		my $award = PS::Award->new($a, $self);
		if (!$award) {
			$::ERR->warn("Award '$a->{name}' can not be processed due to errors: $@");
			next;
		} 
		$award->calc('month', $months) if $domonthly;
		$award->calc('week', $weeks) if $doweekly;
		$award->calc('day', $days) if $dodaily;
	}

	$self->{conf}->setinfo('daily_awards.lastupdate', time);
	$::ERR->info("Daily process completed: 'awards' (Time elapsed: " . compacttime(time-$start,'mm:ss') . ")");
}

# daily process for updating player ranks. Assigns a rank to each player based on their skill
# ... this should be updated to allow for different criteria to be used to determine rank.
sub daily_ranks {
	my $self = shift;
	my $db = $self->{db};
	my $lastupdate = $self->{conf}->getinfo('daily_ranks.lastupdate') || 0;
	my $start = time;
	my ($sth, $cmd);

	$::ERR->info(sprintf("Daily 'ranks' process running (Last updated: %s)", 
		$lastupdate ? scalar localtime $lastupdate : 'never'
	));

	$cmd = "SELECT plrid,rank,skill FROM $db->{t_plr} WHERE allowrank ORDER BY skill DESC";
	if (!($sth = $db->query($cmd))) {
		$db->fatal("Error executing DB query:\n$cmd\n" . $db->errstr . "\n--end of error--");
	}

	$db->begin;

	my $newrank = 0;
	my $prevskill = -999999;
	# This will allow players with the SAME skill to receive the SAME rank. But this is slower
	while (my ($id,$rank,$skill) = $sth->fetchrow_array) {
		$cmd = "UPDATE $db->{t_plr} SET prevrank=rank, rank=" . ($prevskill == $skill ? $newrank : ++$newrank) . " WHERE plrid=$id";
		$db->query($cmd) if $rank ne $newrank;
		$prevskill = $skill;
	}
	$db->update($db->{t_plr}, { rank => 0 }, [ allowrank => 0 ]);

	# this is mysql specific and also does not allow same skill players to receive the same rank (but it's fast)
#	if ($db->type eq 'mysql') {
#		$db->query("SET \@newrank := 0");
#		$db->query("UPDATE $db->{t_plr} SET prevrank=rank, rank=IF(allowrank, \@newrank:=\@newrank+1, 0) ORDER BY skill DESC");
#	} elsif ($db->type eq 'sqlite') {
#
#	}

	$db->commit;

	$self->{conf}->setinfo('daily_ranks.lastupdate', time);

	$::ERR->info("Daily process completed: 'ranks' (Time elapsed: " . compacttime(time-$start,'mm:ss') . ")");
}

# updates the decay of all players
sub daily_decay {
	my $self = shift;
	my $conf = $self->{conf};
	my $db = $self->{db};
	my $lastupdate = $self->{conf}->getinfo('daily_decay.lastupdate') || 0;
	my $start = time;
	my $decay_hours = $conf->get_main('players.decay_hours');
	my $decay_type = $conf->get_main('players.decay_type');
	my $decay_value = $conf->get_main('players.decay_value');
	my ($sth, $cmd);

	if (!$decay_type) {
		$::ERR->info("Daily 'decay' process skipped, decay is disabled.");
		return;
	}

	$::ERR->info(sprintf("Daily 'decay' process running (Last updated: %s)", 
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

	$self->{conf}->setinfo('daily_decay.lastupdate', time);

	$::ERR->info("Daily process completed: 'decay' (Time elapsed: " . compacttime(time-$start,'mm:ss') . ")");
}

# daily process for updating clans. Toggles clans from being displayed based on the clan config settings.
sub daily_clans {
	my $self = shift;
	my $db = $self->{db};
	my $lastupdate = $self->{conf}->getinfo('daily_clans.lastupdate');
	my $start = time;
	my $last = time;
	my $types = PS::Player->get_types;
	my ($cmd, $sth, $sth2, $rules, @min, @max, $allowed, $fields);

	$::ERR->info(sprintf("Daily 'clans' process running (Last updated: %s)", 
		$lastupdate ? scalar localtime $lastupdate : 'never'
	));

	return 0 unless $db->table_exists($db->{c_plr_data});

	# gather our min/max rules ...
	$rules = { %{$self->{conf}->get_main('clans') || {}} };
	delete @$rules{ qw(IDX SECTION) };
	@min = ( map { s/^min_//; $_ } grep { /^min_/ && $rules->{$_} ne '' } keys %$rules );
	@max = ( map { s/^max_//; $_ } grep { /^max_/ && $rules->{$_} ne '' } keys %$rules );

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
			((grep { ($row->{$_}||0) < $rules->{'min_'.$_} } @min) == 0) 
			&& 
			((grep { ($row->{$_}||0) > $rules->{'max_'.$_} } @max) == 0)
		) ? 1 : 0;
		if (!$allowed and $::DEBUG) {
			$self->info("Clan failed to rank \"$row->{clantag}\" => " . 
				join(', ', 
					map { "$_: " . $row->{$_} . " < " . $rules->{"min_$_"} } grep { $row->{$_} < $rules->{"min_$_"} } @min,
					map { "$_: " . $row->{$_} . " > " . $rules->{"max_$_"} } grep { $row->{$_} > $rules->{"max_$_"}} @max
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

	$self->{conf}->setinfo('daily_clans.lastupdate', time);
	$::ERR->info("Daily process completed: 'clans' (Time elapsed: " . compacttime(time-$start,'mm:ss') . ")");
}

# daily process for updating players. This should be run before daily_clans
# Toggles players from being displayed based on the players config settings.
sub daily_players {
	my $self = shift;
	my $db = $self->{db};
	my $lastupdate = $self->{conf}->getinfo('daily_players.lastupdate');
	my $start = time;
	my $last = time;
	my $types = PS::Player->get_types;
	my ($cmd, $sth, $sth2, $rules, @min, @max, $allowed, $fields);

	$::ERR->info(sprintf("Daily 'players' process running (Last updated: %s)", 
		$lastupdate ? scalar localtime $lastupdate : 'never'
	));

	return 0 unless $db->table_exists($db->{c_plr_data});

	# gather our min/max rules ...
	$rules = { %{$self->{conf}->get_main('players') || {}} };
	delete @$rules{ qw(IDX SECTION) };
	@min = ( map { s/^min_//; $_ } grep { /^min_/ && $rules->{$_} ne '' } keys %$rules );
	@max = ( map { s/^max_//; $_ } grep { /^max_/ && $rules->{$_} ne '' } keys %$rules );

        # add extra fields to our query that match values in our min/max arrays
	my %uniq = ( plrid => 1, uniqueid => 1, skill => 1 );
	$fields = join(', ', grep { !$uniq{$_}++ } (@min,@max));

	# first remove players (and their profile) that don't actually have any compiled stats	
	$cmd  = "DELETE FROM p, pp USING ($db->{t_plr} p, $db->{t_plr_profile} pp) ";
	$cmd .= "LEFT JOIN $db->{c_plr_data} c ON c.plrid=p.plrid WHERE c.plrid IS NULL AND p.uniqueid=pp.uniqueid";
	$db->query($cmd);	# don't care if it fails ...

	# load player list
	$cmd  = "SELECT plr.*, pp.name, $fields ";
	$cmd .= "FROM $db->{t_plr} plr, $db->{t_plr_profile} pp, $db->{c_plr_data} data ";
	$cmd .= "WHERE pp.uniqueid=plr.uniqueid AND data.plrid=plr.plrid ";
#	print "$cmd\n";
	if (!($sth = $db->query($cmd))) {
		$db->fatal("Error executing DB query:\n$cmd\n" . $db->errstr . "\n--end of error--");
	}

	$db->begin;
	my (@rank,@norank);
	while (my $row = $sth->fetchrow_hashref) {
		# does the plr meet all the requirements for ranking?
		$allowed = (
			((grep { ($row->{$_}||0) < $rules->{'min_'.$_} } @min) == 0) 
			&& 
			((grep { ($row->{$_}||0) > $rules->{'max_'.$_} } @max) == 0)
		) ? 1 : 0;
		if (!$allowed and $::DEBUG) {
			$self->info("Player failed to rank \"$row->{name}\" " . ($self->{uniqueid} ne 'name' ?  "($row->{uniqueid})" : "") . "=> " . 
				join(', ', 
					map { "$_: " . $row->{$_} . " < " . $rules->{"min_$_"} } grep { $row->{$_} < $rules->{"min_$_"} } @min,
					map { "$_: " . $row->{$_} . " > " . $rules->{"max_$_"} } grep { $row->{$_} > $rules->{"max_$_"}} @max
				)
			);
		}

		# update the plr if their allowrank flag has changed
		if ($allowed != $row->{allowrank}) {
			# SQLite doesn't like it when i try to read/write to the database at the same time
			if ($db->type eq 'sqlite') {
				if ($allowed) {
					push(@rank, $row->{plrid});
				} else {
					push(@norank, $row->{plrid});
				}
			} else {
				$db->update($db->{t_plr}, { allowrank => $allowed }, [ plrid => $row->{plrid} ]);
			}
		}
	}
	undef $sth;
	$db->query("UPDATE $db->{t_plr} SET allowrank=1 WHERE plrid IN (" . join(',', @rank) . ")") if @rank;
	$db->query("UPDATE $db->{t_plr} SET allowrank=0 WHERE plrid IN (" . join(',', @norank) . ")") if @norank;
	$db->commit;

	$self->{conf}->setinfo('daily_players.lastupdate', time);

	$::ERR->info("Daily process completed: 'players' (Time elapsed: " . compacttime(time-$start,'mm:ss') . ")");
}

sub _delete_stale_players {
	my $self = shift;
	my $oldest = shift || return;
	my $db = $self->{db};
	my $conf = $self->{conf};
	my $sql_oldest = $db->quote($oldest);
	my @delete;

	return 0 unless $db->table_exists($db->{c_plr_data});

	$db->begin;

	# keep track of what stats are being deleted 
	my $total = $db->do("INSERT INTO plrids SELECT DISTINCT plrid FROM $db->{t_plr_data} WHERE statdate <= $sql_oldest");

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

	# delete remaining historical stats 
	$db->do("DELETE FROM $db->{t_plr_victims} WHERE statdate <= $sql_oldest");
	$db->do("DELETE FROM $db->{t_plr_roles} WHERE statdate <= $sql_oldest");
	$db->do("DELETE FROM $db->{t_plr_weapons} WHERE statdate <= $sql_oldest");

	# sessions are stored slightly differently
#	$db->do("DELETE FROM $db->{t_plr_sessions} WHERE sessionend <= UNIX_TIMESTAMP($sql_oldest)");
	$db->do("DELETE FROM $db->{t_plr_sessions} WHERE FROM_UNIXTIME(sessionstart,'%Y-%m-%d') <= $sql_oldest");

	# only delete the compiled data if maxdays_exclusive is enabled
	if ($self->{maxdays_exclusive}) {
		# Any player in deleteids hasn't played since the oldest date allowed, so get rid of them completely
		$db->do("INSERT INTO deleteids SELECT plrid FROM $db->{c_plr_data} WHERE lastdate <= $sql_oldest");
		$db->do("DELETE FROM $db->{t_plr} WHERE plrid IN (SELECT id FROM deleteids)");
		$db->do("DELETE FROM $db->{t_plr_ids} WHERE plrid IN (SELECT id FROM deleteids)");
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
	my $conf = $self->{conf};
	my $sql_oldest = $db->quote($oldest);
	my @delete;

	return 0 unless $db->table_exists($db->{c_map_data});

	$db->begin;

	# keep track of what stats are being deleted 
	my $total = $db->do("INSERT INTO mapids SELECT DISTINCT mapid FROM $db->{t_map_data} WHERE statdate <= $sql_oldest");

	# delete basic data
	$db->do("INSERT INTO deleteids SELECT dataid FROM $db->{t_map_data} WHERE statdate <= $sql_oldest");
	$db->do("DELETE FROM $db->{t_map_data_mod} WHERE dataid IN (SELECT id FROM deleteids)") if $db->{t_map_data_mod};
	$db->do("DELETE FROM $db->{t_map_data} WHERE dataid IN (SELECT id FROM deleteids)");
	$db->truncate('deleteids');

	# only delete the compiled data if maxdays_exclusive is enabled
	if ($self->{maxdays_exclusive}) {
		$db->do("DELETE FROM $db->{c_map_data} WHERE lastdate <= $sql_oldest");
	}

	$db->commit;

	return $total;
}

sub _delete_stale_roles {
	my $self = shift;
	my $oldest = shift || return;
	my $db = $self->{db};
	my $conf = $self->{conf};
	my $sql_oldest = $db->quote($oldest);
	my @delete;

	return 0 unless $db->table_exists($db->{c_role_data});

	$db->begin;

	# keep track of what stats are being deleted 
	my $total = $db->do("INSERT INTO roleids SELECT DISTINCT roleid FROM $db->{t_role_data} WHERE statdate <= $sql_oldest");

	$db->do("DELETE FROM $db->{t_role_data} WHERE statdate <= $sql_oldest");

	# only delete the compiled data if maxdays_exclusive is enabled
	if ($self->{maxdays_exclusive}) {
		$db->do("DELETE FROM $db->{c_role_data} WHERE lastdate <= $sql_oldest");
	}

	$db->commit;

	return $total;
}

sub _delete_stale_weapons {
	my $self = shift;
	my $oldest = shift || return;
	my $db = $self->{db};
	my $conf = $self->{conf};
	my $sql_oldest = $db->quote($oldest);
	my @delete;

	return 0 unless $db->table_exists($db->{c_weapon_data});

	$db->begin;

	# keep track of what stats are being deleted 
	my $total = $db->do("INSERT INTO weaponids SELECT DISTINCT weaponid FROM $db->{t_weapon_data} WHERE statdate <= $sql_oldest");

	$db->do("DELETE FROM $db->{t_weapon_data} WHERE statdate <= $sql_oldest");

	# only delete the compiled data if maxdays_exclusive is enabled
	if ($self->{maxdays_exclusive}) {
		$db->do("DELETE FROM $db->{c_weapon_data} WHERE lastdate <= $sql_oldest");
	}

	$db->commit;

	return $total;
}

sub _update_player_stats {
	my $self = shift;
	my $oldest = shift || return;
	my $db = $self->{db};
	my $conf = $self->{conf};
	my $sql_oldest = $db->quote($oldest);
	my $total = 0;
	my ($cmd, $fields, $o, $types, $sth);

	return 0 unless $self->{maxdays_exclusive} and $db->table_exists($db->{c_plr_data});

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

	return $total;
}

sub _update_player_weapons {
	my $self = shift;
	my $oldest = shift || return;
	my $db = $self->{db};
	my $conf = $self->{conf};
	my $sql_oldest = $db->quote($oldest);
	my $total = 0;
	my ($cmd, $fields, $o, $types, $sth);

	return 0 unless $self->{maxdays_exclusive} and $db->table_exists($db->{c_plr_weapons});

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
	my $conf = $self->{conf};
	my $sql_oldest = $db->quote($oldest);
	my $total = 0;
	my ($cmd, $fields, $o, $types, $sth);

	return 0 unless $self->{maxdays_exclusive} and $db->table_exists($db->{c_plr_roles});

	$o = PS::Player->new(undef, $self);
	$types = $o->get_types_roles;
	$types->{firstdate} = '=';		# need to add these so save_stats will write them
	$types->{lastdate} = '=';
	$fields = $db->_values($types);

	$db->begin;

	$cmd  = "SELECT plrid,roleid,MIN(statdate) firstdate, MAX(statdate) lastdate,$fields FROM $db->{t_plr_roles} ";
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
	my $conf = $self->{conf};
	my $sql_oldest = $db->quote($oldest);
	my $total = 0;
	my ($cmd, $fields, $o, $types, $sth);

	return 0 unless $self->{maxdays_exclusive} and $db->table_exists($db->{c_plr_victims});

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
	my $conf = $self->{conf};
	my $sql_oldest = $db->quote($oldest);
	my $total = 0;
	my ($cmd, $fields, $o, $types, $sth);

	return 0 unless $self->{maxdays_exclusive} and $db->table_exists($db->{c_plr_maps});

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
	my $conf = $self->{conf};
	my $sql_oldest = $db->quote($oldest);
	my $total = 0;
	my ($cmd, $fields, $o, $types, $sth);

	return 0 unless $self->{maxdays_exclusive} and $db->table_exists($db->{c_map_data});

	$o = PS::Map->new(undef, $conf, $db);
	$types = $o->get_types;
	$types->{firstdate} = '=';		# need to add these so save_stats will write them
	$types->{lastdate} = '=';
	$fields = $db->_values($types);

	$db->begin;

	# if exclusive update stats to remove old data
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
	my $conf = $self->{conf};
	my $sql_oldest = $db->quote($oldest);
	my $total = 0;
	my ($cmd, $fields, $o, $types, $sth);

	return 0 unless $self->{maxdays_exclusive} and $db->table_exists($db->{c_weapon_data});

	$o = PS::Weapon->new(undef, $conf, $db);
	$types = $o->get_types;
	$types->{firstdate} = '=';		# need to add these so save_stats will write them
	$types->{lastdate} = '=';
	$fields = $db->_values($types);

	$db->begin;

	# if exclusive update stats to remove old data
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
	my $conf = $self->{conf};
	my $sql_oldest = $db->quote($oldest);
	my $total = 0;
	my ($cmd, $fields, $o, $types, $sth);

	return 0 unless $self->{maxdays_exclusive} and $db->table_exists($db->{c_role_data});

	$o = PS::Role->new(undef, '', $conf, $db);
	$types = $o->get_types;
	$types->{firstdate} = '=';		# need to add these so save_stats will write them
	$types->{lastdate} = '=';
	$fields = $db->_values($types);

	$db->begin;

	# if exclusive update stats to remove old data
	$cmd  = "SELECT roleid, MIN(statdate) firstdate, MAX(statdate) lastdate, $fields FROM $db->{t_role_data} data ";
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
	my $conf = $self->{conf};
	my $lastupdate = $self->{conf}->getinfo('daily_maxdays.lastupdate');
	my $start = time;
	my $last = time;
	my ($cmd, $sth, $ok, $fields, @delete, @ids, $o, $types, $total, $alltotal,%t);

	$::ERR->info(sprintf("Daily 'maxdays' process running (Last updated: %s)", 
		$lastupdate ? scalar localtime $lastupdate : 'never'
	));

	# determine the oldest date to delete
#	my $oldest = strftime("%Y-%m-%d", localtime(time-60*60*24*($self->{maxdays}+1)));
# I think it'll be better to use the newest date in the database instead of the current time to determine where to trim stats.
# This way the database won't lose stats if it stops getting new logs for a period of time.
	my ($oldest) = $db->get_list("SELECT MAX(statdate) - INTERVAL $self->{maxdays} DAY FROM $db->{t_plr_data}");
	goto MAXDAYS_DONE unless $oldest;	# will be null if there's no historical data available
	my $sql_oldest = $db->quote($oldest);

	$::ERR->verbose("Deleting stale stats older than $oldest ...");

	# first create a temporary tables to store ids (dont want to use potentially huge arrays in memory)
	$db->do("CREATE TEMPORARY TABLE deleteids (id INT UNSIGNED PRIMARY KEY)");
	$db->do("CREATE TEMPORARY TABLE plrids (id INT UNSIGNED PRIMARY KEY)");
	$db->do("CREATE TEMPORARY TABLE mapids (id INT UNSIGNED PRIMARY KEY)");
	$db->do("CREATE TEMPORARY TABLE roleids (id INT UNSIGNED PRIMARY KEY)");
	$db->do("CREATE TEMPORARY TABLE weaponids (id INT UNSIGNED PRIMARY KEY)");

	$t{plrs} = $total = $self->_delete_stale_players($oldest);
	$::ERR->info(sprintf("%s stale players deleted!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$t{maps} = $total = $self->_delete_stale_maps($oldest);
	$::ERR->info(sprintf("%s stale maps deleted!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$t{weapons} = $total = $self->_delete_stale_weapons($oldest);
	$::ERR->info(sprintf("%s stale weapons deleted!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$t{roles} = $total = $self->_delete_stale_roles($oldest);
	$::ERR->info(sprintf("%s stale roles deleted!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$::ERR->verbose("Recalculating compiled stats ...");
	$::ERR->verbose("This may take several minutes ... ");
	$total = 0;

	$total = $self->_update_map_stats($oldest) if $t{maps};
	$::ERR->info(sprintf("%s maps updated!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$total = $self->_update_role_stats($oldest) if $t{roles};
	$::ERR->info(sprintf("%s roles updated!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$total = $self->_update_weapon_stats($oldest) if $t{weapons};
	$::ERR->info(sprintf("%s weapons updated!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$total = $self->_update_player_stats($oldest) if $t{plrs};
	$::ERR->info(sprintf("%s players updated!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$total = 0;
	$total = $self->_update_player_maps($oldest) if $t{plrs};
	$::ERR->info(sprintf("%s player maps updated!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$total = $self->_update_player_roles($oldest) if $t{plrs};
	$::ERR->info(sprintf("%s player roles updated!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$total = $self->_update_player_victims($oldest) if $t{plrs};
	$::ERR->info(sprintf("%s player victims updated!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	$total = $self->_update_player_weapons($oldest) if $t{plrs};
	$::ERR->info(sprintf("%s player weapons updated!", commify($total))) if $total;
	$alltotal += $total;
	return if $::GRACEFUL_EXIT > 0;

	# if NOTHING was updated then dont bother optimizing tables
	if ($alltotal) {
		$::ERR->info("Optimizing database tables ...");
		# optimize all the tables, since we probably just deleted a lot of data

		$db->optimize(map { $db->{$_} } grep { /^[ct]\_/ } keys %$db);	# do them ALL! muahahahah
	}

MAXDAYS_DONE:
	$self->{conf}->setinfo('daily_maxdays.lastupdate', time);
	$::ERR->info("Daily process completed: 'maxdays' (Time elapsed: " . compacttime(time-$start,'mm:ss') . ")");
}

# row is updated directly
# no longer used. ..
sub _prepare_row {
	my ($self, $row, $types) = @_;
	foreach my $key (keys %$types) {
		next if ref $types->{$key};
		if ($types->{$key} eq '=') {			# do not update static fields
			delete $row->{$key};
		} elsif (!defined $row->{$key}) {		# delete keys that do not exist already
			delete $row->{$key};
		} elsif ($types->{$key} eq '+') {		# negate the value so it updates properly
			if ($row->{$key} eq '0') {
				delete $row->{$key};		# no sense in updating fields that are zero
			} else {
				$row->{$key} = -$row->{$key} if defined $row->{$key};
			}
		} else {
			# any value with ">" or "<" will update normally like a calculated field
		}
	}
}

# deletes clans from the database. Does not delete the profiles. Sets all player.clanid to 0. 
sub delete_clans {
	my $self = shift;
	my $db = $self->{db};
	# NOP
}

# resets all stats in the database. USE WITH CAUTION!
# reset(1) resets stats and all profiles
# reset(0) resets stats and NO profiles
# reset(player => 1, clans => 0) resets stats and only the profiles specified
sub reset {
	my $self = shift;
	my $del = @_ == 1 ? { players => $_[0], clans => $_[0] } : { @_ };
	my $db = $self->{db};
	my $gametype = $self->{conf}->get_main('gametype');
	my $modtype  = $self->{conf}->get_main('modtype');
	my $errors = 0;

	my @empty_c = qw( c_map_data c_plr_data c_plr_maps c_plr_victims c_plr_weapons c_weapon_data c_role_data c_plr_roles );
	my @empty_m = qw( t_map_data_mod t_plr_data_mod t_plr_maps_mod );
	my @empty = qw(
		t_awards t_awards_plrs
		t_clan
		t_errlog
		t_map t_map_data
		t_plr t_plr_data t_plr_ids t_plr_maps t_plr_roles t_plr_sessions t_plr_victims t_plr_weapons
		t_role t_role_data
		t_search t_state t_state_plrs
		t_weapon t_weapon_data
	);

	# delete compiled data
	foreach my $t (@empty_c) {
		my $tbl = $db->{$t} || next;
#		print "DROP $tbl\n";
		if (!$db->droptable($tbl) and $db->errstr !~ /unknown table/i) {
			$self->warn("Reset error on $tbl: " . $db->errstr);
			$errors++;
		}
	}

	# delete most of everything else
	foreach my $t (@empty) {
		my $tbl = $db->{$t} || next;
#		print "TRUNCATE $t: $tbl\n";
		if (!$db->truncate($tbl) and $db->errstr !~ /exist/) {
			$self->warn("Reset error on $tbl: " . $db->errstr);
			$errors++;
		}
	}

	# delete mod specific tables
	foreach my $t (@empty_m) {
		my $tbl = $db->{$t} || next;
#		print "TRUNCATE $t: $tbl\n";
		if (!$db->truncate($tbl) and $db->errstr !~ /exist/) {
			$self->warn("Reset error on $tbl: " . $db->errstr);
			$errors++;
		}
	}

	if ($del->{players}) {
		my $tbl = $db->{t_plr_profile} || next;
#		print "TRUNCATE plr: $tbl\n";
		if (!$db->truncate($tbl)) {
			$self->warn("Reset error on $tbl: " . $db->errstr);
			$errors++;
		}
	}

	if ($del->{clans}) {
		my $tbl = $db->{t_clan_profile} || next;
#		print "TRUNCATE clan: $tbl\n";
		if (!$db->truncate($tbl)) {
			$self->warn("Reset error on $tbl: " . $db->errstr);
			$errors++;
		}
	}

	$self->info("Player stats have been reset!! (from command line)");

	return ($errors == 0);
}

sub has_mod_tables { 0 }

1;

