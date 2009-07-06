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
package PS::CmdLine;

use strict;
use warnings;
use base qw( PS::Core );

use Carp;
use Getopt::Long;
use Pod::Usage;
use util qw( :win );

our $VERSION = '4.00.' . (('$Rev$' =~ /(\d+)/) || '000')[0];
our @OPTS = ();

our $SINGLETON = {};

sub new {
	my ($class, $skip_constant) = @_;
	return $SINGLETON->{$class} if exists $SINGLETON->{$class};
	
	my $self = { debug => 0, class => $class };
	$self->{param} = {};
	bless($self, $class);
	$SINGLETON->{$class} = $self;
	
	# alias debug since the -debug paramater will override the inherited
	# debug() function from PS::Core.
	*_debug = \&PS::Core::debug;

	Getopt::Long::Configure(qw( no_ignore_case auto_abbrev ));

	# don't let Getopt::Long clobber the ARGV array
	my @oldARGV = @main::ARGV;
	$self->_getOptions;
	@main::ARGV = @oldARGV;
	$self->_sanitize;
	$self->_make_constant unless $skip_constant;

	return $self;
}

# private: Loads command line parameters
sub _getOptions {
	my $self = shift;

	my $optok = GetOptions(
		# CONFIG OVERRIDES
		'uniqueid=s'	=> \$self->{param}{uniqueid},
		'maxdays=s'	=> \$self->{param}{maxdays},
		'baseskill=i'	=> \$self->{param}{baseskill},
		'skill=s'	=> \$self->{param}{calcskill_kill},

		# BASIC OPTIONS
		'config=s'	=> \$self->{param}{config},
		'noconfig'	=> \$self->{param}{noconfig},
		'help|?'	=> \$self->{param}{help},
		'gametype=s'	=> \$self->{param}{gametype},
		'modtype=s'	=> \$self->{param}{modtype},
		'reset:s'	=> \$self->{param}{reset},
		'unknown'	=> \$self->{param}{report_unknown},
		'v|verbose'	=> \$self->{param}{verbose},
		'V|ver|version'	=> \$self->{param}{version},
		'quiet'		=> \$self->{param}{quiet},

		# PERFORMANCE OPTIONS
		'lps=f'		=> \$self->{param}{lps},	# Lines per second maximum processing speed

		# DEBUGGING OPTIONS
		'd|debug:i'	=> \$self->{param}{debug},	# ENV{PSYCHOSTATS_DEBUG} replaces this
		'debugfile:s'	=> \$self->{param}{debugfile},
		'dumpevents'	=> \$self->{param}{dumpevents},
		'dumpbonuses'	=> \$self->{param}{dumpbonuses},

		# LOG OPTIONS
		'logsource=s'	=> \$self->{param}{logsource},
		'nologsource'	=> \$self->{param}{nologs},
		'files'		=> \$self->{param}{files},	# only process non-stream sources
		'streams'	=> \$self->{param}{streams},	# only process stream sources
		'passive|pasv'	=> \$self->{param}{passive},	# FTP logsource's
		'maxlogs=s'	=> \$self->{param}{maxlogs},	# maximum logs to process before exiting
		'maxlines=s'	=> \$self->{param}{maxlines},	# maximum lines ...
		'ipaddr=s'	=> \$self->{param}{ipaddr},	# Stream logsource's (bind ip)
		'port=i'	=> \$self->{param}{port},	# Stream logsources' (bind port)
		'echo'		=> \$self->{param}{echo},	# Stream logsource's
		'force'		=> \$self->{param}{force},	# ignore invalid previous states

		# DAEMON OPTIONS
		'daemon'	=> \$self->{param}{daemon},

		# DAILY OPTIONS
		'update=s'	=> \$self->{param}{update},
		#'daily:s'	=> \$self->{param}{daily},
		#'nodaily'	=> \$self->{param}{nodaily},

		# AWARD OPTIONS
		'award:s'	=> \$self->{param}{award},
		'start|day=s'	=> \$self->{param}{start},
		'end=s'		=> \$self->{param}{end},

		# CLAN OPTIONS
		'scanclantags|clantags:s' => \$self->{param}{scanclantags},	# SCAN clans; matching players to clantags
#		'deleteclans'	=> \$self->{param}{deleteclans},	# DELETE clans; plr.clanid=0 (profiles remain intact)

		# DATABASE OPTIONS
		'dbinit'	=> \$self->{param}{dbinit},
		'dbtype=s'	=> \$self->{param}{dbtype},
		'dbhost=s'	=> \$self->{param}{dbhost},
		'dbport=s'	=> \$self->{param}{dbport},
		'dbname=s'	=> \$self->{param}{dbname},
		'dbuser=s'	=> \$self->{param}{dbuser},
		'dbpass=s'	=> \$self->{param}{dbpass},
		'dbtblprefix:s'	=> \$self->{param}{dbtblprefix},
		'dbcompress=i'	=> \$self->{param}{dbcompress},

		# grab extra params that are not options
		'<>'		=> sub { push(@PS::CmdLine::OPTS, shift) }
	);

	# default verbose to on if we're under windows
	if (iswindows and !$self->{param}{verbose}) {
		$self->{param}{verbose} = 1;
	}

	if (!$optok) {
#		die("Invalid parameters given. Insert help page");
		pod2usage({ -input => \*DATA, -verbose => 1 });
	}

	if ($self->{param}{help}) {
		pod2usage({ -input => \*DATA, -verbose => 2 });
	}

}

