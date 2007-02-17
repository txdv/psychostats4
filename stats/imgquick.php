<?php
/*
	Displays a graph that shows the skill history of the player ID given
*/
define("VALID_PAGE", 1);
include(dirname(__FILE__) . '/includes/imgcommon.php');
include(JPGRAPH_DIR . '/jpgraph_line.php');

$plrid = is_numeric($_GET['plrid']) ? $_GET['plrid'] : 0;
$var = in_array(strtolower($_GET['v']), array('skill','kills','onlinetime')) ? strtolower($_GET['v']) : 'skill';
//if ($var == 'skill') $var = 'dayskill';
$_GET = array( 'plrid' => $plrid, 'v' => $var );

//list($base,$ext) = explode('.', GenImgName());
//$imgfilename = $base . "_" . $plrid . '.' . $ext;
$imgfilename = 'auto';
$data = array();
$labels = array();
$sum = 0;
$avg = 0;
$interval = 0;
$minlimit = 0;
$maxlimit = 0;

$max = 30;	// more than 30 starts to look ugly


if (!isImgCached($imgfilename)) {
	$field = $var == 'skill' ? 'dayskill' : $var;
	$ps_db->query("SELECT statdate,$field FROM $ps->t_plr_data WHERE plrid='" . $ps_db->escape($plrid) . "' ORDER BY statdate LIMIT $max");
	while (list($statdate,$skill) = $ps_db->fetch_row(0)) {
		$skill = round($skill);
		$sum += $skill;
		$data[] = $skill;
		$labels[] = $statdate;
	}

	// DEBUG
/*
	while (count($data) < 30) {
		$totalforday = rand(5000,10000);
		$sum += $totalforday;
		$data[] = $totalforday;
		$labels[] = $labels[0];
	}
/**/
}

// Not enough data to produce a proper graph
// jpgraph will crash if we give it an empty array
if (!count($data)) {
	$sum = 0;
	$data[] = 0;
} elseif (count($data) == 1) {
	$data[1] = $data[0];
}

// calculate the average of our dataset
if (count($data)) {
	$avg = $sum / count($data);

	if ($var == 'skill') {
		$interval = imgconf('quickimg attr.interval', 0);
	}

	if ($interval) {
		$minlimit = floor(min($data) / $interval) * $interval;
		$maxlimit = ceil(max($data) / $interval) * $interval;
	}
}

// Setup the graph.
$graph = new Graph(240,160,$imgfilename, CACHE_TIMEOUT);
if ($interval) {
	$graph->SetScale("textlin", $minlimit, $maxlimit);
} else {
	$graph->SetScale("textlin");
}
$graph->SetMargin(45,10,10,20);
$graph->title->Set(imgconf('quickimg.frame.title', 'Quick History'));

//$graph->yaxis->HideZeroLabel(); 
if ($var != 'onlinetime') {
	$graph->yaxis->SetLabelFormat('%d'); 
} else {
	$graph->yaxis->SetLabelFormatCallback('conv_onlinetime');
}

if (count($data)<2 or !imgconf('quickimg.frame.xgrid attr.show', true)) {
	$graph->xaxis->Hide();
}
$graph->xaxis->HideLabels();

$graph->ygrid->SetFill((bool)imgconf('quickimg.frame.ygrid attr.show', true),
	imgconf('quickimg.frame.ygrid attr.color1', 'whitesmoke'),
	imgconf('quickimg.frame.ygrid attr.color2', 'azure2')
);
$graph->ygrid->Show((bool)imgconf('quickimg.frame.ygrid attr.show', true));

$font1 = constant(imgconf('quickimg.frame.title attr.font', 'FF_FONT1'));
$legendfont = constant(imgconf('quickimg.legend attr.font', 'FF_FONT0'));
$graph->title->SetFont($font1, FS_BOLD);
$graph->yaxis->title->SetFont($font1,FS_BOLD);
$graph->legend->SetFont($legendfont,FS_NORMAL);
#$graph->xaxis->title->SetFont($font1,FS_BOLD);
#$graph->xaxis->SetFont(FF_FONT0,FS_NORMAL);

$graph->SetMarginColor(imgconf('quickimg attr.margin', '#d7d7d7')); 
$graph->SetFrame(true,imgconf('quickimg.frame attr.color', 'gray'), imgconf('quickimg.frame attr.width', 1)); 
if (imgconf('quickimg attr.antialias', 0)) $graph->img->SetAntiAliasing();

$p1 = new LinePlot($data);
$p1->SetLegend(ucfirst($var));
$p1->SetWeight(imgconf('quickimg.frame.plot.0 attr.weight', 1));
//$p1->mark->SetType(constant(imgconf('quickimg.frame.plot.0 attr.mark', 'MARK_CROSS')));
$p1->SetFillColor(imgconf('quickimg.frame.plot.0 attr.color', 'blue@0.90'));

$avg = intval($avg);
if ($avg) {
	for ($i=0; $i < count($data); $i++) {
		$avgdata[] = $avg;
	}

	$p2 = new LinePlot($avgdata);
//	$p2->SetStyle('dashed');
	$p2->SetLegend(imgconf('quickimg.frame.plot.1', 'Average'));
	$p2->SetWeight(imgconf('quickimg.frame.plot.1 attr.weight', 2));
	$p2->SetColor(imgconf('quickimg.frame.plot.1 attr.color', 'khaki4'));
//	$p2->SetBarCenter();
	$graph->Add($p2);
}

$graph->legend->SetAbsPos(
	imgconf('quickimg.legend attr.x', '5'),
	imgconf('quickimg.legend attr.y', '5'),
	imgconf('quickimg.legend attr.halign', 'right'),
	imgconf('quickimg.legend attr.valign', 'top')
);
$graph->legend->SetFillColor(imgconf('quickimg.legend attr.color', 'lightblue@0.5'));
$graph->legend->SetShadow(
	imgconf('quickimg.legend.shadow attr.color', 'gray@0.5'),
	imgconf('quickimg.legend.shadow attr.width', '2')
);

$graph->Add($p1);

if (count($data) < 2) {
	$t = new Text("Not enough history\navailable\nto chart graph");
	$t->SetPos(0.5,0.5,'center','center');
	$t->SetFont(FF_FONT2, FS_BOLD);
	$t->ParagraphAlign('centered');
	$t->SetBox('lightyellow','black','gray');
	$t->SetColor('orangered4');
	$graph->yaxis->HideLabels();
	$graph->xaxis->HideLabels();
	$graph->legend->Hide();
	$graph->AddText($t);
}

stdImgFooter($graph);
$graph->Stroke();

function conv_onlinetime($time) {
	return compacttime($time,'hh:mm');
}

?>
