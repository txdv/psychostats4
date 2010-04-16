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
#	This PS::Config object allows easy access to all config variables in
#	the database using either hash references or object-like methods.
#	For example:
#		$conf->var_name
#		$conf->{var_name}
#		$conf->global->ranking->player_min_skill
#		$conf->global->{ranking}{player_min_skill}
#		$conf->{global}{ranking}{player_min_skill}
#
#	Note: If you use hashes to access variables you must chain hashes from
# 	right to left completely. Meaning, this will work:
#		$conf->global->{ranking}{player_min_skill}
#	But this will not:
#		$conf->global->{ranking}->player_min_skill
#
package PS::Config;

use strict;
use warnings;
use Carp;

our $VERSION = '4.00.' . (('$Rev$' =~ /(\d+)/)[0] || '000');
our $AUTOLOAD;
our $FATAL_ON_UNDEF = 1;

sub new {
	my $proto = shift;
	my $db = shift;		# must have a valid PS::DBI object
	my $where = shift;
	my $class = ref($proto) || $proto;
	my $self = {};
	
	bless($self, $class);
	$self->LOAD($db, $where);

	return $self;
}

# load config from DB
sub LOAD {
	my ($self, $db, $where) = @_;
	my ($var, $value, $group, $section);
	my ($cmd, $st);
	
	$cmd  = "SELECT cfg_var, cfg_value, cfg_group, cfg_section FROM t_config";
	$cmd .= " WHERE " . $db->where($where) if $where;
	$cmd .= " ORDER BY cfg_group, cfg_section, cfg_var";

	$st = $db->prepare($cmd);
	$st->execute;
	$st->bind_columns(\($var, $value, $group, $section));

	while ($st->fetch) {
		no strict 'refs';

		# all vars are maintained in a single hash (vars are unique)
		# so we don't have to rely on the group->section a var might
		# be inside of.
		$self->{$var} = $value;

		# -------------------------------------------------------------
		# NOTE: MOST OF THE CODE BELOW WAS COMMENTED OUT AND REPLACED
		# WITH THE ADVANCED 'AUTOLOAD' METHOD FROM Hash::AsObject.
		# -------------------------------------------------------------
		
		# top level method for each unique var
		#if (!$self->can($var)) {
		#	*{ref($self) . '::' . $var} = sub () { $value };
		#}
	
		if ($group) {
			# if the group doesn't exist then we need to add it
			#if (!$self->can($group)) {
			#	my $g = bless({}, 'PS::Config::Hash');
			#	*{ref($self) . '::' . $group} = sub () { $g };
			#}
	
			if ($section) {
				#if (!$self->$group->can($section)) {
				#	my $s = bless({}, 'PS::Config::Hash');
				#	*{ref($s) . '::' . $section} = sub () { $s };
				#}
				#*{ref($self->$group->$section) . '::' . $var} = sub () { $value };
				$self->{$group}{$section}{$var} = $value;
				#$self->$group->{$section}{$var} = $value;
				#$self->$group->$section->{$var} = $value;
			} else {
				#*{ref($self->$group) . '::' . $var} = sub () { $value };
				$self->{$group}{$var} = $value;
				#$self->$group->{$var} = $value;
			}
		}
	}
	$st->finish;
	return $self;
}

# Load a basic config from a file.
# This is used as a package method and not an object method.
sub LOAD_FILE {
	my $self = shift;
	my $filename = shift;
	my %arg = (
		lowercase	=> 1,	# lowercase variable names?
		empty_on_undef 	=> 0, 	# convert undef values to empty strings?
		auto_arrays 	=> 0,	# auto convert duplicate vars into arrays?
		comments	=> '#', # char(s) used for comments
		reset_file_pos	=> 1,	# should file POS be reset? (if $filename is a FILEHANDLE)
		@_
	);
	my ($tell, $was_fh);
	my $conf = {};
	
	if (ref \$filename eq 'GLOB') {
		*FILE = \$filename;
		$tell = tell FILE;		# save current pos
		$was_fh = 1;
	} else {
		unless (open(FILE, "<$filename")) {
			return;
			#die("Error opening config file: $filename: $!");
		}
	}
	
	while (<FILE>) {
		s/^\s+//;			# remove whitespace from front
		s/\s+$//;			# remove whitespace from end
		next if /^\Q$arg{comments}/;	# skip comments
		next if /^$/;			# skip blank lines

		# VAR = VALUE
		if (/^([a-zA-Z0-9_]+)\s*=\s*(.*)/) {
			my ($var, $val) = ($1, $2);
			$var = lc $var if $arg{lowercase};
			next if $var =~ /^__(opt|db|conf)$/;

			# convert to empty str if its not defined.
			# Not sure if this is useful anymore. perl 5.8.8 at
			# least never allows $2 to be undef.
			$val = '' if !defined($val) and $arg{empty_on_undef};

			# remove comments and double quotes if present
			if (defined $val) {
				$val =~ s/\s*\Q$arg{comments}\E.*//;
				$val =~ s/^"(.*)"$/$1/;
			}
			
			if ($arg{auto_arrays} and exists $conf->{$var}) {
				# convert var into an array
				if (!ref $conf->{$var}) {
					$conf->{$var} = [ $conf->{$var} ];
				}
				# add value to var array
				push(@{$conf->{$var}}, $val);
			} else {
				# assign value to var
				$conf->{$var} = $val;
			}
		}
	}

	# rewind to where we started if we supplied a file handle
	if ($was_fh) {
		seek(FILE, $tell, 0) if $arg{reset_file_pos};
	} else {
		close(FILE);
	}

	return $conf;
}

