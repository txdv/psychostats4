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
#       'use' this package in any file that wants special debugging statements
#       removed. Any line that starts with 3 semicolons are removed from the
#       source code before Perl compiles it. This allows me to riddle
# 	my code with debug statements w/o worring about those statements
# 	affecting performance (tons of function calls, or logic expressions).
#
#	If the environment variable PSYCHOSTATS_DEBUG is defined the debug
# 	statements will NOT be removed from the code.
package PS::SourceFilter;

use strict;
use Filter::Simple;

BEGIN {
        Filter::Simple::FILTER {
		unless ($ENV{PSYCHOSTATS_DEBUG}) {
			# remove any line that starts with 3 semicolons or
			# looks like a debug call. WARNING: This assumes all
			# $self->debug() calls are a single line, if its on
			# multiple lines then this will break the code.
			s/^\s*(?:;;;|\$self->debug).*$//gm;

			#s/^\s*;;;.+$//gm;
			#s/^\s*\$self->debug.+$//gm;
		}
	}
}

1;