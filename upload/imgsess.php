<?php
/*
	Displays a graph that shows the total connections for the last 30 days
*/
define("PSYCHOSTATS_PAGE", true);
include(dirname(__FILE__) . "/includes/imgcommon.php");
include(JPGRAPH_DIR . '/jpgraph_gantt.php');

$plrid = is_numeric($_GET['id']) ? $_GET['id'] : 0;
$noname = isset($_GET['nn']);

$imgfilename = 'auto';
$data = array();
$labels = array();
$plrname = "Unknown";

$imgconf = array();
$s = array();

$showempty = false;

// how many days are allowed in the image.
$max = 14;

if (!isImgCached($imgfilename)) {
	$imgconf = load_img_conf();
	$s =& $imgconf['sessimg'];
	$showempty = (bool)imgdef($s['frame']['bar']['showempty'], false);

	if (!$noname) {
		$plrname = $ps->db->fetch_item("SELECT name FROM $ps->t_plr p, $ps->t_plr_profile pp WHERE p.uniqueid=pp.uniqueid AND p.plrid='" . $ps->db->escape($plrid) . "'");
	}
	$ps->db->query("SELECT sessionstart,sessionend FROM $ps->t_plr_sessions WHERE plrid='" . $ps->db->escape($plrid) . "' ORDER BY sessionstart DESC");
	$idx = 1;
	$d = array();
	while (list($start,$end) = $ps->db->fetch_row(0)) {
		if (count($d) >= $max) break;
		$d1 = date("Y-m-d", $start);
		$d2 = date("Y-m-d", $end);

		// fill in the gap from the current and previous dates
		if ($showempty and count($data) and $data[count($data)-1][1] > $d1) {
			$diff = floor(($data[count($data)-1][4] - $start) / (60*60*24));
			$empty = $data[count($data)-1][4];
			for ($i=0; $i < $diff; $i++) {
				if (count($d) >= $max) break;
				$empty = $empty - 60*60*24;
				if (!$d[time2ymd($empty)]) $d[time2ymd($empty)] = $idx++;
				$data[] = array(
					$d[time2ymd($empty)]-1,
					date("Y-m-d", $empty),
					'00:00',
					'00:00',
					$empty
				);
			}
		}

		// need to wrap the session to the next day
		if ($d2 > $d1) {
			if (!$d[$d2]) $d[$d2] = $idx++;
			$data[] = array(
				$d[$d2]-1,
				date("Y-m-d", $end),
				"00:00",
				date("H:i", $end),
				$start
			);
		}

		if (!$d[$d1]) $d[$d1] = $idx++;
		$data[] = array(
			$d[$d1]-1,
			date("Y-m-d", $start),
			date("H:i", $start),
			$d2 <= $d1 ? date("H:i", $end) : "23:59",
			$start
		);
	}
}

// remove any and all output buffers
while (@ob_end_clean());

$graph = new GanttGraph(0,0,$imgfilename, CACHE_TIMEOUT);

if ($noname) {
	$graph->setMargin(0,0,0,14);
}

//$graph->SetBackgroundGradient('gray','whitesmoke',GRAD_LEFT_REFLECTION,BGRAD_MARGIN); 
$graph->SetMarginColor(imgdef($s['frame']['margin'], '#C4C4C4')); 
$graph->SetFrame(true,imgdef($s['frame']['color'], 'gray'), imgdef($s['frame']['width'], 1)); 

$graph->ShowHeaders(GANTT_HHOUR);

if (!$noname) $graph->title->Set($plrname);
//$graph->title->SetColor('blue');
//$graph->subtitle->Set(imgdef($s['frame']['title']['_content'], 'Player Sessions'));
//$graph->subtitle->SetFont(constant(imgdef($s['frame']['font'], 'FF_FONT0')));

// must override the weekend settings ...
$graph->scale->UseWeekendBackground(false);
$graph->scale->day->SetWeekendColor(imgdef($s['frame']['header']['bgcolor'], 'lightyellow:1.5'));
$graph->scale->day->SetSundayFontColor(imgdef($s['frame']['header']['color'], 'black'));

// match the weekend settings ...
$graph->scale->hour->SetFontColor(imgdef($s['frame']['header']['color'], 'black')); 
$graph->scale->hour->SetBackgroundColor(imgdef($s['frame']['header']['bgcolor'], 'lightyellow:1.5'));

$graph->scale->hour->SetFont(FF_FONT1);
$graph->scale->hour->SetIntervall(2);
$graph->scale->hour->SetStyle(HOURSTYLE_HM24);

/**
$graph->scale->actinfo->SetBackgroundColor('lightyellow:1.5');
$graph->scale->actinfo->SetStyle(0);
$graph->scale->actinfo->SetFont(FF_FONT1);
$graph->scale->actinfo->SetColTitles(array("Day"));
/**/

$graph->hgrid->Show((bool)imgdef($s['frame']['hgrid']['show'], true));
if ((bool)imgdef($s['frame']['hgrid']['show'], true)) {
	$graph->hgrid->SetRowFillColor(
		imgdef($s['frame']['hgrid']['color1'],'whitesmoke@0.9'),
		imgdef($s['frame']['hgrid']['color2'],'darkblue@0.9')
	);
}

for($i=0; $i<count($data); ++$i) {
	$bar = new GanttBar($data[$i][0],$data[$i][1],$data[$i][2],$data[$i][3]);
	$bar->SetPattern(
		constant(imgdef($s['frame']['bar']['pattern'], 'BAND_RDIAG')),
		imgdef($s['frame']['bar']['patternfill'], 'lightblue')
	);
	if (imgdef($s['frame']['bar']['fill'], 'darkblue') != 'BAND_SOLID') {
		$bar->SetFillColor(imgdef($s['frame']['bar']['fill'], 'darkblue'));
	}
#	$bar->SetShadow(true, 'black@0.5');
	$graph->Add($bar);
}


stdImgFooter($graph);
$graph->Stroke();

?>
