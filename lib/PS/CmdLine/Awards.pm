package PS::CmdLine::Awards;
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
#
#Process Order
#-------------------------------
#
## Fetch list of award(s) that are configured and enabled. -award command line option can limit this.
#
## Determine the oldest and newest date available in the historical DB.
#
## Determine what days, weeks and months need to be calculated for awards.
#
## Caculate awards...
#
#
#Command Line Options
#-------------------------------
#
#-award [string|int]
#Generate award only for the one matching this value. 
#Can be the award name or primary ID.
#
#-daily
#-weekly
#-monthly
#Generate awards on the various time frames available.
#DEFAULT: based on config.
#
#-partial
#Allow partial awards. A partial award is calculated for a given time frame even if the entire time frame has not passed yet. EG: Create a weekly award in the middle of the week, or the current daily award in the middle of the day.
#DEFAULT: based on config.
#
#-date [yyyy-mm-dd]
#Specifies the date to calculate awards for.
#daily awards use -date literally.
#weekly awards use -date to determine what week its in.
#monthly awards use -date to determine what month its in.
#DEFAULT: current date.
#
#-yesterday
#Special shortcut for -date. Uses yesterdays date as the date baseline.
#

use strict;
use warnings;
use base qw( PS::CmdLine );

use Carp;
use Getopt::Long;
use Pod::Usage;
use Time::Local;

our $VERSION = '1.00.' . (('$Rev$' =~ /(\d+)/)[0] || '000');
our $AUTOLOAD;

# private: Loads command line parameters
sub _getOptions {
	my $self = shift;

	my $optok = GetOptions(
		# BASIC SETTINGS
		'config=s'		=> \$self->{param}{config},
		'noconfig'		=> \$self->{param}{noconfig},
		'help|?'		=> \$self->{param}{help},
		'V|version'		=> \$self->{param}{version},
		'v|verbose'		=> \$self->{param}{verbose},
		'gametype=s'		=> \$self->{param}{gametype},
		'modtype=s'		=> \$self->{param}{modtype},

		# DATABASE SETTINGS
		'dbtype=s'		=> \$self->{param}{dbtype},
		'dbhost=s'		=> \$self->{param}{dbhost},
		'dbport=s'		=> \$self->{param}{dbpost},
		'dbname=s'		=> \$self->{param}{dbname},
		'dbuser=s'		=> \$self->{param}{dbuser},
		'dbpass=s'		=> \$self->{param}{dbpass},
		'dbtblprefix:s'		=> \$self->{param}{dbtblprefix},

		# AWARD SETTINGS
		'award=s'		=> \$self->{param}{award},
		'range=s'		=> sub { $self->_opt_range(@_) },
		'daily'			=> \$self->{param}{daily},
		'weekly'		=> \$self->{param}{weekly},
		'monthly'		=> \$self->{param}{monthly},
		'partial'		=> \$self->{param}{partial},
		'date=s'		=> \$self->{param}{date},
		'monthly'		=> \$self->{param}{monthly},
		'yesterday'		=> sub { $self->_opt_yesterday(@_) },
		'reset:s'		=> \$self->{param}{reset},

		# grab extra params that are not options
		'<>'			=> sub { push(@PS::CmdLine::OPTS, shift) }
	);

	$self->{param}{debug} = 1 if defined $self->{param}{debug} and $self->{param}{debug} < 1;

	if (!$optok) {
#		die("Invalid parameters given. Insert help page");
		pod2usage({ -input => \*DATA, -verbose => 1 });
	}

	if ($self->{param}{help}) {
		pod2usage({ -input => \*DATA, -verbose => 2 });
	}

}

# alias for -date to use yesterdays date. (GMT)
sub _opt_yesterday {
	my ($self) = @_;
	my $date = timegm(gmtime) - 60*60*24;	# subtract 1 day from today
	my ($d,$m,$y) = (gmtime($date))[3,4,5];	# get date values
	$self->{param}{date} = sprintf('%04d-%02d-%02d', $y+1900, $m+1, $d);
}

# alias for -daily, -weekly, -monthly.
# -range <daily, weekly, monthly>
sub _opt_range {
	my ($self, $opt, $value) = @_;

	if (lc $value !~ /^(dai|week|month)ly$/) {
		pod2usage({ -input => \*DATA, -verbose => 1 });
		exit 1;
	}
	$self->{param}{$value} = 1;
}

1;

__DATA__

=head1 NAME

PsychoStats - Comprehensive Statistics

Awards generator for Psychostats

*** HELP CONTENT NOT COMPLETED YET ***

=head1 SYNOPSIS

=over 4 

=item B<Do something useful...>

=over 4 

=item awards.pl -xxx <...> [...]

=back

=back

=head1 COMMANDS

=over 4

=item B<-xxx> <...> [...]

...
