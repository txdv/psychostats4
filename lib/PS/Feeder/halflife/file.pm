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
package PS::Feeder::halflife::file;

use strict;
use warnings;
use base qw( PS::Feeder::file );

our $VERSION = '4.00.' . (('$Rev$' =~ /(\d+)/)[0] || '000');

# sorting method to sort a list of log filenames. returns a NEW array reference
# of the sorted logs. Does not change original reference.
sub logsort {
	my $self = shift;
	my $list = shift;		# array ref to a list of log filenames
	#my %uniq;
	#use util;
	#for (@$list) {
	#	my $key = substr($_, 0, 3);
	#	$uniq{$key} = $_ if !exists $uniq{$key} || $_ lt $uniq{$key};
	#}
	#print_r(\%uniq);
	#use util; print_r([ sort { $self->logcompare($a, $b) } @$list ]);
	return [ sort { $self->logcompare($a, $b) } @$list ];
}

# compare method that can compare 2 log files for the game and return (-1,0,1)
# depending on their order smart logic tries to account for logs from a previous
# year as being < instead of > this year
sub logcompare { 
	my ($self, $x, $y) = @_; 

	#return lc $x cmp lc $y; 

	# Fast path -- $a and $b are in the same month 
	if ( substr($x, 0, 3) eq substr($y, 0, 3) ) { 
		return lc $x cmp lc $y; 
	} 

	# Slow path -- handle year wrapping. localtime returns the month offset
	# by 1 so we add 2 to get the NEXT month
	my $month = (localtime)[4] + 2;

	return ( 
		substr($x, 1, 2) <= $month <=> substr($y, 1, 2) <= $month 
		or 
		lc $x cmp lc $y 
	); 
}

1;