# PS::Heatmap is a class that will generate a heatmap for maps.
# A heatmap is a PNG image that is meant to be overlayed on top of a map image and represents some sort of
# spatial stat on that map (like where players have died, what locations are used for AWP camping, etc...)
#
# 	$Id$
#
package PS::Heatmap;

use strict;
use warnings;
use base qw( PS::Debug );
use POSIX qw(floor ceil);
use Data::Dumper;
use GD;

use Carp;

our $VERSION = '1.00.' . ('$Rev$' =~ /(\d+)/ || '000')[0];
our $AUTOLOAD;

sub new {
	my $proto = shift;
	my $class = ref($proto) || $proto;
	my $self = { 
		class 		=> $class,
		_data		=> undef,		# dataset to build heatmap from
		_width		=> 200,			# width of map overlay
		_height		=> 200,			# height of map overlay
		_scale		=> 1,			# scale to use for heatmap (based on map overlay resolution)
		_brush		=> 'medium',		# brush to use ('small', 'medium', 'large' or an array ref for custom)
		_flip_horizontal=> 0,
		_flip_vertical	=> 0,
	};
	bless($self, $class);

	# collect other parameters passed in
	if (@_) {
		my %args = ref $_[0] ? %{$_[0]} : @_;
		while (my ($var, $val) = each %args) {
			$self->_set($var, $val);
		}
	}

	# use GD at runtime, so we can more accurately report a user-friendly error
#	eval "require GD";
#	if ($@) {
#		croak("The GD module is required but is not available.");
#	}

	return $self;
}

# burn the heatmap data onto our canvas array
sub burn {
	my ($self, $dimx, $dimy, $canvas) = @_;
	$canvas ||= $self->canvas;
	croak("Canvas was not initialized before calling Heatmap::burn") unless ref $canvas;

	# setup some variables to avoid calling 'getter' functions over and over
	my $minx = $self->minx || 0;
	my $miny = $self->miny || 0;
	my $maxx = $self->maxx || $dimx;
	my $maxy = $self->maxy || $dimy;
	my $brush = $self->get_brush( $self->brush || 'medium' );
	my $brushsize = $self->get_brush_size($brush);

	# calculate the cell size from our defined boundary
	my $cellwidth  = abs($minx - $maxx) / $dimx;
	my $cellheight = abs($miny - $maxy) / $dimy;

	# our spatial data arrays
	my $datax = $self->datax;
	my $datay = $self->datay;

	# burn the canvas with the spatial data
	for (my $i=0; $i < @$datax; $i++) {
		my $x = floor(($datax->[$i] - $minx) / $cellwidth);
		my $y = floor(($datay->[$i] - $miny) / $cellheight);

		# each brush stroke adds a bit of heat to the canvas
		$self->stroke($x, $y, $dimx, $dimy, $canvas, $brush, $brushsize);
	}
}

# add a brush stroke (more like a brush dot) onto the canvas at the X/Y co-ords, 
# translated by the dimx / dimy variables.
sub stroke {
	my ($self, $x, $y, $dimx, $dimy, $canvas, $brush, $brushsize) = @_;

	my $brush_half = $brushsize/2;
	my $at = $y * $dimx - (ceil($brush_half) * $dimx);
	$at += $x + floor($brush_half);

	# at_x helps us determine if we're bleeding into the other side.
	my $at_x = $x - floor($brush_half);

	# loop through the brush and stroke the image
	for (my $i=0; $i < @$brush; $i++) {
		if ($at < @$canvas && $at >= 0 && $at_x >= 0 && $at_x < $dimx) {
			$canvas->[$at] += $brush->[$i];
		}
		$at_x++;
		if($i % $brushsize == 0) {
			$at += $dimx - $brushsize;
			$at_x = $x - floor($brush_half);
		}
		$at++;
	}
}

