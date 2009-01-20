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
package PS::Conf::conftype;

use strict;
use warnings;

use Carp;
use PS::Conf::section;

our $VERSION = '4.00.' . (('$Rev$' =~ /(\d+)/)[0] || '000');
our $AUTOLOAD;

sub new {
	my $proto = shift;
	my $conftype = shift;
	my $list = shift;
	my $class = ref($proto) || $proto;
	my $self = { VARS => {}, conftype => $conftype };
	bless($self, $class);
	
	# add methods for each section
	foreach my $c (@$list) {
		my ($section, $var, $value) = @$c;
		if (defined $section and $section ne '') {
			no strict "refs";
			# add a section method only once...
			if (!$self->can($section)) {
				# create an object for this conftype
				my $o = new PS::Conf::section();
				*{"$section"} = sub () { $o };
			}
			
			# add the variable to the section
			# $s is created as an alias so perl can use the proper
			# reference.
			my $s = $self->$section;
			$s->_var($var, $value);
		} else {
			# add the variable to the object
			$self->_var($var, $value);
		}
	}
	
	return $self;
}

# return a hash of conf vars in this list
sub VARS { $_[0]->{VARS} }

# creates a method for $var that returns the $value
sub _var {
	my ($self, $var, $value) = @_;
	return if !defined $var or $self->can($var);
	$self->{VARS}{$var} = $value;
	if (defined $value) {
		$value =~ s/'/\\'/g;
		eval "sub $var () { '$value' }";
	} else {
		eval "sub $var () { undef }";
	}
	return $value;
}

# catch any references to unknown config variables.
# create the variable so AUTOLOAD is only called once.
sub AUTOLOAD {
	my $self = ref($_[0]) =~ /::/ ? shift : undef;
	my $var = $AUTOLOAD;
	$var =~ s/.*:://;
	return if $var eq 'DESTROY';

	carp("Warning: Unknown config section or variable ($var) used");

	return $self->_var($var, undef);
}

1;