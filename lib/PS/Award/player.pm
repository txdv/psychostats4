package PS::Award::player;

use base qw( PS::Award );
use strict;
use warnings;

use Data::Dumper;
use POSIX qw( strftime );
use util qw( :date :time :strings );

sub init_award {
	my $self = shift;
	# do something useful here, if needed ...
	return $self;
}

sub calc { 
	my $self = shift;
	my $range = lc shift;						# 'month', 'week' or 'day'
	my $dates = $self->valid_dates($range, ref $_[0] ? shift : [ @_ ]);
	my $db = $self->{db};
	my $conf = $self->{conf};
	my $a = $self->{award};
	my $gametype = $conf->get_main('gametype');
	my $modtype = $conf->get_main('modtype');
	my $allowpartial = $range ne 'day' ? $conf->get_main("awards.allow_partial_$range") : 1;
	my $tail = $gametype && $modtype ? "_${gametype}_$modtype" : $gametype ? "_$gametype" : "";
	my @mainkeys = keys %{$db->tableinfo($db->{t_plr_data})};
	my @modkeys = $db->table_exists($db->{t_plr_data} . $tail) ? keys %{$db->tableinfo($db->{t_plr_data} . $tail)} : ();
	my ($cmd, $fields);

	my ($newest) = $db->get_row_array("SELECT MAX(statdate) FROM $db->{t_plr_data}");

	$fields = { 
		(map { $_ => "SUM(data.$_)" } @mainkeys), 
		(@modkeys ? map { $_ => "SUM(mdata.$_)" } @modkeys : ()),
		skill => "AVG(plr.skill)",
		dayskill => "AVG(data.dayskill)",
		dayrank => "AVG(data.dayrank)",
		lasttime => "MAX(data.lasttime)",
	};
	delete @$fields{ qw( dataid plrid statdate ) };
#	print Dumper $fields;

	foreach my $timestamp (@$dates) {
		my $start = strftime("%Y-%m-%d", localtime($timestamp));
		my $end = $self->end_date($range, $start);
		my $expr = simple_interpolate($a->{expr}, $fields);
		my $where = simple_interpolate($a->{where}, $fields);
		my $order = $a->{order} || 'desc';
		my $limit = $a->{limit} || '10';
		my $complete = ($end lt $newest) ? 1 : 0;
		# I use 'less then' (instead of 'less then or equal to' for $complete above so that
		# the award is only marked completed if the newest date is the next day. Otherwise, awards
		# would be marked completed early in the morning and would not reflect any stats from later
		# in the day if awards were updated again on that day.

		next if (!$complete and !$allowpartial);

		$cmd  = "SELECT $expr value, plr.plrid ";
		$cmd .= "FROM ($db->{t_plr_data} data, $db->{t_plr} plr) ";
		$cmd .= "LEFT JOIN $db->{t_plr_data}$tail mdata ON mdata.dataid=data.dataid " if @modkeys;
		$cmd .= "WHERE plr.plrid=data.plrid ";
		$cmd .= "AND plr.allowrank ";
		$cmd .= "AND (statdate BETWEEN '$start' AND '$end') ";
		$cmd .= "GROUP BY data.plrid ";
		# must use 'having' and not 'where', since we're using expressions
		$cmd .= "HAVING $where " if $a->{where};
		$cmd .= "ORDER BY 1 $order ";
		$cmd .= "LIMIT $limit ";
#		print "$cmd\n";

		$::ERR->verbose("Calc " . ($complete ? 'complete' : 'partial ') . 
			" $a->{type} award on " . sprintf("%-5s",$range) . " $start for '$a->{name}'");
		my $plrs = $db->get_rows_hash($cmd) || next;
#		next unless @$plrs;

		# if all players have a 0 value ignore the award
		my $total = 0;
		$total += abs($_->{value} || 0) for @$plrs;
#		next unless $total;
		$plrs = [] unless $total;

		$db->begin;
		my $id = $db->select($db->{t_awards}, 'id', [ awardid => $a->{id}, awarddate => $start, awardrange => $range ]);
		if ($id) {
			$db->delete($db->{t_awards}, [ id => $id ]);
			$db->delete($db->{t_awards_plrs}, [ awardid => $id ]);
		}

		$id = $db->next_id($db->{t_awards});
		my $award = {
			id		=> $id,
			awardid		=> $a->{id},
			awardtype	=> 'player',
			awardname	=> $a->{name},
			awarddate	=> $start,
			awardrange	=> $range,
			awardcomplete	=> $complete,
			topplrid	=> @$plrs ? $plrs->[0]{plrid} : 0,
			topplrvalue	=> @$plrs ? $plrs->[0]{value} : 0
		};
		$db->insert($db->{t_awards}, $award);

		my $idx = 0;
		foreach my $p (@$plrs) {
			next unless $p->{value};
			$db->insert($db->{t_awards_plrs}, {
				id	=> $db->next_id($db->{t_awards_plrs}),
				idx	=> ++$idx,
				awardid	=> $id,
				plrid	=> $p->{plrid},
				value	=> $p->{value}
			});
		}

		$db->commit;
	}

}

1;
