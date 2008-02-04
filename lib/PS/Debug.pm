# parent class to most anything else. This just provides very basic debugging methods to all classes
# Don't try and create objects from this class directly.
package PS::Debug;

use strict;
use warnings;
use Data::Dumper;

our $ANSI = 0;
#eval "use Term::ANSIColor";
#if (!$@) {
#	$ANSI = 1;
#}

our $VERSION = '1.00.' . ('$Rev$' =~ /(\d+)/)[0];
our $DEBUG = 0;		# enable global debugging for everything if TRUE

# may be called as a class or package method
sub debug {
	my $self = shift;
	return if ((ref $self and !$self->{debug}) and !$DEBUG and !$::DEBUG);
	my $msg = shift;
	my $minlevel = shift || 1;
	my $maxcallers = shift || 5;
	my $reqlevel = $::DEBUG || $DEBUG || (ref $self ? $self->{debug} : 0);
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

	pop @trace while ($trace[-1] =~ /^PS::Debug/);	# remove the PS::Debug element from the end of the trace
	$plaintrace = join("->", @trace);

	if ($::DEBUGFILE) {
		if (!open(DF, ">>", $::DEBUGFILE)) {
			print STDERR "[WARNING]* Error opening debug file $::DEBUGFILE for writting: $!\n";
			$::DEBUGFILE = undef;		# disable the DEBUGFILE (to avoid further errors)
		} else {
			print DF ('-' x $minlevel) . "> [$plaintrace] $msg";
			close(DF);
		}
	}

	print STDERR ('-' x $minlevel) . "> [$plaintrace] $msg";
}

sub debug1 { shift->debug(shift,1,@_) }
sub debug2 { shift->debug(shift,2,@_) }
sub debug3 { shift->debug(shift,3,@_) }
sub debug4 { shift->debug(shift,4,@_) }
sub debug5 { shift->debug(shift,5,@_) }

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

# The *_safe methods are mainly for use within the PS::DB objects. Within certain methods (ie: query) using the normal
# errlog methods will cause a deep recursion error and the script will endlessly loop.
# These methods will output the message but will not store it to the database log.
sub errlog_safe { PS::ErrLog->log(@_[1,2]) }
sub info_safe   { shift->errlog_safe(shift, 'info') }
sub warn_safe   { shift->errlog_safe(shift, 'warning') }
sub fatal_safe  { shift->errlog_safe(shift, 'fatal') }

1; 

