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
package PS::ErrLog;

use strict;
use warnings;
use Carp;

our $VERSION = '4.00.' . (('$Rev$' =~ /(\d+)/)[0] || '000');

our $ANSI = 0;
#eval "use Term::ANSIColor";
#if (!$@) {
#	$ANSI = 1;
#}

my $ERRHANDLER = undef;		# only 1 Error handler is ever created by new{}

sub new {
	return $ERRHANDLER if defined $ERRHANDLER;
	my $proto = shift;
	my $class = ref($proto) || $proto;
	my $db = shift || croak("You must provide a database object to $class\->new");
	my $conf = shift;
	my $self = {
		class => $class,
		db => $db,
		conf => $conf,
		_verbose => 0
	};
	$ERRHANDLER = bless($self, $class);
	return $self;
}

# If log() is called as a package method it will write to stats.log.
# If log() is called as a class method it will write to the database.
sub log {
	my $self = shift;
	my $msg = shift;
	my $severity = lc(shift || 'info');
	my $notrace = shift || 0;
	$severity = 'info' if $severity eq 'i';
	$severity = 'warning' if $severity eq 'w';
	$severity = 'fatal' if $severity eq 'f';
	$severity = 'info' unless $severity =~ /^info|warning|fatal$/;
	chomp($msg);				# remove newlines

	if ($severity eq 'fatal') {
		my $callerlevel = 6;
		my @trace;
		while ($callerlevel >= 0) {
			my ($pkg,$filename,$line) = caller($callerlevel);
			--$callerlevel && next unless defined $pkg and $line;
			push(@trace, "$pkg($line)") unless $pkg =~ /^PS::(ErrLog|Debug|Core)/;
			$callerlevel--;
		}
		my $plaintrace = join("->", @trace);

		$msg = "Called from $plaintrace >>>\n" . $msg unless $notrace;
	}

	if (((ref $self and $self->{_verbose}) or !ref $self) or $severity ne 'info') {
		my $prefix = '';
		if ($severity ne 'info') {
			if ($ANSI) {
				$prefix = "[" . color('bold') . uc($severity) . color('reset') . "] ";
			} else {
				$prefix = "[" . uc($severity) . "] ";
			}
			$prefix .= '* ' if !ref $self;
		}
		warn $prefix . $msg . "\n";
	}

	if (ref $self and ref $self->{db}) {
		my $nextid = $self->{db}->next_id($self->{db}->{t_errlog});
		$self->{db}->insert($self->{db}->{t_errlog},{
			'id' => $nextid, 'timestamp' => time,
			'severity' => $severity, 'msg' => $msg
		});
		$self->truncate;
	} else {
		if (open(L, ">>stats.log")) {
			my @lines = split("\n", $msg);
			my $line1 = shift @lines;
			print L "[" . uc($severity) . "] $line1\n" . join("\n", map { " > $_" } @lines) . (@lines ? "\n" : "");
			close(L);
		}
	}

	if ($severity eq 'fatal') {
		if (main->can('exit')) {
			main::exit();
		} else {
			exit();
		}
	}
}

# shortcuts for logging info, warning or fatal messages
sub info { shift->log(shift, 'info', @_) }
sub warn { shift->log(shift, 'warning', @_) }
sub fatal { shift->log(shift, 'fatal', @_) }

# never let the size of the errlog table grow too large. Truncate based on date
# and total rows
sub truncate {
	my $self = shift;
	my $maxrows = defined $_[0] ? shift : $self->{conf}->main->errlog->maxrows;
	my $maxdays = defined $_[0] ? shift : $self->{conf}->main->errlog->maxdays;
	my $db = $self->{db};
	$maxrows = 5000 unless defined $maxrows;
	$maxdays = 30 unless defined $maxdays;
	return if $maxrows eq '0' and $maxdays eq '0';		# nothing to do if both are disabled (not recommended)
	my $deleted = 0;
	if ($maxdays) {
		$db->delete($db->{t_errlog}, $db->qi('timestamp') . " < " . (time-60*60*24*$maxdays));
		$deleted++;
	}
	if ($maxrows) {
		my $total = $db->count($db->{t_errlog});
		return if $total < $maxrows;
		my $tbl = $db->{t_errlog};
		my $diff = $total - $maxrows;			# how many rows to delete
		my $id;
		my @ids;
		$db->query("SELECT " . $db->qi('id') . " FROM $tbl ORDER BY " . $db->qi('timestamp') . " LIMIT $diff");
		if ($db->{sth}) {
			$db->{sth}->bind_columns(\$id);
			while ($db->{sth}->fetch) {
				push(@ids, $id);
			}
			$db->{sth}->finish;
			if (scalar @ids) {
				$db->query("DELETE FROM $tbl WHERE " . $db->qi('id') . " IN (" . join(',', @ids) . ")");
				$deleted++;
			}
		}
	}
	if ($deleted) {
		$db->{sth}->finish;
		# I'd rather have this in a DESTORY block, but the database
		# handle seems to be destroyed before the ErrLog is so I have no
		# valid DB handle at the time this object is destroyed. Owell.
		$db->optimize($db->{t_errlog}) if int(rand(20)) == 1;		# approximately 5% chance to optimize the table
	}
}

# Simple verbose command. Only echos the output given if verbose is enabled in
# the config
sub verbose {
	my ($self, $msg, $no_newline) = @_;
	return unless $self->{_verbose};
	print $msg;
	print "\n" if (!$no_newline and $msg !~ /\n$/);
}

sub set_verbose {
	my ($self, $new) = @_;
	$self->{_verbose} = $new;
}

sub get_verbose {
	$_[0]->{_verbose};
}

1;
