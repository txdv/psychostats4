package PS::CmdLine::Heatmap;

use strict;
use warnings;
use base qw( PS::CmdLine );

use Carp;
use Getopt::Long;
use Pod::Usage;
use util qw( expandlist date );

our $VERSION = '1.00.' . (('$Rev$' =~ /(\d+)/)[0] || '000');
our $AUTOLOAD;

# private: Loads command line parameters
sub _getOptions {
	my $self = shift;

	$self->{OPTS} = [];
	my $optok = GetOptions(
		# HEATMAP SETTINGS
		'brush=s'		=> \$self->{param}{brush},
		'file|o=s'		=> \$self->{param}{file},
		'format=s'		=> \$self->{param}{format},
		'hourly:s'		=> \$self->{param}{hourly},
		'limit|samples=i'	=> \$self->{param}{limit},
		'mapname|map=s'		=> \$self->{param}{mapname},
		'mapinfo|xml=s'		=> \$self->{param}{mapinfo},
		'nohourly'		=> \$self->{param}{nohourly},
		'overlay|img|bg=s'	=> \$self->{param}{overlay},
		'scale=f'		=> \$self->{param}{scale},
		'sql'			=> \$self->{param}{sql},

		# WHERE CLAUSE SETTINGS
		'headshot:s'		=> \$self->{param}{headshot}, 
		'statdate|start=s'	=> \$self->{param}{statdate},
		'enddate|end=s'		=> \$self->{param}{enddate},
		'killer|k=s'		=> \$self->{param}{killer},
		'victim|v=s'		=> \$self->{param}{victim},
		'player||plr|p=s'	=> \$self->{param}{player},
		'kteam=s'		=> \$self->{param}{kteam},
		'vteam=s'		=> \$self->{param}{vteam},
		'team=s'		=> \$self->{param}{team},
		'who=s'			=> \$self->{param}{who},
		'weapon=s'		=> \$self->{param}{weapon},

		# BASIC SETTINGS
		'config=s'	=> \$self->{param}{config},
		'noconfig'	=> \$self->{param}{noconfig},
		'help|?'	=> \$self->{param}{help},
		'V|version'	=> \$self->{param}{version},
		'quiet'		=> \$self->{param}{quiet},

		# DATABASE SETTINGS
		'dbtype=s'	=> \$self->{param}{dbtype},
		'dbhost=s'	=> \$self->{param}{dbhost},
		'dbport=s'	=> \$self->{param}{dbpost},
		'dbname=s'	=> \$self->{param}{dbname},
		'dbuser=s'	=> \$self->{param}{dbuser},
		'dbpass=s'	=> \$self->{param}{dbpass},
		'dbtblprefix:s'	=> \$self->{param}{dbtblprefix},

		# grab extra params that are not options
		'<>'		=> sub { push(@{$self->{OPTS}}, shift) }
	);

	$self->{param}{debug} = 1 if defined $self->{param}{debug} and $self->{param}{debug} < 1;

	if (!$optok) {
#		die("Invalid parameters given. Insert help page");
		pod2usage({ -input => \*DATA, -verbose => 1 });
	}

	if ($self->{param}{help}) {
		pod2usage({ -input => \*DATA, -verbose => 2 });
	}

}

