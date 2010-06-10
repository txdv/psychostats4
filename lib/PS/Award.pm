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
package PS::Award;

use strict;
use warnings;
use base qw( PS::Core );

use FindBin;
use File::Spec::Functions;
use POSIX qw( strftime );
use Safe;
use Time::Local;
use PS::SourceFilter;

our $VERSION = '1.00.' . (('$Rev$' =~ /(\d+)/)[0] || '000');

our $CLASSES = {};	# keep track of what sub-classes were created already
our %DEFAULTS = (
	caption		=> 'Unknown',	# short name for award
	description	=> '',		# description of award
	class		=> 'basic',	# award subclass (basic, composite, etc...)
	type		=> 'player',	# type of award (player, weapon, weapons, etc...)
	reaction	=> 'positive',	# is award positive or negative? (most suicides are negative, etc)
	phrase		=> '',		# visible phrase for award (ie: '$player has the most kills') (tokenized)
	expression	=> '',		# SQL expression to calculate values (tokenized)
	where		=> '',		# extra where clause (tokenized)
	order		=> 'desc',	# how to sort the values ('asc', 'desc')
	limit		=> 1,		# how many players can get award
	format		=> '%s',	# how the award is output
	max_rank	=> 1,		# only include plrs ranked <= this value
	gametype	=> '',		# gametype to pull data from
	modtype		=> '',		# modtype to pull data from
);

#our $DATETIME = 0;
#eval {
#	# try to load DateTime if available
#	use DateTime;
#	$DATETIME = 1;
#};

sub new {
	my ($proto, $db, $type, $subclass, $gametype, $modtype) = @_;
	my $baseclass = ref($proto) || $proto;
	my $self = {
		type		=> lc $type,
		class		=> lc $subclass,
		gametype	=> $gametype,
		modtype 	=> $modtype,
	};
	
	# note: we're not cloning the prepared statements from $db since
	# we don't need them here...
	$self->{db} = $db->clone(0);
	$self->{db}{fatal} = 0;

	$type = ucfirst(lc $type);
	$subclass = ucfirst(lc $subclass);
	
	# verify the award sub-class exists
	my $class = join('::', 'PS', 'Award', $type, $subclass);
	if (!exists $CLASSES->{$class}) {
		my $file = catfile($FindBin::Bin, 'lib', split('::', $class)) . '.pm';
		if (!-f $file) {
			$@ = "Subclass file $file not found";
			return;
		}

		eval "require $class";
		return if $@;

		$CLASSES->{$class} = $class;
	}
	
	bless($self, $class);
	return $self->_init;
}


# award plugins need to override this to initialize themselves
# return a reference to the award object ($self)
sub _init {
	# nothing to do here...
	return $_[0]; # self
}

# cleanup any memory before the object is destroyed
sub done {
	my ($self) = @_;
	$self->{db}->disconnect if $self->{db};
}

# sets the expression to use to calculate the award
sub expr {
	my ($self, $expr) = @_;
	$self->{expr} = $expr;
	return $self;
}

sub date_range {
	my ($self, $time, $range) = @_;
	$range = 'month' unless $range =~ /^(?:month|week|day)$/;
	$self->{start_date} = $self->time2ymd($time);
	$self->{end_date} = $self->end_date($time, $range);
	return $self;
}

# sets the where clause that will restrict how the calculations are performed.
sub where {
	my ($self, $where) = @_;
	$self->{where} = $self->interpolate($where);
	return $self;
}

sub min_value {
	my ($self, $value) = @_;
	$self->{min_value} = $value;
	return $self;
}

sub max_rank {
	my ($self, $rank) = @_;
	$self->{max_rank} = $rank;
	return $self;
}

sub ranked_only {
	my ($self, $rank) = @_;
	$self->{ranked_only} = $rank;
	return $self;
}

# sets the limit on how many items (players, etc...) are allowed to get award.
sub limit {
	my ($self, $limit) = @_;
	$self->{limit} = $limit;
	return $self;
}

