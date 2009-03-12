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
package PS::Conf;

use strict;
use warnings;

use PS::Conf::conftype;
use PS::Conf::section;
use Carp;

our $VERSION = '4.00.' . (('$Rev$' =~ /(\d+)/)[0] || '000');
our $AUTOLOAD;

sub new {
	my $proto = shift;
	my $db = shift;		# must have a valid PS::DBI object
	my $class = ref($proto) || $proto;
	my $self = { db => $db, order => [] };
	my @types;
	
	$self->{st} = $db->prepare('load_conftype', qq{
		SELECT section, var, value FROM $db->{t_config}
		WHERE var IS NOT NULL AND conftype = ?
	});

	# Capture a PS::CmdLine object if passed in...
	for (my $i=0; $i<@_; $i++) {
		if (!ref $_[$i]) {
			push(@types, $_[$i]);
		} elsif (ref($_[$i]) =~ /^PS::CmdLine/) {
			# If the ref is a PS::CmdLine::* object ...
			$self->{opt} = $_[$i];
		}
	}

	bless($self, $class);
	return $self->load(@types);
}

# load config.
# this class uses constant subs and nested objects to mimic the config hierarchy
# in the database. Benchmark results show that the speed comparison between
# using methods vs. direct hash access is rougly the same. But it's easier to
# reference a config var with $main->section->var, then with
# $main->{section}{var}. the downside is methods can not be easily used inside
# strings.
sub load {
	my ($self, @list) = @_;
	my ($section, $var, $value);
	return $self unless @list;

	foreach my $conftype (@list) {
		next if $self->can($conftype);			# only load a config type ONCE
		
		$self->{st}->execute($conftype)
			|| $self->fatal_safe("Error loading configuration for '$conftype': " . $self->{st}->errstr);
		#$self->{st}->bind_columns(\($section, $var, $value));
		#next if $var eq '';
		my $vars = $self->{st}->fetchall_arrayref;

		# create an object for this conftype
		my $o = new PS::Conf::conftype($conftype, $vars, $self->{opt});

		# create a sub with the name of the conftype pointing to the
		# object we just created so $self->conftype will return the
		# proper config object (constant sub).
		{ 
			no strict "refs";
			*{"$conftype"} = sub () { $o };
		}
	}

	return $self;
}

# creates a method for $var that returns the $value
sub _var {
	my ($self, $var, $value) = @_;
	return if !defined $var or $self->can($var);
	if (defined $value) {
		$value =~ s/'/\\'/g;
		eval "sub $var () { '$value' }";
	} else {
		eval "sub $var () { undef }";
	}
	return $value;
}

