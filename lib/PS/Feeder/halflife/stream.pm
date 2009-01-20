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
package PS::Feeder::halflife::stream;

use strict;
use warnings;
use base qw( PS::Feeder::stream );

our $VERSION = '4.00.' . (('$Rev$' =~ /(\d+)/)[0] || '000');

# normalizes the incoming packet by stripping off any headers, etc and acting on
# the contents if needed. This is for halflife 1 or 2 log streams.
sub normalize_line {
	my ($self, $line) = @_;
	my ($head);
	$head  = substr($line,0,5,'');					# "....R" (hl2) or "....l" (hl1)
	$head .= substr($line,0,3,'') if substr($head,-1) eq 'l';	# HL1 (remove extra 'og.')
	$line  = substr($line,0,-1);					# remove trailing NULL byte

	# don't chomp it, this way we can detect if a line is complete or not.
	#chomp($line);

	# lets keep the counter accurate, since we're removing a few bytes.
	# this is used in bytes_per_second()
	$self->{_totalbytes} += length($head);

	# keep track of the current log name
	if ($line =~ /^L .......... - ..:..:..: Log file started/) {
		if ($line =~ /file ".*(L\d+\.log)"/) {
			$self->{_curlog} = $1;
		}
		# set line to 0, the caller will increment this by one.
		$self->{_curline} = 0;
	}
	return $line;
}

1;