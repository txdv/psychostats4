# Award plugin template. 
# Use this as a starting point for any new award class.
package PS::Award::award_type;	# change "award_type" to the basename of the file

# always have these 3 lines
use base qw( PS::Award );
use strict;
use warnings;

# add/remove modules needed for your code
use Data::Dumper;
use POSIX qw( strftime );
use util qw( :date :time :strings );

# ->init_award is called right after the object is created and before any calculations are done.
# ALWAYS return a reference to our object.
sub init_award {
	my $self = shift;
	# do something useful here, if needed ...
	return $self;
}

# ->calc is called to actually perform the award calculations
# This is where most of your code and processing happens.
sub calc { 
	my $self = shift;
	my $range = shift;	# 'month', 'week' or 'day'
	my $dates = ref $_[0] ? shift : [ @_ ];
	# ...
}


# always return a true value at the end of the file
1;