sub order {
	my ($self, $order) = @_;
	$self->{order} = $order;
	return $self;
}
# performs the final calculations for the award. Returns a list of plrs,
# weapons, etc that make up the award.
sub calc {
	my ($self) = @_;
	return;
}

sub reset {
	my ($self) = @_;
	$self->{$_} = undef for qw( expr start_date end_date max_rank ranked_only where min_value order limit );
	return $self;
}

# create a list of variables that are used for interpolation of column names
# within calculations.
sub add_vars_from_table {
	my $self = shift;

	if (!ref $self->{vars}) {
		$self->{vars} = {};
	}
	
	while (defined(my $table = shift)) {
		next unless $self->{db}->table_exists($table);
		my @keys = keys %{$self->{db}->table_info($table)};
		foreach my $key (@keys) {
			my $func = $self->var_func($key) || next;
			my $aggr = $key;
			# handle special vars to avoid ambiguities
			if ($aggr =~ /^(?:skill|rank)$/) {
				$aggr = 'data.' . $aggr;
			}
			$self->{vars}{$key} = "$func($aggr)";
		}

		## try and assign fields from the game::mod table if it exists
		#if ($gametype and $modtype and index($table, $gametype.'_') < 0) {
		#	fields_by_table($fields, $db->{'t_plr_data'} . '_' . $gametype . '_' . $modtype);
		#}
	}
	
	return $self;
}

# add arbitrary column vars
sub add_vars {
	my $self = shift;
	my %keys = ref $_[0] ? %{$_[0]} : @_;
	while (my($k,$v) = each %keys) {
		if (!defined $v) {
			$v = $self->var_func($k) || next;
			$self->{vars}{$k} = "$v($k)";
		} else {
			$self->{vars}{$k} = $v;
		}
	}
	return $self;
}

sub var_func {
	my ($self, $key) = @_;
	# certain keys should be ignored.
	if ($key =~ /(?:id|type|seen|date)$/) {
		# ignore columns ending in 'id', 'type', 'seen', or 'date'
		return;
	} elsif ($key =~ /(?:streak|skill)$/) {
		return 'MAX';
	} elsif ($key eq 'rank') {
		return 'MIN';
	}
	# most column keys will be SUM'ed
	return 'SUM';
}

sub save {
	my ($self) = @_;
	warn ref $self, "->save not implemented";
	return $self;
}

# can be called as a package or object method
sub is_complete {
	my ($self, $date, $range, $conf_id) = @_;
	my $name = 'is_complete';
	
	# default conf_id to our local award config, if $self is instantiated
	if (!$conf_id and ref $self) {
		$conf_id ||= $self->{award}{conf_id};
	}

	# explicitly return 1 if the conf_id is not known.
	return 1 unless $conf_id;

	# prepare the query if it hasn't already. 
	my $sth;
	if (!($sth = $self->{db}->prepared($name))) {
		$sth = $self->{db}->prepare($name, qq{
			SELECT completed FROM t_awards
			WHERE award_date=? AND award_range=? AND conf_id=? AND completed IS NOT NULL
		});
	}

	# convert epoch time to YYYY-MM-DD
	if ($date =~ /^\d+$/) {
		$date = $self->time2ymd($date);
	}

	my $exists = $sth->execute($date, $range, $conf_id);
	$sth->finish;

	return if !defined $exists or $exists eq '0E0';
	return $exists;
}

# converts a date of "YYYY-MM-DD" into a unix epoch timestamp
sub ymd2time {
	my ($self, $date) = @_;
	my @ary = reverse split(/[^\d]+/, $date);
	$ary[1]--;
	$ary[2] -= 1900;
	return timegm(0,0,12,@ary);
}

# converts the time into a YYYY-MM-DD string
sub time2ymd {
	my ($self, $time, $char) = @_;
	$char ||= '-';
	return strftime("%Y$char%m$char%d", gmtime($time));
}

