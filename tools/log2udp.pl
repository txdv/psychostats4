#!/usr/bin/perl
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
#	Example of usage:
#	./log2udp.pl 	-local localhost:27016 \	# bind to local ip:port
#			-port 28001 \			# send to remote port
#			-path /path/to/logs \		# path to read logs from
#			-kbps 30			# throttle stream speed
#
BEGIN { # FindBin isn't going to work on systems that run the stats.pl as SETUID
	use strict;
	use warnings;

	use FindBin; 
	use lib ( $FindBin::Bin, $FindBin::Bin . "/lib", $FindBin::Bin . "/../lib" );
}

our $VERSION = '4.00.' . (('$Rev$' =~ /(\d+)/) || '000')[0];
our $GRACEFUL_EXIT = 0;

use util qw( abbrnum commify );
use PS::Feeder;
use Getopt::Long;
use IO::Socket::INET;
use FileHandle;
use Time::HiRes qw( time usleep );	# very important for BPS throttling

my $opt = {};
GetOptions(
	'path=s'	=> \$opt->{path},	# log path to read from
	'ip|ipaddr=s'	=> \$opt->{ip},		# IP for outgoing stream
	'port=s'	=> \$opt->{port},	# Port for outgoing stream
	'localaddr=s'	=> \$opt->{localaddr}, 	# Local addr:port to bind to
	'delay=f'	=> \$opt->{delay},	# interval delay between events
	'kbps|bps=f'	=> \$opt->{kbps},	# maximum BPS allowed
	'lps=f'		=> \$opt->{lps},	# maximum LPS allowed
	'maxlines=i'	=> \$opt->{maxlines},	# max lines to stream
	'file=s'	=> \$opt->{file},	# output file for testing
	'gametype=s'	=> \$opt->{gametype},
	'modtype=s'	=> \$opt->{modtype},
);

#$opt->{path} ||= '-';		# read from STDIN by default
$opt->{ip} ||= '127.0.0.1'; 	# send to localhost by default
$opt->{port} ||= 28000;
$opt->{delay} = 0.0001 unless defined $opt->{delay};
$opt->{kbps} ||= 0;
$opt->{lps} ||= 0;
$opt->{gametype} ||= 'halflife';
$opt->{modtype} ||= '';

if (!$opt->{path}) {
	die "A valid -path <dir> must be specified to read logs from.\n";
}

my $logsource = {
	type		=> 'file',
	gametype	=> $opt->{gametype},
	modtype		=> $opt->{modtype},
	tz		=> $opt->{tz},
	path		=> $opt->{path},
	recursive	=> $opt->{recursive},
	depth		=> $opt->{depth},
};
my $feed = new PS::Feeder($logsource);
if ($feed->error) {
	die "Error creating feeder: " . $feed->error . "\n";
}
if (!$feed->init) {
	die "Error initialing feeder: " . $feed->error . "\n";
}

my $sock = new IO::Socket::INET(
	LocalAddr	=> $opt->{localaddr},
	PeerAddr	=> $opt->{ip},
	PeerPort	=> $opt->{port},
	Proto		=> 'udp',
) or die "Error creating socket on $opt->{ip}:$opt->{port}: $!";

my $lastprint = time;
my $lasttimebytes = time;
my $lasttimelines = time;
my $totalbytes = 0;
my $totallines = 0;
my $prevbytes = 0;
my $prevlines = 0;
my $bps = 0;
my $max_bps = 1024 * $opt->{kbps};
my $max_lps = $opt->{lps};
#my $delay = $opt->{kbps} ? 0 : $opt->{delay} * 1_000_000;	# millionths of a second
my $fh;

if ($opt->{file}) {
	$fh = new FileHandle('>' . $opt->{file});
	if (!defined $fh) {
		die "Error opening output file for streamed data: $!";
	}
	$fh->autoflush(1);
}

#warn "Delay is set to $opt->{delay} ($delay usecs)\n" if $delay;
$| = 1;	# disable output buffering

# push all log events to the UDP socket
my $line;
while (1) {
	if ((!$opt->{kbps} or $bps < $max_bps) and (!$opt->{lps} or $lps < $max_lps)) {
		$line = $feed->next_event || last;
		event($sock, $line);
		print $fh $line if $fh;
	} else {
		# yield; so we don't eat 100% CPU
		usleep(0);
	}

	#usleep($delay) if $delay;
	
	$bps = bps();
	$lps = lps();
	if (time - $lastprint >= 1.0) {
		my $str = abbrnum($bps,2,1024,[' bps', ' kbps', ' mbps']) .
			" / $lps lps" . 
			" (" . commify($totallines) . " lines)";
		print "  \r", " " x length($str), "\r", $str;
		$lastprint = time;
	}
	last if $opt->{maxlines} and $totallines >= $opt->{maxlines};
}

END {
	$fh->close if $fh;
}

sub event {
	my ($sock, $line) = @_;
	my $event = pack('N', 0) . 'R' . $line . pack('x');
	$totallines++;
	$totalbytes += length($event);
	$sock->send($event);
}

# calculate how fast we're sending events (BPS)
sub bps {
	my ($self) = @_;
	return undef unless defined $lasttimebytes;
	my $time_diff = time - $lasttimebytes;
	my $byte_diff = $totalbytes - $prevbytes;
	my $total = $time_diff ? sprintf("%.0f", $byte_diff / $time_diff) : $byte_diff;
	#$prevbytes = $totalbytes;
	#$lasttimebytes = time;
	return $total;
}

sub lps {
	my ($self) = @_;
	return undef unless defined $lasttimelines;
	my $time_diff = time - $lasttimelines;
	my $line_diff = $totallines - $prevlines;
	my $total = $time_diff ? sprintf("%.0f", $line_diff / $time_diff) : $line_diff;
	#$prevlines = $totallines;
	#$lasttimelines = time;
	return $total;
}