# normalize and colorize our burn data
# $im is a GD::Image object
# returns an array ref of colors generated, keyed on the canvas values
sub colorize {
	my ($self, $im, $canvas) = @_;
	$canvas ||= $self->canvas;
	return unless $canvas;

	# calculate the maximum value of the burned canvas
	my $max = $self->get_max_burn($canvas);

	# normalize the burn values. $canvas values will be between 0 .. 255
	$self->normalize_burn($max, $canvas);

	my $colors = [];	# array of 256 color values (some may be undef)

	# colorize the burn; This creates the image pallete in $im.
	for (my $i = 0; $i < @$canvas; $i++) {
		my $col = $canvas->[$i];
		if (!defined $colors->[$col]) {
			my ($r,$g,$b,$a) = $self->create_burn_color($canvas->[$i]);
			$colors->[$col] = $im->colorAllocateAlpha($r, $g, $b, $a);
		}
	}

	$im->alphaBlending(0);
	$im->saveAlpha(1);

	$self->colors($colors);
	return $colors;
}

# return an RGB color based on how high $p is (a canvas plot)
# blue -> yellow -> red
sub create_burn_color {
	my ($self, $p) = @_;
	my ($r, $g, $b, $a) = (0,0,0,0);

	$p /= 255;		# normalize: 0 .. 1 (float)

	# blue
	if ($p >= 0.33 && $p < 0.66) {
		$b = int((1 - (($p - 0.33)/0.33)) * 255);
#                       43    21         1     23      4
	}

	# green
	if ($p < 0.33) {
		$g = int($p / 0.33 * 255);
	} elsif ($p >= 0.33 && $p < 0.66) {
		$g = 255;
	} else {
		$g = int((1 - (($p - 0.66)/0.34)) * 255);
#                       43    21         1     23      4
	}

	# red
	if ($p < 0.33) {
		$r = 0;
	} elsif ($p >= 0.33 && $p < 0.66) {
		$r = int((($p - 0.33) / 0.33) * 255);
#                       321         1       2      3
	} else {
		$r = 255;
	}

	# alpha
	$a = int(127 * (1-$p));

	return ($r,$g,$b,$a);
}

# normalize the burn values on the canvas so everything is between 0 .. 255.
# this prevents the 'everything is red' problem when a large data set is used.
sub normalize_burn {
	my ($self, $max, $canvas) = @_;
	$canvas ||= $self->canvas;
	return unless $max > 0 and $canvas;

	for (my $i = 0; $i < @$canvas; $i++) {
		$canvas->[$i] = int(255 * $canvas->[$i] / $max);
	}
}

# return the maximum burn value on the canvas
sub get_max_burn {
	my ($self, $canvas) = @_;
	$canvas ||= $self->canvas;
	return 0 unless $canvas;
	my $max = 0;
	for (my $i=0; $i < @$canvas; $i++) {
		$max = $canvas->[$i] if $canvas->[$i] > $max;
	}
	return $max;
}

# primary render method. Creates the heatmap.
sub render {
	my ($self, $fh) = @_;
	my $dimx = $self->width;
	my $dimy = $self->height;

	# scale the heatmap dimensions
	if ((my $s = $self->scale) != 1.0) {
		$dimx = ceil($dimx * $s);
		$dimy = ceil($dimy * $s);
	}

	# initialize the canvas to all zeros
	my $canvas = [];
	for(my $i=0; $i < $dimx*$dimy; $i++) {
		$canvas->[$i] = 0;
	}
#	warn "CANVAS SIZE: " . @$canvas . "\n";
	$self->canvas($canvas);

	# heat it up!
	$self->burn($dimx, $dimy, $canvas);

	# create GD instance
	my $im;
#	if ($self->background) {
#		$im = new GD::Image($self->background) || croak("Error creating image: $!");
#		$im->trueColor(1);
#	} else {
		$im = new GD::Image($dimx, $dimy, 1) || croak("Error creating image: $!");
#	}

	# colorize the heat map and get a list of RGB colors
	my $colors = $self->colorize($im, $canvas);

	# rotate and flip the canvas ...
	if ($self->flip_vertical and $self->flip_horizontal) {
		# easy peasy ... 
		@$canvas = reverse @$canvas;
	} elsif ($self->flip_vertical) {
		my @tmp = ();
		my $y = -1;
		for (my $i=0; $i < @$canvas; $i++) {
			# each column is reversed (top to bottom)
			$y++ if $i % $dimx == 0;
			$tmp[$i] = $canvas->[($dimy-$y-1)*$dimx + ($i % $dimx)];
		}		
		@$canvas = @tmp;
	} elsif ($self->flip_horizontal) {
		my @tmp = ();
		for (my $i=0; $i < @$canvas; $i++) {
			# each row is reversed (left to right)
			$tmp[$i] = $canvas->[floor($i/$dimx+1)*$dimx - ($i%$dimx) - 1];
		}
		@$canvas = @tmp;
	}

	# plot the heatmap data
	$self->draw($im, $dimx, $colors, $canvas);

	# return the heatmap data, or print it directly to a filehandle given
	if (ref $fh) {
		print $fh $im->png;
	} elsif (defined $fh) {
		open(PNG, ">$fh") or die "Error opening file '$fh' for output: $!\n";
		print PNG $im->png;
		close(PNG);
	} else {
		return $im->png;
	}
	return '';		# return an empty string if we get to this point
}

