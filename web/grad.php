<?php

$g = rgbGradient(0xFF0000,0x00FF00,10);

foreach ($g as $col) {
	printf("<div style='width: 100px; background: #%06X'>%03d: #%06X</div>\n", $col, 1+$i++, $col);
}

// returns a gradient array between 2 numbers
function gradient($low, $high, $totalsteps) {
	$steps = $totalsteps - 1;
	$dist = $high - $low;
	$inc = $dist / $steps;
	$value = $low;
	$ary = array();
	$ary[] = $low;
	for ($i=1; $i < $steps; $i++) {
		$value += $inc;
		$ary[] = $value;
	}
	$ary[] = $high;
	return $ary;
}

// returns the RGB gradient between 2 RGB pairs
function rgbGradient($low, $high, $totalsteps) {
	$r1 = $low >> 16;
	$g1 = ($low & 0x00FF00) >> 8;
	$b1 = $low & 0x0000FF;
	$r2 = $high >> 16;
	$g2 = ($high & 0x00FF00) >> 8;
	$b2 = $high & 0x0000FF;
	$r = gradient($r1, $r2, $totalsteps);
	$g = gradient($g1, $g2, $totalsteps);
	$b = gradient($b1, $b2, $totalsteps);
	$ary = array();
	for ($i=0; $i < count($r); $i++) {
		$ary[] = ($r[$i] << 16) | ($g[$i] << 8) | $b[$i];
	}
	return $ary;
}

?>