# private: Cleans up (sanitizes) the options loaded from the command line.
sub _sanitize {
	my $self = shift;

	# Debugging must be enabled using the ENV{PSYCHOSTATS_DEBUG} variable.
	# Warn the user to inform them of this if -debug is used.
	if (defined $self->{param}{debug}) {
		warn "Depreciated paramater ignored (-debug).\nSet the " . 
			"environment variable PSYCHOSTATS_DEBUG to the debug " . 
			"level desired (1-9).\n";
	}

	# lowercase the following
	my @list = qw( daily gametype modtype scanclantags );
	foreach (@list) {
		next unless defined $self->{param}{$_};
		$self->{param}{$_} = lc $self->{param}{$_};
	}
	
	# a dash means a 'blank' modtype
	if ($self->{param}{modtype} and $self->{param}{modtype} eq '-') {
		$self->{param}{modtype} = '';
	}

	# if daily is specified but it's blank or 0 default it to "all"
	if (defined $self->{param}{daily} and !$self->{param}{daily}) {
		$self->{param}{daily} = 'all';
	}

	# if update is specified but it's blank or 0 default it to "all"
	if (defined $self->{param}{update} and !$self->{param}{update}) {
		$self->{param}{update} = 'all';
	}

	if (defined $self->{param}{scanclantags}) {
		$self->{param}{scanclantags} = 1 if $self->{param}{scanclantags} ne 'all';
	}
}

# "functionizes" each paramater as a constant sub-routine for easy/fast access.
# The paramater functions are optimized to constants by the perl pre-processor.
# For this reason the SETTER method is separate since most options are never
# changed once loaded.
# Benchmark results: A function constant is roughly 700% faster than using
# ->get() and 400% faster than accessing the ->{param}{...} hash directly.
# WARNING: The only problem with this is you can not use set() and then use
# a constant sub to get the new value. You have to use get() after any calls
# to set().
sub _make_constant {
	my ($self) = @_;
	my $code = '';
	foreach my $key (keys %{$self->{param}}) {
		my $val = 'undef';
		if (defined $self->{param}{$key}) {
			$val = $self->{param}{$key};
			if ($val !~ /^\d$/) {
				# sanitize the string (quotes and slashes)
				$val =~ s/\\/\\\\/g;
				$val =~ s/'/\\'/g;
				$val = "'$val'";
			}
		}
		
		# GETTER (eval'ed into a constant sub-routine)
		$code .= "sub $key () { $val }\n";

		# non-constant GETTER
		$code .= "sub get_$key { \$_[0]->{param}{$key} }\n";
		
		# non-constant SETTER
		$code .= "sub set_$key { \$_[0]->{param}{$key} = \$_[1] }\n";
	}
	#print $code;
	eval $code;
	return $self;
}

# returns true if the parameter given actually exists
sub exists {
	my $self = shift;
	my $var = shift;
	return (exists $self->{param}{$var} && defined $self->{param}{$var});
#	my $undefexists = shift;			# defaults to false
#	my $exists = exists $self->{param}{$var};	# true, even if the value is undef
#	if (!$undefexists and $exists and !defined $self->{param}{$var}) {
#		return;
#	}
#	return $exists;
}

# sets the paramater, and returns the previous value
sub set {
	my ($self, $var, $val) = @_;
	my $old = $self->{param}{$var};
	$self->{param}{$var} = $val;
	return $old;
}

sub get {
	my $self = shift;
	my $var = shift;

	return unless exists $self->{param}{$var};
	return $self->{param}{$var};
}

# deletes a paramter from the known list (exists will now return false if called on the same var)
sub del {
	my ($self, $var) = @_;
	return delete $self->{param}{$var};
}

sub pop_opt { return pop @OPTS }
sub shift_opt { return shift @OPTS }
sub OPTIONS { return @OPTS }

our $AUTOLOAD;
sub AUTOLOAD {
	my $self = ref($_[0]) =~ /::/ ? shift : undef;
	my $var = $AUTOLOAD;
	$var =~ s/.*:://;
	return if $var eq 'DESTROY';

	# Define a function for this option since it doesn't exist. This will
	# prevent AUTOLOAD from being called again for the same option.
	eval "sub $var () { undef }";
	#goto &$var;
	return;
}

1;

__DATA__

=head1 NAME

