<?php 
// not used

function gradientRange($low, $high, $totalsteps) {
	$steps = $totalsteps;
	$inc = ($high - $low) / $steps;
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

function hsvGradient($low, $high, $iterations=2, $lightness=1) {
	if ($iterations < 1) $iterations = 1;
	$r1 = $low >> 16;
	$g1 = ($low & 0x00FF00) >> 8;
	$b1 = $low & 0x0000FF;
	$r2 = $high >> 16;
	$g2 = ($high & 0x00FF00) >> 8;
	$b2 = $high & 0x0000FF;
	$c1 = array($r1,$g1,$b1);
	$c2 = array($r2,$g2,$b2);

	$colors = array();
	$hdiff = 0;
	$hx = 0;
	$bx = 0;
	if ($lightness) {
		$ch1 = rgb2hsv($c1);
		$ch2 = rgb2hsv($c2);

		$hdiff = ($ch2[0] - $ch1[0]);
		$hx = $hdiff / $iterations;

		$bdiff = ($ch2[2] - $ch1[2]);
		$bx = $bdiff / $iterations;

		// if (($ch1[1] == $ch2[1]) || !$ch2[1] || !$ch1[1]){
		if (!$ch2[1] || !$ch1[1]){
			$hdiff = 0;
		}
	}

	$ary = array();
	for ($x=0; $x < $iterations; $x++) {
		$col = array();
		$newcolor = array();
		if (!$hdiff) {
			$col[0] = $red_steps * $x;
			$col[1] = $green_steps * $x;
			$col[2] = $blue_steps * $x;

			// Loop through each R, G, and B
			for ($i = 0; $i < 3; $i++) {
				$partcolor = $c1[$i] + $col[$i];
				if ($partcolor < 256) {
					$newcolor[$i] = ($partcolor > -1) ? $partcolor : 0;
				} else {
					$newcolor[$i] = 255;
        		        }
			}

			$cl1 = array(
				round($newcolor[0]),
				round($newcolor[1]),
				round($newcolor[2])
                        );
			$mod = $lightness <= ( 255 - array_sum($cl1) / 3 ) ? 1 : -1;

			if ($lightness) {
				$xxx = ($degrees-1) / 2;
				$delta = ($degrees - abs($xxx - $x)) - $xxx;
				$pct = $delta / $degrees * $mod;
				array_walk($newcolor, 'changeLightness', $pct*100);
			}

			$colors[] = sprintf('%02X%02X%02X',$newcolor[0],$newcolor[1],$newcolor[2]);

		} else {
			$hshift += $hx;
			$bshift += $bx;
			$newcolor = hsv2rgb($ch1[0] + $hshift, $ch1[1], $ch1[2] + $bshift);
			$colors[] = sprintf('%02X%02X%02X',$newcolor[0],$newcolor[1],$newcolor[2]);
		}
	}
	return $colors;
}

function rgb2hsv($color) {
	list($var_R, $var_G, $var_B) = $color;

	$var_Min = min($var_R, $var_G, $var_B);
	$var_Max = max($var_R, $var_G, $var_B);
	$del_Max = $var_Max - $var_Min;

	// $V = ($var_Max + $var_Min) / 2;
	$V = $var_Max;

	if ($del_Max == 0) {
		$H = 0;
		$S = 0;
	} else {
		$S = $del_Max / ($var_Max + $var_Min);

		$del_R = ((($var_Max-$var_R)/6)+($del_Max/2))/$del_Max;
		$del_G = ((($var_Max-$var_G)/6)+($del_Max/2))/$del_Max;
		$del_B = ((($var_Max-$var_B)/6)+($del_Max/2))/$del_Max;

		if ($var_R == $var_Max) $H = $del_B - $del_G;
			else if ($var_G == $var_Max) $H = (1 / 3) + $del_R - $del_B;
			else if ($var_B == $var_Max) $H = (2 / 3) + $del_G - $del_R;

		if ($H < 0) $H += 1;
		if ($H > 1) $H -= 1;
	}

	$H = round($H * 360);
	$S = round($S * 100);
	$V = round($V * 100);
	return array($H, $S, $V);
}

function changeLightness(&$xx, $yy, $degree=0) {
	if ($xx + $degree < 256) {
		if ($xx + $degree > -1) {
			$xx += $degree;
		} else {
			$xx = 0;
		}
	} else {
		$xx = 255;
	}
}

function hsv2hex($h, $s, $v) {
	$s /= 100;
	$v /= 100;
	$h /= 360;

	if ($s == 0) {
		$r = $g = $b = $v;
		return sprintf('%02X%02X%02X',$r,$g,$b);
	} else {
		$h = $h * 6;
		$i = floor($h);
		$f = $h - $i;
		$p = (integer)($v * (1.0 - $s));
		$q = (integer)($v * (1.0 - $s * $f));
		$t = (integer)($v * (1.0 - $s * (1.0 - $f)));

		switch($i) {
		case 0:
			$r = $v;
			$g = $t;
			$b = $p;
			break;
		case 1:
			$r = $q;
			$g = $v;
			$b = $p;
			break;
		case 2:
			$r = $p;
			$g = $v;
			$b = $t;
			break;
		case 3:
			$r = $p;
			$g = $q;
			$b = $v;
			break;
		case 4:
			$r = $t;
			$g = $p;
			$b = $v;
			break;
		default:
			$r = $v;
			$g = $p;
			$b = $q;
			break;
		}
	}
	return sprintf('%02X%02X%02X',$r,$g,$b);
}

function hex2rgb($hex) {
        $hex = str_replace('#', '', $hex);
	$r = hexdec( substr( $hex, 0, 2 ) );
	$g = hexdec( substr( $hex, 2, 2 ) );
	$b = hexdec( substr( $hex, 4, 2 ) );
	return $return;
}

function hsv2rgb($h, $s, $v) {
	return hex2rgb(hsv2hex($h, $s, $v));
}
?>