# draw the burn data using our calculated colors.
# dimx is needed so we know the length of a row.
sub draw {
	my ($self, $im, $dimx, $colors, $canvas) = @_;
	$colors ||= $self->colors;
	$canvas ||= $self->canvas;

	my $x = 0;
	my $y = -1;	# will be 0 on the first iteration of the loop below
	for(my $i=0; $i < @$canvas; $i++) {
		$x = $i % $dimx;
		$y++ if $i % $dimx == 0;

		if (defined $canvas->[$i]) {
			$im->setPixel($x,$y, $colors->[ $canvas->[$i] ]);
		}
	}
}

# assign heat data to the object.
sub data {
	my ($self, $datax, $datay) = @_;
	$self->datax($datax);
	$self->datay($datay);
}

sub flip {
	my ($self, $v, $h) = @_;
	$self->flip_vertical($v);
	$self->flip_horizontal($h);
}

# Set the boundaries of the overlay map. 
# This will allow burn() to restrain the values in the heat data within the boundaries of our canvas.
sub boundary {
	my ($self, $x1, $y1, $x2, $y2) = @_;
	$self->minx($x1);
	$self->miny($y1);
	$self->maxx($x2);
	$self->maxy($y2);
}

# brushes must always be SQUARE (9x9, 25x25, 53x53, etc...)
sub get_brush_size {
	my ($self, $brush) = @_;
	my $b = $self->get_brush($brush);
	return defined $b ? sqrt @$b : undef;
}

sub get_brush {
	my ($self, $size) = @_;
	if (ref $size) {
		# return the brush exactly as given, since it's a custom brush array
		if (ref $size eq 'ARRAY') {
			# size is actually an array of values, so just return it.
			# in scalar context return the original reference and not a copy.
			return wantarray ? @$size : $size;
		} else {
			return undef;
		}
	} elsif ($self->can('brush_' . $size)) {
		# return pre-defined brush if a method exists for it
		my $func = 'brush_' . $size;
		my $brush = $self->$func();
		return wantarray ? @$brush : [ @$brush ];	# always return a new reference in scalar context
	}
	return undef;
}

# private 'setter' method. To set a variable simply use its name as the method call: $heat->scale(2)
sub _set {
	my ($self, $var, $val) = @_;
	# don't allow private vars to be changed
	# although, since I prefix everything with '_' its not possible to update those vars anyway
	if ($var !~ /^(?:class)$/) {
		my $old = $self->{'_' . $var};
		$self->{'_' . $var} = $val;
		return $old;
	} else {
		carp("Attempt to set private variable '\$$var' ignored");
	}
	return undef;
}

# private 'getter' method. To get a variable simple use its name as the method call: $heat->scale()
sub _get {
	my ($self, $var) = @_;
	return exists $self->{'_' . $var} ? $self->{'_' . $var} : undef;
}

# small brush: 9x9
sub brush_small {
	my @brush = qw(
		0 0  2  4   4   3   2  0  0
		0 3  10 22  27  21  10 3  0
		2 11 35 74  94  74  34 10 2
		4 21 74 155 199 155 74 21 4
		4 27 95 199 255 199 94 27 5
		4 21 74 155 199 156 73 21 4
		2 10 35 73  94  74  35 10 2
		0 3  10 21  27  21  10 3  0
		0 0  2  4   4   4   1  0  0
	);
	return wantarray ? @brush : [ @brush ];
}