PsychoStats - Comprehensive Statistics

=head1 SYNOPSIS

stats.pl [options]

stats.pl -daily [daily options]

=head1 OPTIONS

=over 4

=item B<-config> <filename>, -noconfig

Specifies an alternate filename to load the required database settings 
for PsychoStats. By default the stats.cfg file is loaded.

=item B<-noconfig>

Disables the loading of any database config file. When -noconfig is used it
is assumed that the actual database connection settings are specified using 
the -db* command line options explained below.

=item B<-daily> [all,maxdays,decay,players,clans,ranks,awards]

The daily process performs several intensive stats updates that can not be
done in 'real-time'. This includes calculating player ranks, removing stale
clans, applying skill decay to players and removing old stats that are older
than the current maxdays setting.

=item B<-dbcompress> [0,1]

Enables or disables client to server compression when talking to the mysql
server. This should usually be disabled for databases that are local but can
be enabled for remote database for a possible performance gain. The trade off
is while compression will generally decrease the amount of data transfered
between the server, more CPU is required by both sides and may hinder
performance.

=item B<-dbtype> [mysql]

The database engine type to use. 'mysql' is the default.
'mysql' is actually the only working option.

=item B<-dbhost>

The hostname or IP address of the database to connect to. 
'localhost' is the default.

=item B<-dbport>

The port to connect to. Defaults to the standard port of the 
database type specified. Ex: 3306 for mysql.

=item B<-dbname>

Name of the database to use. Defaults to 'psychostats'. 
This is not to be confused with the host of the database server. 
The 'name' is a database within the server where the tables are stored.

=item B<-dbuser>

The username to connect to the database server. Defaults to NULL.

=item B<-dbpass>

The password to connect to the database server. Defaults to NULL.

=item B<-dbtblprefix>

The table prefix string to use for all tables within the PsychoStats
database. Defaults to 'ps_'. 

=item B<-debugfile> <filename>

All debug output is written to a debug.txt file in the current directory.
If a filename is specified its name is used instead. The environment variable
PSYCHOSTATS_DEBUG must be set in order for any debug output to be logged.

=item B<-echo>

B<For STREAM log sources only.> The stream feeder will echo all log events
to STDOUT.

=item B<-gametype> <type>

Processes logs using the specified GAME type. IE: halflife, cod. Using this
option has a side effect of actually changing the value in your database
configuration permanently. Most users will not have to use this. The update
will fail if an unknown gametype is specified. See also: B<-modtype>

=item B<-help>

If you do not know what this does by now, go seek help, YOU NEED IT!

=item B<-logsource> <path>

Process the log source given. Will ignore all log sources currently
configured in the database. This does not add the logsource to your 
configuration permanently.

=item B<-maxlines> <number>

Specifies the maximum number of log lines to process before exiting. Normally
you never need this but on some systems that have system resource restraints
using this can help limit resource usage. By default all available lines will
be processed.

=item B<-maxlogs> <number>

Specifies the maximum number of logs to process before exiting, see -maxlines
for more information.

=item B<-modtype> <type>

Processes logs using the specified MOD type. IE: cstrike, dod. Using this
option has a side effect of actually changing the value in your database
configuration permanently. Most users will not have to use this. The update
will fail if an unknown modtype is specified. See also: B<-gametype>

=item B<-nodaily>

Skips all daily processing procedures after logs are processed.

=item B<-passive, -pasv>

Enables PASSIVE mode for FTP logsources. This provides a quick way to toggle
passive mode on or off w/o having to edit the configuration first. Once you
figure out which method works you can edit the config to make it permanent.

=item B<-reset> [all,players,clans,weapons]

Resets all stats in your database. If 'players', 'clans' or 'weapons; is 
specified then the profiles for each will also be reset. 
By default profiles and weapons are saved.

B<*** IMPORTANT ***> This can NOT be undone! Use at your own risk.

=item B<-scanclantags> [all]

Rescans all players currently not associated with a clan for matching clantags.
Run this if you just configured a new clantag. Otherwise players that match will
not actually assoicate with the new clantag until they reconnect to the server.

If "all" is specified then ALL PLAYERS will be scanned regardless if they are 
already in a clan. This is usefull if player names have changed and they need 
to be matched against a new clan instead of their original clan.
NOTE: this will cause all current clans to be rescanned. Your clans listing will 
be emptied and repopulated.

This forces a "-daily clans" update.

=item B<-unknown>

Temporarily enables the errlog.report_unknown option from the config. This does
not update the database and only lasts for the current process.

=item B<-verbose>

Enable stats progress output. As stats are updated the progress is displayed.
If running under Windows verbose defaults to ON. Use -quiet to turn it off.

=item B<-V>, B<-version>

Display software version

=item B<-quiet>

Overrides the verbose option and disables it if verbose was already enabled.

=back

=head1 DESCRIPTION

B<PsychoStats> is awesome!

=cut

