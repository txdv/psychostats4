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
package PS::Role::halflife::tf;

use strict;
use warnings;
use base qw( PS::Role );	# NOTE: NOT SUBCLASSING 'halflife'

our $VERSION = '4.00.' . (('$Rev$' =~ /(\d+)/)[0] || '000');

BEGIN {
	my $fields = __PACKAGE__->SUPER::FIELDS('DATA');
	%{$fields->{halflife_tf}} = (
		(map { $_ => '+' } qw(
			assisted_kills
			dominations
			custom_kills			custom_deaths
			backstab_kills			backstab_deaths
			destroyed_objects		built_objects
			destroyed_dispenser		built_dispenser
			destroyed_sentrygun		built_sentrygun
			destroyed_attachment_sapper 	built_attachment_sapper
			destroyed_teleporter_entrance 	built_teleporter_entrance
			destroyed_teleporter_exit	built_teleporter_exit
		 ))
	);
}

# A player created an object (sentry guns, dispensers, etc)
sub action_created_object {
	my ($self, $game, $object, $owner, $props) = @_;
	my @vars = ( 'built_' . $object, 'built_objects' );
	$self->timestamp($props->{timestamp});

	$self->{data}{$_}++ for @vars;
}

sub action_destroyed_object {
	my ($self, $game, $object, $plr, $owner, $weapon, $props) = @_;
	#my $w = $weapon ? $weapon->id : undef;
	#my $v = $owner ? $owner->id : undef;
	my @vars = ( 'destroyed_' . $object, 'destroyed_objects' );
	$self->timestamp($props->{timestamp});

	$self->{data}{$_}++ for @vars;
}

# a player role was killed by someone
sub action_death {
	my $self = shift;
	my ($game, $victim, $killer, $weapon, $map, $props) = @_;

	# allow parent to do its thing...
	$self->SUPER::action_death(@_);

	return unless $props->{customkill};
	
	$self->{data}{custom_deaths}++;
	$self->{data}{$props->{customkill} . '_deaths'}++;
}

# a player role killed someone
sub action_kill {
	my $self = shift;
	my ($game, $killer, $victim, $weapon, $map, $props) = @_;

	# allow parent to do its thing...
	$self->SUPER::action_kill(@_);
	
	return unless $props->{customkill};

	# track the custom kill stat	
	$self->{data}{custom_kills}++;
	$self->{data}{$props->{customkill} . '_kills'}++;
}

sub action_kill_assist {
	my ($self, $game, $victim, $weapon, $map, $props) = @_;

	# Treat an assist the same as a regular kill
	$self->action_kill(@_);
	$self->{data}{assisted_kills}++;

}

1;