# medium brush: 25x25
sub brush_medium {
	my @brush = qw(
		0 0 0 0 0 0 0 0 0 0 1 0 2 2 1 1 0 0 0 0 0 0 0 0 0  
		0 0 0 0 0 0 0 1 2 2 1 3 2 3 3 4 1 0 2 0 0 0 0 0 0  
		0 0 0 0 2 1 2 3 4 5 4 6 8 6 6 4 4 3 2 1 0 0 0 0 0  
		0 0 0 0 0 3 3 6 8 10 14 14 15 15 14 11 8 5 4 2 1 0 0 0 0  
		0 0 0 1 1 4 6 10 16 20 23 27 28 27 23 22 15 11 6 4 2 1 0 0 0  
		0 0 2 2 4 8 12 19 28 36 45 49 49 49 45 36 28 19 12 8 4 1 2 0 0  
		0 0 2 3 6 13 20 33 44 58 70 78 80 76 70 59 46 32 21 12 6 4 2 2 0  
		0 1 3 6 9 19 33 50 67 87 105 116 120 115 104 88 67 47 32 20 11 6 2 3 0  
		0 2 4 8 16 28 46 68 94 120 143 159 164 159 143 119 94 67 45 27 17 8 4 3 0  
		1 1 5 10 21 36 57 85 119 153 182 199 206 200 181 153 120 87 59 36 20 10 6 2 0  
		2 3 5 13 25 43 70 105 143 182 213 232 238 231 213 181 144 104 70 43 26 11 6 3 1  
		0 4 6 14 27 49 78 115 158 200 231 251 253 250 232 201 158 116 79 48 28 13 7 3 1  
		1 3 6 15 30 49 80 119 164 206 239 255 255 253 239 206 164 120 80 50 27 16 8 4 0  
		2 3 8 14 27 48 77 117 159 200 233 250 253 251 231 199 159 116 77 47 27 13 6 3 0  
		1 2 6 12 25 43 70 104 143 182 213 232 238 231 213 181 144 104 70 45 25 12 6 2 1 
		1 2 6 10 20 36 59 87 119 154 182 200 206 200 182 154 120 88 59 36 19 10 5 2 0 
		1 3 4 8 16 26 46 67 94 119 144 159 164 159 142 119 94 69 44 28 16 8 4 3 0 
		0 1 2 4 9 20 33 48 67 87 105 116 121 115 105 86 68 48 32 19 12 6 4 1 0 
		0 0 1 4 6 13 20 31 44 59 70 80 80 78 70 59 45 31 21 14 8 4 1 0 0 
		0 0 1 3 5 6 12 18 27 36 43 48 50 49 43 35 29 20 14 8 4 3 0 0 0 
		0 0 1 1 2 4 6 12 15 20 25 29 28 27 26 19 16 11 8 4 2 1 0 0 0 
		0 0 0 0 2 2 3 6 10 10 14 14 15 14 11 10 8 6 3 1 1 2 0 0 0 
		0 0 0 0 0 0 2 3 3 7 5 6 8 6 5 4 3 3 2 0 1 0 0 0 0  
		0 0 0 0 0 0 1 1 2 1 3 2 4 3 3 2 1 1 1 0 0 0 0 0 0  
		0 0 0 0 0 0 0 0 0 0 2 3 1 3 2 0 0 0 0 0 0 0 0 0 0 
	);
	return wantarray ? @brush : [ @brush ];
}