# loads config from a file (stats.cfg, etc).
# can be called as a class method or module method ($conf->loadfile() or PS::Config->loadfile()
sub loadfile {
  my $self = shift;
  my %args = (
	'filename'	=> '',
	'oldconf'	=> undef,
	'fatal'		=> 1,
	'warning'	=> 1,
	'commentstr'	=> '#',
	'idx'		=> 0,
	'section'	=> 'global',
	'sectionname'	=> 'SECTION',
	'idxname'	=> 'IDX',
	'ignorequotes'	=> 0,
	'preservecase'	=> 0,
	'noarrays'	=> 0,
	(scalar @_ == 1) ? ( 'filename' => shift ) : @_
  );
  my ($var, $val, $begin, $end, $begintotal, $tell);
  my %blockend = ( '{' => '}', '[' => ']' );
  my $mainconf = defined $args{oldconf} 
	? $args{oldconf}
	: ref $self ? $self->{conf} : {};
  my $confptr = $mainconf;
  my $was_fh = 0;
  $args{section} = lc $args{section};			# make sure section names are always lowercase
							# this allows us to start at an alternate section from 'global'


  if (ref \$args{filename} eq 'GLOB') {
    *FILE = \$args{filename};
    $was_fh = 1;
  } else {
    unless (open(FILE, "<$args{filename}")) {
      if ($args{fatal} or $args{warning}) {
        carp("Error opening config file: $args{filename}: $!");
        $args{fatal} ? exit : return wantarray ? () : {};
      } 
    }
  }
  $tell = tell FILE if $was_fh;		# save current file pos

  while (<FILE>) {
    s/^\s+//;                                   			        # remove whitespace from front
    s/\s+$//;                               					# remove whitespace from end
    next if $args{commentstr} ne 'none' and /^\Q$args{commentstr}/; 		# skip comments
    next if /^$/; 								# skip blank lines
    next if not /^\[?\s*\S+\s*(>|\]|=|\{|\[)/;					# skip 'invalid' lines

    if (/^\[\s*(.+)\s*\]/) {							# [SECTION] header
      $args{section} = lc $1;
      ## create section if needed and create reference to new hash section, taking care of 'global'
      if ($args{section} ne 'global') {
	# keep order of sections as read from file
        $mainconf->{ $args{section} }{ $args{idxname} } = ++$args{idx} unless exists $mainconf->{ $args{section} };
        $confptr = $mainconf->{ $args{section} };
        $confptr->{ $args{sectionname} } = $1 unless exists $confptr->{ $args{sectionname} };		# preserve the section header case
      } else {
        $confptr = $mainconf;						# reset confptr to 'global' level of hash
      }

    } elsif (/^\s*(\S+?)\s*=\s*(.*)/) {						# VAR = VALUE
      ($var, $val) = ($1,defined $2 ? $2 : '');
      $var = lc $var unless $args{preservecase};
      $val =~ s/\s*\Q$args{commentstr}\E.*// if $args{commentstr} ne 'none'; 	# remove comments from end
      if (($var eq '$comments') and ($val ne '')) {				# change the comment str if requested
        $args{commentstr} = $val;
        next;
      }
      $val =~ s/^"(.*)"$/$1/ unless $args{ignorequotes};			# remove double quotes if present

      if ($var =~ /^([\w\d]+)\.([\w\d]+)/) {					# dot notation to specify a different SECTION
        if (lc $1 ne 'global') {						# IGNORE 'global' sections
          _assignvar($mainconf->{$1}, $2, $val, $args{noarrays});		# NOTE: use %newconf and not $confptr !
        } else {
          _assignvar($mainconf, $2, $val, $args{noarrays});
        }
      } else {									# normal variable
        _assignvar($confptr, $var, $val, $args{noarrays});
      }

    } elsif (/^\s*(\S+?)\s*>+\s*([\.\w\d]+)/) {					# VAR >> END
      ($var, $val) = ($1,$2);
      my $token = $val;
      $var = lc $var unless $args{preservecase};
      $val = '';
      while (my $line = <FILE>) {
        if ($line =~ /^\s*\Q$token\E\s*$/i) {					# matched 'END' token
          last;
        } else {
          $val .= $line;
        }
      }

      if ($var =~ /^([\w\d]+)\.([\w\d]+)/) {					# dot notation to specify a different SECTION
        if ($1 ne 'global') {							# IGNORE 'global' sections
          _assignvar($mainconf->{$1}, $2, $val, $args{noarrays});		# NOTE: use %newconf and not $confptr !
        } else {
          _assignvar($mainconf, $2, $val, $args{noarrays});
        }
      } else {									# normal variable
        _assignvar($confptr, $var, $val, $args{noarrays});
      }

    } elsif (/^\s*(\S+?)\s*([{\[])\s*(.*)/) {					# -- VAR {[ VALUE (multi-line) ]} --
      ($var, $begin, $val) = ($1,$2,defined $3 ? $3 : '');
      $end = $blockend{$begin};							# get block ending character
      $var = lc $var unless $args{preservecase};
      my $block = '';

      $begintotal = 1;
      if ($val =~ /^(.*)(\Q$end\E\s*)/) {					# var { $1 } ($2 = $end; line doesn't have to exist)
        $block = $1;
        if (defined $2) {
          $val = $end;								# set '}' so the while loop below will not run.
          $begintotal = 0;
        } else {
          $block .= "\n";
        }
      }
      while ( (($val ne $end) or ($begintotal>0)) and !eof(FILE)) {		# This runs when an {} block has more than one line
        $val = getc(FILE);							# get next char
        $begintotal-- if ($val eq $end);					# must account for nested {} blocks
        $begintotal++ if ($val eq $begin);
        $block .= $val if ($val ne $end) or ($begintotal>0);
      }
      $block =~ s/^\s+//;							# trim white space from value
      $block =~ s/\s+$//;

	# I don't use this and it's a security risk to have it... 
      #if ($begin.$end eq '{}') {						# CODE block { ... } needs to be run
      #  my $code = $block;
      #  my $this = eval $code;
      #  if (!$@) {
      #    $block = (defined $this) ? $this : '';
      #  } else {
      #    &logerror("Invalid code block '$var' specified in $args{filename} ($@)",1);
      #  }
      #}

      if ($var =~ /^([\w\d]+)\.([\w\d]+)/) {					# dot notation to specify a different SECTION
        if ($1 ne 'global') {							# IGNORE 'global' sections
          _assignvar($mainconf->{$1}, $2, $block, $args{noarrays});		# NOTE: use %newconf and not $confptr !
        } else {
          _assignvar($mainconf, $2, $block, $args{noarrays});
        }
      } else {									# normal variable
        _assignvar($confptr, $var, $block, $args{noarrays});
      }
#      _assignvar($confptr, $var, $block, $args{noarrays});			# assign final value to variable

    } ## if..else..
  } ## while(FILE) ...

  # rewind to where we started if we supplied a file handle
  if ($was_fh) {
#    print "rewinding file to byte $tell!\n";
    seek(FILE,$tell,0);
  } else {
    close(FILE);
  }

  # convert all arrays in the config to scalar strings (if noarrays is specified)
  if ($args{noarrays}) {
    foreach my $k (keys %{$mainconf}) {
      if (ref $mainconf->{$k} eq 'HASH') {						# handle sub-hashes (there can be only 2 levels;
        foreach my $k2 (keys %{$mainconf->{$k}}) {					# so there is need for recursion)
          next unless ref $mainconf->{$k}{$k2} eq 'ARRAY';
          my $ary = $mainconf->{$k}{$k2};
          $mainconf->{$k}{$k2} = join("\n", @$ary);
        }
      } else {
        next unless ref $mainconf->{$k} eq 'ARRAY';
        my $ary = $mainconf->{$k};
        $mainconf->{$k} = join("\n", @$ary);
      }
    }
  }
  return wantarray ? %{$mainconf} : $mainconf;
}

# NOT A CLASS METHOD
# internal function for loadfile(). Assigns a value to the 'var'. Automatically converts var into an array if required
sub _assignvar {
	my ($conf, $var, $val, $noary) = @_;
	if (!$noary and exists $conf->{$var}) {
		if (ref $conf->{$var} ne 'ARRAY') {
			my $old = $conf->{$var};
			$conf->{$var} = [ $old ];		# convert scalar into an array with its original value
		}
		push(@{$conf->{$var}}, $val);			# add new value to the array
	} else {
		$conf->{$var} = $val;				# single value, so we keep it as a scalar
	}
	return 1;
}

# catch any references to undefined variables and return undef
sub AUTOLOAD {
	my $self = ref($_[0]) =~ /::/ ? shift : undef;
	my $var = $AUTOLOAD;
	$var =~ s/.*:://;
	return if $var eq 'DESTROY';
	
	# create a new anon sub to handle this unknown conftype
	my $o = new PS::Conf::conftype($var);
	no strict "refs";
	*{"$var"} = sub () { $o };
	
	carp("Warning: Unknown config type ($var) used");

	goto &$var;
}

1;