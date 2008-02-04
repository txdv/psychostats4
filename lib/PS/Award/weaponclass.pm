package PS::Award::weaponclass;

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
	my $range = lc shift;				# 'month', 'week' or 'day'
	my $dates = $self->valid_dates($range, ref $_[0] ? shift : [ @_ ]);
	my $db = $self->{db};
	my $conf = $self->{conf};
	my $a = $self->{award};
	my $classes = $db->get_list("SELECT distinct class FROM $db->{t_weapon} WHERE class != '' ORDER BY class");
	my $allowpartial = $conf->get_main("awards.allow_partial_$range");
	my ($cmd, $fields);

	my ($newest) = $db->get_row_array("SELECT MAX(statdate) FROM $db->{t_plr_weapons}");

	$fields = { 
		(map { $_ => "SUM(data.$_)" } keys %{$db->tableinfo($db->{t_plr_weapons})}), 
		skill => "AVG(plr.skill)",
	};
	delete @$fields{ qw( dataid plrid weaponid statdate ) };

	foreach my $class (@$classes) {
		my $interpolate = {
			class	=> $class,
			weapon	=> { class => $class },
		};
		foreach my $timestamp (@$dates) {
			my $start = strftime("%Y-%m-%d", localtime($timestamp));
			my $end = $self->end_date($range, $start);
			my $expr = simple_interpolate($a->{expr}, $fields);
			my $order = $a->{order} || 'desc';
			my $limit = $a->{limit} || '10';
			my $awardname = simple_interpolate($a->{name}, $interpolate);
			my $complete = ($end lt $newest) ? 1 : 0;

			next if (!$complete and !$allowpartial);

			$cmd  = "SELECT $expr awardvalue, plr.plrid ";
			$cmd .= "FROM $db->{t_plr_weapons} data, $db->{t_weapon} w, $db->{t_plr} plr ";
			$cmd .= "WHERE data.plrid=plr.plrid AND data.weaponid=w.weaponid ";
			$cmd .= "AND w.class=" . $db->quote($class) . " ";
			$cmd .= "AND plr.allowrank ";
			$cmd .= "AND (data.statdate BETWEEN " . $db->quote($start) . " AND " . $db->quote($end) . ") ";
			$cmd .= "GROUP BY data.plrid ";
			# must use 'having' and not 'where', since we're using expressions
			$cmd .= "HAVING $a->{where} " if $a->{where};
			$cmd .= "ORDER BY 1 $order ";
			$cmd .= "LIMIT $limit ";
#			print "$cmd\n"; next;

			$::ERR->verbose("Calc " . ($complete ? 'complete' : 'partial ') . 
				" $a->{type} award on " . sprintf("%-5s",$range) . " $start for '$awardname'");
			my $plrs = $db->get_rows_hash($cmd) || next;
#			next unless @$plrs;

			# if all players have a 0 value ignore the award
			my $total = 0;
			$total += abs($_->{awardvalue} || 0)for @$plrs;
#			next unless $total;
			$plrs = [] unless $total;

			$db->begin;
			my $id = $db->select($db->{t_awards}, 'id', 
				[ awardid => $a->{id}, awarddate => $start, awardrange => $range, awardname => $awardname ]
			);
			if ($id) {
				$db->delete($db->{t_awards}, [ id => $id ]);
				$db->delete($db->{t_awards_plrs}, [ awardid => $id ]);
			}

			if (!@$plrs) {	# do not add anything if we have no valid players
				$db->commit;
				next;
			}

			$id = $db->next_id($db->{t_awards});
			my $award = {
				id		=> $id,
				awardid		=> $a->{id},
				awardtype	=> $a->{type},
				awardweapon	=> $class,
				awardname	=> $awardname,
				awarddate	=> $start,
				awardrange	=> $range,
				awardcomplete	=> $complete,
				topplrid	=> @$plrs ? $plrs->[0]{plrid} : 0,
				topplrvalue	=> @$plrs ? $plrs->[0]{awardvalue} : 0
#				topplrvalue	=> $self->format(@$plrs ? $plrs->[0]{awardvalue} : 0)
			};
			$db->insert($db->{t_awards}, $award);

=pod
			my $idx = 0;
			foreach my $p (@$plrs) {
				next unless $p->{awardvalue};
				$db->insert($db->{t_awards_plrs}, {
					id	=> $db->next_id($db->{t_awards_plrs}),
					idx	=> ++$idx,
					awardid	=> $id,
					plrid	=> $p->{plrid},
					value	=> $p->{awardvalue}
				});
			}
=cut
			$db->commit;
		}
	}

}


1;