# borrowed from Hash::AsObject by Paul Hoffman 
# http://search.cpan.org/dist/Hash-AsObject/lib/Hash/AsObject.pm
# I had my own routines in place to convert {vars} into methods but this
# autoload routine is a lot cleaner and handles more cases than I could imagine.
sub AUTOLOAD {
    my $invocant = shift;
    my $key = $AUTOLOAD;

    # --- Figure out which hash element we're dealing with
    if (defined $key) {
        $key =~ s/.*:://;
    }
    else {
        # --- Someone called $obj->AUTOLOAD -- OK, that's fine, be cool
        # --- Or they might have called $cls->AUTOLOAD, but we'll catch
        #     that below
        $key = 'AUTOLOAD';
    }
    
    # --- We don't need $AUTOLOAD any more, and we need to make sure
    #     it isn't defined in case the next call is $obj->AUTOLOAD
    #     (why the %*@!? doesn't Perl undef this automatically for us
    #     when execution of this sub ends?)
    undef $AUTOLOAD;
    
    # --- Handle special cases: class method invocations, DESTROY, etc.
    if (ref($invocant) eq '') {
        # --- Class method invocation
        if ($key eq 'import') {
            # --- Ignore $cls->import
            return;
        } elsif ($key eq 'new') {
            # --- Constructor
            my $elems =
                scalar(@_) == 1
                    ? shift   # $cls->new({ foo => $bar, ... })
                    : { @_ }  # $cls->new(  foo => $bar, ...  )
                    ;
            return bless $elems, $invocant;
        }
        else {
            # --- All other class methods disallowed
            die "Can't invoke class method '$key' on a Hash::AsObject object";
        }
    } elsif ($key eq 'DESTROY') {
        # --- This is tricky.  There are four distinct cases:
        #       (1) $invocant->DESTROY($val)
        #       (2) $invocant->DESTROY()
        #           (2a) $invocant->{DESTROY} exists and is defined
        #           (2b) $invocant->{DESTROY} exists but is undefined
        #           (2c) $invocant->{DESTROY} doesn't exist
        #     Case 1 will never happen automatically, so we handle it normally
        #     In case 2a, we must return the value of $invocant->{DESTROY} but not
        #       define a method Hash::AsObject::DESTROY
        #     The same is true in case 2b, it's just that the value is undefined
        #     Since we're striving for perfect emulation of hash access, case 2c
        #       must act just like case 2b.
        return $invocant->{'DESTROY'}          # Case 2c -- autovivify
        unless
            scalar @_                      # Case 1
            or exists $invocant->{'DESTROY'};  # Case 2a or 2b
    }
    
    # --- Handle the most common case (by far)...
    
    # --- All calls like $obj->foo(1, 2) must fail spectacularly
    die "Too many arguments"
        if scalar(@_) > 1;  # We've already shift()ed $invocant off of @_
    
    # --- If someone's called $obj->AUTOLOAD
    if ($key eq 'AUTOLOAD') {
        # --- Tread carefully -- we can't (re)define &Hash::AsObject::AUTOLOAD
        #     because that would ruin everything
        return scalar(@_) ? $invocant->{'AUTOLOAD'} = shift : $invocant->{'AUTOLOAD'};
    }
    else {
        my $cls = ref($invocant) || $invocant;
        no strict 'refs';
        *{ "${cls}::$key" } = sub {
            my $v;
            if (scalar @_ > 1) {
                $v = $_[0]->{$key} = $_[1];
                return undef unless defined $v;
            }
            else {
		if (!exists $_[0]->{$key}) {
			# warn the user that an invalid key was accessed
			carp("Use of undefined configuration setting '$key'");
			exit if $FATAL_ON_UNDEF;
		}
                $v = $_[0]->{$key};
            }
            if (ref($v) eq 'HASH') {
                bless $v, $cls;
            }
            else {
                $v;
            }

        };
        unshift @_, $invocant;
        goto &{ "${cls}::$key" };
    }
}

1;