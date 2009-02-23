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
package PS::Weapon::halflife::tf;

use strict;
use warnings;

use base qw( PS::Weapon::halflife );

our $VERSION = '4.00.' . (('$Rev$' =~ /(\d+)/)[0] || '000');

BEGIN {
	my $fields = __PACKAGE__->SUPER::FIELDS('DATA');
	%{$fields->{halflife_tf}} = (
		(map { $_ => '+' } qw(
			custom_kills
			backstab_kills
		 ))
	);
}

# a player killed someone
sub action_kill {
	my $self = shift;
	my ($game, $killer, $victim, $map, $props) = @_;
	$self->SUPER::action_kill(@_);
	
	return unless exists $props->{customkill};
	
	# track custom kills
	my @customs = ( 'custom_kills', $props->{customkill} . '_kills' );
	for (@customs) {
		$self->{data}{$_}++;
	}
}

1;