# large brush: 53x53
sub brush_large {
	my @brush = qw(
		0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 1 1 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 
		0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 1 1 0 1 1 0 1 1 1 1 1 1 0 1 1 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 
		0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 1 1 1 1 2 2 2 1 1 2 1 1 1 2 1 1 1 1 1 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 
		0 0 0 0 0 0 0 0 0 0 0 0 0 0 1 0 1 1 1 2 2 2 2 2 2 2 2 2 3 2 2 2 1 1 2 1 1 1 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 
		0 0 0 0 0 0 0 0 0 0 0 0 1 1 1 1 1 2 2 2 3 3 3 3 3 3 3 4 3 3 3 3 3 2 2 2 2 1 1 1 1 0 0 0 0 0 0 0 0 0 0 0 0 
		0 0 0 0 0 0 0 0 0 0 0 1 1 1 1 2 2 2 3 3 3 4 5 5 5 5 6 5 5 4 4 5 3 3 3 2 2 2 2 1 0 0 0 0 0 0 0 0 0 0 0 0 0 
		0 0 0 0 0 0 0 0 0 1 1 1 2 2 2 3 3 3 4 5 5 6 6 7 8 8 7 8 7 7 7 6 5 4 4 4 3 2 2 2 1 1 1 1 0 0 0 0 0 0 0 0 0 
		0 0 0 0 0 0 0 0 0 1 1 1 2 2 2 3 4 5 6 6 8 9 9 10 10 10 10 10 10 9 9 8 8 7 6 5 5 4 3 3 2 2 1 1 0 0 0 0 0 0 0 0 0 
		0 0 0 0 0 0 0 0 1 1 2 2 3 3 4 5 6 7 8 9 10 12 13 13 14 14 14 14 14 13 12 11 11 9 9 7 6 5 4 4 2 2 1 1 1 0 0 0 0 0 0 0 0 
		0 0 0 0 0 0 1 1 1 2 2 3 4 5 5 7 8 10 12 12 14 16 17 18 19 19 20 19 19 18 17 15 15 12 11 9 9 7 5 5 3 2 2 2 1 1 0 0 0 0 0 0 0 
		0 0 0 0 0 0 0 1 1 2 2 4 4 6 7 9 11 12 15 17 19 22 23 24 26 27 26 27 25 24 23 21 19 17 15 13 10 9 8 6 5 3 3 2 1 1 0 0 0 0 0 0 0 
		0 0 0 0 0 1 1 1 2 3 4 4 6 8 10 12 14 17 20 22 26 28 31 32 34 34 35 35 34 32 30 27 25 23 20 17 15 12 10 8 6 5 4 3 2 2 1 1 0 0 0 0 0 
		0 0 0 0 0 1 2 2 3 4 5 6 8 10 12 15 19 22 25 29 32 36 39 42 43 44 45 45 43 42 39 36 33 30 26 22 19 16 12 10 8 6 4 3 3 2 1 1 1 0 0 0 0 
		0 0 0 1 0 2 2 2 3 5 6 8 10 13 16 19 24 28 32 37 41 46 49 52 55 57 57 57 55 53 49 46 41 37 32 28 23 20 16 13 10 8 6 5 4 2 1 1 1 0 0 0 0 
		0 0 0 0 1 1 2 3 4 5 7 10 13 16 20 24 30 35 41 46 52 58 62 66 69 70 71 71 69 66 62 57 51 46 41 35 29 24 20 16 13 10 7 6 4 3 2 1 1 1 0 0 0 
		0 0 0 1 1 1 3 4 5 7 9 12 15 19 24 30 36 43 49 57 63 70 75 80 84 86 87 86 84 81 76 70 63 56 49 43 37 29 24 20 16 12 9 7 5 4 3 2 1 1 0 0 0 
		0 0 0 0 1 2 3 4 6 8 11 15 19 24 30 36 43 51 59 68 76 84 92 97 102 104 105 104 101 97 91 84 77 68 60 51 43 36 30 24 18 14 11 9 6 4 4 2 1 1 1 0 0 
		0 0 1 2 2 3 4 5 7 10 13 17 22 28 35 43 52 61 70 81 91 100 108 115 121 123 124 124 121 115 108 100 91 80 71 61 51 42 34 28 22 17 12 10 8 5 3 3 2 1 1 0 0 
		0 0 1 2 2 3 5 6 8 12 15 20 26 32 40 50 60 71 82 94 106 116 126 133 140 144 145 144 139 134 125 116 105 93 82 71 60 49 41 33 25 20 15 11 9 6 4 3 2 1 1 0 0 
		0 1 1 2 2 3 4 7 9 13 18 22 29 37 47 57 68 80 94 107 121 133 143 153 160 163 165 164 159 153 144 133 120 107 94 80 68 56 46 38 29 23 17 13 9 7 5 3 2 1 1 0 0 
		0 1 1 2 3 4 6 7 11 15 19 25 33 41 51 64 76 91 105 120 135 149 161 171 179 184 185 184 179 172 161 149 135 121 105 90 77 64 52 41 33 26 19 14 10 8 6 4 3 2 1 1 0 
		0 1 2 2 3 4 5 8 12 16 21 28 36 46 57 70 84 100 116 133 149 164 177 189 198 202 204 203 198 189 177 164 148 133 116 100 84 70 57 46 36 28 21 16 12 8 6 4 2 2 1 1 0 
		0 1 1 2 3 4 6 9 12 17 23 31 39 49 62 76 92 108 126 144 161 177 192 205 214 220 221 219 213 204 192 177 161 143 126 108 92 76 62 50 39 31 23 17 13 9 6 5 3 2 2 1 0 
		0 1 2 2 3 5 6 9 13 18 25 32 42 52 66 81 97 115 133 153 172 189 204 218 228 234 235 233 228 218 204 189 171 153 134 115 97 80 66 52 42 32 25 18 13 10 7 5 3 2 2 1 0 
		0 1 1 2 3 5 7 10 14 19 25 34 43 55 69 85 102 121 140 160 179 198 214 227 238 244 246 244 237 228 214 197 179 160 140 120 101 85 69 55 44 34 25 19 14 10 7 5 3 2 2 0 0 
		0 1 2 2 3 5 8 10 14 20 26 35 44 56 70 86 104 124 144 164 184 202 220 233 244 251 253 250 244 234 219 202 184 163 143 123 104 87 71 57 44 35 26 19 15 10 7 5 3 2 2 1 1 
		0 0 1 2 4 5 7 10 14 19 26 34 45 57 71 87 105 124 145 165 186 204 222 235 246 253 255 252 246 235 222 205 186 166 144 125 105 87 72 57 45 35 26 20 14 10 8 5 3 2 1 1 0 
		1 1 2 2 4 5 7 10 14 20 26 34 45 57 70 87 104 123 144 163 184 202 219 233 244 251 253 250 244 234 219 203 184 164 144 123 104 86 71 57 44 34 26 19 14 10 8 5 4 3 1 1 0 
		0 0 1 3 4 5 7 10 14 19 26 34 43 55 69 84 101 121 140 159 179 198 214 227 237 244 246 244 237 227 214 198 179 160 139 121 102 85 69 55 43 33 25 19 14 10 7 5 3 2 1 1 1 
		0 1 1 2 3 5 7 10 14 19 24 32 42 53 66 80 97 115 133 154 171 189 205 218 227 234 236 233 227 218 204 189 171 153 133 115 97 81 66 52 42 32 24 19 13 10 6 5 3 2 1 1 0 
		0 0 1 2 3 5 6 10 13 17 23 30 39 50 61 76 91 108 126 143 161 177 192 205 214 219 221 219 214 204 193 177 161 144 125 108 92 76 61 49 39 30 22 17 13 10 7 5 4 2 2 1 0 
		0 1 2 2 3 4 6 8 12 16 22 28 36 45 58 70 84 100 116 133 148 164 177 189 198 203 204 202 197 189 178 164 149 133 116 100 84 70 58 46 36 27 21 16 12 8 6 4 3 2 1 1 0 
		0 0 1 2 3 4 5 7 11 14 19 25 33 42 52 64 77 90 105 121 135 149 161 171 179 184 186 184 179 171 161 149 135 120 105 90 77 64 51 41 33 26 19 14 10 8 6 3 3 1 1 1 0 
		0 1 1 1 2 3 5 7 9 13 18 22 29 37 46 57 68 80 94 107 121 133 143 153 160 164 165 164 159 153 144 133 120 107 94 80 68 57 46 37 29 23 17 13 9 7 5 3 3 1 1 0 0 
		0 0 1 1 2 3 4 6 8 11 15 19 25 33 41 50 60 71 82 94 105 116 126 133 140 144 144 143 140 133 125 116 105 93 82 71 59 49 40 32 25 20 15 11 8 6 4 3 2 1 1 0 0 
		0 0 0 1 1 3 3 5 7 10 13 17 22 28 35 43 52 61 71 81 90 100 108 115 121 124 124 123 121 115 108 100 91 80 71 61 51 42 35 28 22 17 12 9 7 5 4 2 1 2 1 0 0 
		0 0 0 0 1 2 3 4 6 8 10 14 19 24 29 36 43 52 60 68 76 84 91 97 101 105 105 105 101 97 92 85 76 69 60 52 43 36 29 24 19 15 10 8 6 4 3 2 1 1 0 0 0 
		0 0 1 0 1 2 3 4 5 7 9 12 16 20 24 30 36 43 49 56 64 70 76 81 85 87 87 86 85 80 75 71 64 56 50 43 36 30 25 20 16 12 9 7 5 4 2 1 1 0 0 0 0 
		0 0 0 1 1 1 2 3 4 5 7 10 13 16 20 25 29 35 41 46 52 58 62 66 69 70 71 70 69 66 62 57 52 46 40 35 29 25 20 16 12 10 7 6 4 3 2 1 1 0 0 0 0 
		0 0 0 0 1 1 2 3 4 4 6 8 10 13 15 20 24 28 33 37 42 46 49 53 55 57 57 56 55 53 49 46 41 38 32 27 24 20 16 13 10 8 6 4 3 2 2 1 1 0 0 0 0 
		0 0 0 0 0 1 2 2 2 4 5 6 8 10 13 15 19 22 25 29 33 36 39 41 43 45 45 44 44 41 39 36 33 29 26 22 19 15 13 10 8 6 4 4 2 2 1 1 0 0 0 0 0 
		0 0 0 0 0 1 1 2 2 2 3 5 6 8 9 12 14 17 20 23 26 28 30 32 34 34 34 34 33 32 30 27 25 22 20 17 14 12 10 8 6 4 3 3 2 1 1 1 0 0 0 0 0 
		0 0 0 0 0 0 1 1 2 2 3 3 5 6 7 9 11 13 14 17 19 21 23 24 26 26 26 26 25 24 23 21 19 17 15 13 11 9 8 6 5 4 3 2 1 1 1 0 0 0 0 0 0 
		0 0 0 0 0 0 0 0 1 1 2 3 4 5 5 7 8 9 12 13 15 15 17 19 19 19 19 19 19 18 17 16 15 13 11 10 9 7 5 4 4 3 2 2 1 1 0 0 0 0 0 0 0 
		0 0 0 0 0 0 0 0 0 1 1 2 2 3 4 5 6 7 8 9 10 12 13 13 14 14 14 14 14 14 12 11 10 9 9 7 6 4 4 3 3 2 1 1 1 0 0 0 0 0 0 0 0 
		0 0 0 0 0 0 0 0 0 1 1 1 2 2 3 3 5 5 6 7 8 8 9 9 10 11 11 11 10 9 9 9 7 7 6 5 4 3 3 2 2 1 1 1 0 0 0 0 0 0 0 0 0 
		0 0 0 0 0 0 0 0 0 0 1 1 1 2 2 2 3 3 4 5 5 6 7 7 7 8 8 7 7 7 7 6 6 5 5 4 3 2 2 2 1 1 1 0 0 0 0 0 0 0 0 0 0 
		0 0 0 0 0 0 0 0 0 0 0 1 1 2 1 2 3 3 3 3 3 4 4 5 5 5 5 5 4 5 5 5 3 3 3 3 2 2 1 1 1 0 1 0 0 0 0 0 0 0 0 0 0 
		0 0 0 0 0 0 0 0 0 0 0 0 0 0 1 1 1 2 2 2 3 2 3 4 3 3 3 3 3 3 3 3 2 3 2 2 2 1 1 1 1 0 0 0 0 0 0 0 0 0 0 0 0 
		0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 1 1 2 1 1 2 2 2 2 3 3 2 2 2 2 2 1 1 1 1 1 0 1 0 0 0 0 0 0 0 0 0 0 0 0 0 
		0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 1 0 1 1 1 1 2 1 1 1 1 2 1 2 2 1 1 1 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 
		0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 1 0 0 1 1 1 1 1 1 1 1 1 1 1 1 1 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 
		0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 1 1 0 1 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0
	);
	return wantarray ? @brush : [ @brush ];
}

sub AUTOLOAD {
	my $self = ref($_[0]) =~ /::/ ? shift : undef;
	my $var = $AUTOLOAD;
	$var =~ s/.*:://;
	return if $var eq 'DESTROY';
#	warn "AUTOLOAD: $AUTOLOAD(" . join(', ', @_) . ")\n";

	# no object? Then we're trying to call a normal function somewhere in this class file
	if (!defined $self) {
		my ($pkg,$filename,$line) = caller;
		die("Undefined subroutine $var called at $filename line $line.\n");
	}

	return scalar @_ ? $self->_set($var, @_) : $self->_get($var);
}

1;