# do some cleanup and prechecks on parameters
sub _sanitize {
	my ($self) = @_;
	my $p = $self->{param};

	# mapname defaults to first non-switch argument
	if (!$p->{mapname} and @{$self->{OPTS}}) {
		$p->{mapname} = shift @{$self->{OPTS}};
	}

	if ($p->{nohourly}) {
		$p->{hourly} = undef;		
	}

	if (defined $p->{format} and !$p->{hourly}) {
		warn "Notice: -format parameter ignored when not generating hourly heat maps.\n";
		$p->{format} = undef;
	}

	if (defined $p->{mapinfo} and !-f $p->{mapinfo}) {
		die "Mapinfo file '$p->{mapinfo}' does not exist!\n";
	}

	if (defined $p->{scale} and ($p->{scale} < 0.1 or $p->{scale} > 20)) {
		die "Invalid scale specified. Must be 0.1 - 20.\n";
	}

	$p->{brush} = lc $p->{brush} if defined $p->{brush};
	if (defined $p->{brush} and $p->{brush} !~ /^(?:small|medium|large)$/) {
		die "Invalid brush specified. Must be 'small', 'medium', or 'large'.\n";
	}

	# clean up the hourly range list

	if (defined $p->{hourly} and $p->{hourly} eq '') {
		$p->{hourly} = '0-23';
	}

	if (defined $p->{hourly}) {
		$p->{hourly} = join(', ', grep { $_ >= 0 && $_ <= 23 } expandlist($p->{hourly}));
	}

	$p->{who} = 'victim' unless defined $p->{who};
	$p->{who} = lc $p->{who};
	if ($p->{who} !~ /^(killer|victim)$/) {
		die "Invalid -who specified. Must be 'killer' or 'victim'\n";
	}

	if (defined $p->{statdate} and $p->{statdate} !~ /^\d\d\d\d-\d\d-\d\d$/) {
		die "Invalid -statdate specified. Must be in the form of 'YYYY-mm-dd'\n";
	}
	if (defined $p->{enddate} and $p->{enddate} !~ /^\d\d\d\d-\d\d-\d\d$/) {
		die "Invalid -enddate specified. Must be in the form of 'YYYY-mm-dd'\n";
	}

	if (defined $p->{overlay} and !-f $p->{overlay}) {
		die "Overlay background image '$p->{overlay}' does not exist or is not a file.\n";
	}

	# force headshot to a numeric 0 or 1
	if (defined $p->{headshot} and $p->{headshot} !~ /^\d+$/) {
		$p->{headshot} = 1;
	}
}

1;

__DATA__

=head1 NAME

PsychoStats - Comprehensive Statistics

PsychoStats Heatmap Generator

=head1 SYNOPSIS

=over 4

=item B<Generate a single heatmap>

=over 4 

=item heat.pl <map>

=item heat.pl <map> -o filename.png

=item heat.pl -xml <path/to/heat.xml> <map>

=back 4

=item B<Generate a series of heatmaps>

=over 4 

=item heat.pl -hourly <map> 

=item heat.pl -hourly -format "%m_%h.png" <map> 

=back 8

=head1 COMMANDS

The order of commands does not matter.

=over 4

=item B<-brush> [small, medium, large]

Specifies the brush size to use. 'medium' is used by default.

=item B<-date> [string]

Defines the date to collect heat map data for. Must be in 'YYYY-MM-DD' format.
By default the most recent date available in the PsychoStats database will be used.

=item B<-file> [string], B<-o> [string]

Specifies the output file to use. Only used for single heat maps.
A filename of "B<->" will output the heat map to STDOUT.
A filename of "B<blob>" will save the heat maps to the PsychoStats database instead 
of a normal file. B<This is the default action!>.
Use B<-format> when creating hourly heat map files (not DB blobs).

=item B<-format> [string]

Changes the format used to generate a filename. 
By default "B<%m_%i.png>" is used.

=over 4

=item B<%m> Name of map.

=item B<%i> File index (starts at 01 and goes up).

=item B<%h> Hour of image (00 .. 23). Only useful with -hourly.

=back

Any other characters in the format are used literally.

=item B<-hourly> [list of hours]

Generates a series of hourly heat maps for the map specified. A list
of hours can be supplied as "0,1,2", "10-15", "1,3,5,20-23". By default
"0-23" is used. At most 24 heat maps are created. Use -format to change 
how the filenames are determined.

=item B<-limit> [number]

Limit heat map data samples. Defaults to 5500. The higher this value the
more processing and memory required to generate a heat map.

=item B<-mapinfo> [file], B<-xml> [file]

Specifies a different mapinfo XML file to read heatmap configuration
from. By default B<heat.xml> is read.

=item B<-mapname> [string]

Specifies the name of the map to generate a heat map for. If not specified
the first non-switch argument provided on the command line will be used as 
the map name.

=item B<-scale> [1,2,3,4]

Scale factor of the heat map. The higher the scale factor the smaller
the heat map. A scale of 1 means the heat map will be the same size as the 
map overlay image. 2 is half the size, etc. Each step in scale reduces the 
size by half. The lower the scale the longer it takes to process the heatmap.

=back

=cut
