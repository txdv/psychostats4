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
package PS::Core;

use strict;
use warnings;
use PS::ErrLog;
use PS::SourceFilter;

our $VERSION = '4.00.' . (('$Rev$' =~ /(\d+)/)[0] || '000');

# may be called as a class or package method
sub debug {
	my $self = shift;
	# determine if debugging is enabled
	my $D = ($::DEBUG or (ref $self and $self->{debug}));
	# return debug value if no params are given, or if debug is disabled
	return $D if @_==0 || !$D;
	my $msg = shift;
	my $minlevel = shift || 1;
	my $maxcallers = defined $_[0] ? shift : 5;
	my $reqlevel = $::DEBUG || (ref $self ? $self->{debug} : 0);
	return if $reqlevel < $minlevel;	# ignore event if the verbosity isn't high enough
	$msg .= "\n" unless $msg =~ /\n$/;

	my @trace = ();
	my $plaintrace = "";
	my $callerlevel = $maxcallers; # $minlevel < 5 ? 20 : 2;
	$callerlevel-- if $callerlevel > 0;
	while ($callerlevel >= 0) {
		my ($pkg,$filename,$line) = caller($callerlevel--);
		next unless defined $pkg and $line;
		push(@trace, "$pkg($line)");
	}

	# remove the PS::Core element from the end of the trace
	pop @trace while (@trace and $trace[-1] =~ /^PS::Core/);
	$plaintrace = ' [' . join("->", @trace) . ']' if @trace;

	if ($::DEBUGFILE) {
		if (!open(DF, ">>", $::DEBUGFILE)) {
			warn "[WARNING]* Error opening debug file $::DEBUGFILE for writting: $!\n";
			$::DEBUGFILE = undef;		# disable the DEBUGFILE (to avoid further errors)
		} else {
			print DF "D$minlevel>$plaintrace $msg";
			close(DF);
		}
	}

	warn "D$minlevel>$plaintrace $msg";
}

sub debug1 { shift->debug(shift,1,@_) }
sub debug2 { shift->debug(shift,2,@_) }
sub debug3 { shift->debug(shift,3,@_) }
sub debug4 { shift->debug(shift,4,@_) }
sub debug5 { shift->debug(shift,5,@_) }
sub debug6 { shift->debug(shift,6,@_) }
sub debug7 { shift->debug(shift,7,@_) }
sub debug8 { shift->debug(shift,8,@_) }
sub debug9 { shift->debug(shift,9,@_) }

sub errlog {
	my $self = shift;
	if (ref $::ERR) {
		$::ERR->log(@_);
	} else {
		PS::ErrLog->log(@_);
	}
}
sub info  { shift->errlog(shift, 'info') }
sub warn  { shift->errlog(shift, 'warning') }
sub fatal { shift->errlog(shift, 'fatal') }

# The *_safe methods are mainly for use within the PS::DBI objects. Within
# certain methods (ie: query) using the normal errlog methods will cause a deep
# recursion error and the script will endlessly loop. These methods will output
# the message but will not store it to the database log.
sub errlog_safe { PS::ErrLog->log(@_[1,2]) }
sub info_safe   { shift->errlog_safe(shift, 'info') }
sub warn_safe   { shift->errlog_safe(shift, 'warning') }
sub fatal_safe  { shift->errlog_safe(shift, 'fatal') }

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

1;