# returns the end date relative to the start date according to the range given
sub end_date {
	my ($self, $date, $range) = @_;
	my $start = ($date =~ /^\d+$/) ? $date : $self->ymd2time($date);
	my $end = $start;
	my $ofs = 0;
	$range = lc $range;
	if ($range eq 'month') {
		$ofs = $self->days_in_month($start) - 1;
	} elsif ($range eq 'week') {
		$ofs = 6;
	}
	$end += 60*60*24*$ofs if $ofs;
	return strftime("%Y-%m-%d", gmtime($end));
}

# returns the number of days in the month/year given
sub days_in_month {
	my ($self, $month, $year) = @_;
	if (!defined $year) {
		($month, $year) = (gmtime($month))[4,5];
		$month += 1;
		$year += 1900;
	}
	return (gmtime(POSIX::mktime(0,0,0,0,$month,$year-1900,0,0,-1)))[3];
}

# A very simple version of an interpolating routine to do very simple variable
# substitution on a string. This allows for 2 levels of hash variables ONLY. ie:
# {$key}, or {$key.var} (but not {$key.var.subvar}). 
sub interpolate {
	my ($self, $str, $fill, $vars) = @_;
	my ($var1,$var2, $rep, $rightpos, $leftpos, $varlen);
	return '' unless defined $str;
	$fill ||= 0;
	$vars ||= $self->{vars};

	# match $token or {$key.token} (but not {$123token}) 
	while ($str =~ /\{\$([a-z][a-z\d_]+)(?:\.([a-z][a-z\d_]+))?\}/gsi) {
		$var1 = lc $1;
		$var2 = lc($2 || '');
		$varlen = length($var1 . $var2) + 2;	# +2 for "{}" chars
		if (exists $vars->{$var1}) {
			if ($var2 ne '') {
				if (exists $vars->{$var1}{$var2}) {
					$rep = $vars->{$var1}{$var2};
				} else {
					$rep = $fill ? "{$var1.$var2}" : '';
				}
				# must account for the extra '.' in {$token.var}
				$varlen++;
			} else {
				$rep = $vars->{$var1};
			}
		} else {
			$rep = $fill ? $var1 : '';
		} 

		$rightpos = pos($str) - 1;
		$leftpos  = $rightpos - $varlen;
		substr($str, $leftpos, $rightpos-$leftpos+1, $rep);
	}
	return $str;
}

# takes an array of dates and returns the dates that are not already marked as complete.
sub valid_dates {
	my $self = shift;
	my $range = shift;	# 'month', 'week' or 'day'
	my $dates = ref $_[0] ? shift : [ @_ ];
	my $db = $self->{db};
	my $a = $self->{award};
	my @valid;

	foreach my $date (@$dates) {
		next if $db->select($db->{t_awards}, 'awardcomplete', 
			[ awardid => $a->{id}, awarddate => time2ymd($date), awardrange => $range ]
		);
		push(@valid, $date);
	}

	return wantarray ? @valid : [ @valid ];
}

# returns the award value given with the proper formatting configured
sub format {
	my ($self, $value) = @_;
	my $format = $self->{award}{format};
	if ($format =~ /^[a-zA-Z]+$/) {		# code
		if ($format eq 'date') {
			$value = date($self->{conf}->get_theme('format.date'), $value);
		} elsif ($format eq 'datetime') {
			$value = date($self->{conf}->get_theme('format.datetime'), $value);
		} else { # commify, compacttime, ...
			if (!$self->{safe}) {
				$self->{safe} = new Safe;
				$self->{safe}->share_from('util', [qw( &commify &compacttime &int2ip &abbrnum )]);
			}
			my $ret = $self->{safe}->reval("$format('\Q$value\E')");
			if ($@) {
				$::ERR->warn("Error in award #$self->{award}{id} format '$format': $@");
			} else {
				$value = $ret;
			}
		}
		return $value;
	} elsif (index($format, '%') > -1) {	# sprintf
		return sprintf($format, $value);
	} else {				# unknown/invalid format
		return $value;
	}
}

1;

