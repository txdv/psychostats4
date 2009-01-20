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
package PS::Conf::section;

use strict;
use warnings;

use Carp;

our $VERSION = '4.00.' . (('$Rev$' =~ /(\d+)/)[0] || '000');
our $AUTOLOAD;

sub new {
	my $proto = shift;
	return bless({ VARS => {} }, ref($proto) || $proto);	
}

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

# return a hash of conf vars in this list
sub VARS { $_[0]->{VARS} }

sub AUTOLOAD {
	my $self = ref($_[0]) =~ /::/ ? shift : undef;
	my $var = $AUTOLOAD;
	$var =~ s/.*:://;
	return if $var eq 'DESTROY';

	carp("Warning: Unknown config variable ($var) used");

	return $self->_var($var, undef);
}

1;