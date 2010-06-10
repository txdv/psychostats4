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
package PS::Award::Player::Basic;

use base qw( PS::Award::Player );
use strict;
use warnings;

our $VERSION = '1.00.' . (('$Rev$' =~ /(\d+)/)[0] || '000');

sub _init {
	my ($self) = @_;
	$self = $self->SUPER::_init;
	return $self;
}

sub calc {
	my ($self) = @_;
	my ($cmd, $tail);

	if ($self->{gametype} and $self->{modtype}) {
		$tail = '_' . $self->{gametype} . '_' . $self->{modtype};
	}
	
	my $expr = $self->interpolate($self->{expr});
	if (index($expr, '{') != -1) {
		$@ = "Invalid expression (might be pointing to an invalid column)";
		return;
	}

	$cmd  = qq{
		SELECT
			$expr value,
			plr.plrid, plr.uniqueid, plr.activity, plr.skill,
			plr.skill_prev, plr.rank, plr.rank_prev,
			pp.name
		FROM ($self->{db}{t_plr_data} data, $self->{db}{t_plr} plr)
		LEFT JOIN $self->{db}{t_plr_profile} pp ON pp.uniqueid=plr.uniqueid
	};
	
	# join game/mod custom table if its available
	if ($tail and $self->{db}->table_exists($self->{db}{t_plr_data} . $tail)) {
		$cmd .= qq{
		LEFT JOIN $self->{db}{t_plr_data}$tail mdata ON mdata.dataid=data.dataid
		};
	}

	# join plr table with data
	$cmd .= "WHERE plr.plrid=data.plrid ";

	if ($self->{gametype} and $self->{modtype}) {
		$cmd .= "AND plr.gametype=" . $self->{db}->quote($self->{gametype});
		$cmd .= " AND plr.modtype=" . $self->{db}->quote($self->{modtype});
		$cmd .= " ";
	} elsif ($self->{gametype}) {
		$cmd .= "AND plr.gametype=" . $self->{db}->quote($self->{gametype});
		$cmd .= " ";
	}
	
	# limit based on player ranks
	if ($self->{max_rank}) {
		$cmd .= " AND plr.rank < $self->{max_rank} ";
	}
	if ($self->{ranked_only}) {
		$cmd .= " AND plr.rank IS NOT NULL ";
	}
	
	# limit on date range and group by plrid
	$cmd .= qq{
		AND (statdate BETWEEN '$self->{start_date}' AND '$self->{end_date}')
		GROUP BY data.plrid
	};

	if ($self->{where} or defined $self->{min_value}) {
		# must use 'having' and not 'where', since we're using expressions
		$cmd .= "HAVING ";
	}

	if ($self->{where}) {
		$cmd .= "$self->{where} ";
	}

	if (defined $self->{min_value}) {
		$cmd .= " AND " if $self->{where};
		$cmd .= "$expr >= $self->{min_value} ";
	}
	
	# sort by the expression ...
	# using a numerical index for sorting is mysql specific (I think)
	$cmd .= " ORDER BY 1 " . ($self->{order} || '');
	$cmd .= " LIMIT $self->{limit}" if $self->{limit};
	$self->debug($cmd, 3);

	my $list = $self->{db}->get_rows_hash($cmd);
	return $list;
}

1;
