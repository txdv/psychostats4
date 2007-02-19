<?php
/*
	Displays a graph that shows the total connections for the last 30 days
*/
define("VALID_PAGE", 1);
include(dirname(__FILE__) . '/includes/imgcommon.php');
include(JPGRAPH_DIR . '/jpgraph_bar.php');
include(JPGRAPH_DIR . '/jpgraph_line.php');

$imgfilename = 'auto';
$data = array();
$labels = array();
$sum = 0;
$avg = 0;
$maxconn = 0;

$max = 30;	// more than 30 starts to look ugly

if (!isImgCached($imgfilename)) {
//	$ps_db->query("SELECT statdate,SUM(connections),SUM(kills) FROM $ps->t_map_data GROUP BY statdate ORDER BY statdate LIMIT $max");
//	while (list($statdate,$totalforday,$killsforday) = $ps_db->fetch_row(0)) {
	$ps_db->query("SELECT statdate,SUM(connections) FROM $ps->t_map_data GROUP BY statdate ORDER BY statdate DESC LIMIT $max");
	while (list($statdate,$totalforday) = $ps_db->fetch_row(0)) {
		$sum += $totalforday;
		array_unshift($data, $totalforday);
		array_unshift($labels, $statdate);
//		array_unshift($kills, $killsforday);
		if ($totalforday > $maxconn) $maxconn = $totalforday;
	}

	// DEBUG
/*
	while (count($data) < 30) {
		$totalforday = rand(600,1000);
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
	$labels = array("");
}

// calculate the average of our dataset
if (count($data)) {
	$avg = $sum / count($data);
}

// Setup the graph.
$graph = new Graph(600,250,$imgfilename, CACHE_TIMEOUT);
$graph->SetScale("textlin");
//$graph->SetY2Scale("lin");
$graph->SetMargin(60,30,20,75);
$graph->title->Set(imgconf('connimg.frame.title', 'Connections Per Day'));

$graph->xaxis->SetTickLabels($labels);
$graph->xaxis->HideTicks();
$graph->xaxis->SetLabelAngle(90);

$graph->yaxis->HideZeroLabel(); 
//$graph->y2axis->HideZeroLabel(); 

$graph->ygrid->SetFill((bool)imgconf('connimg.frame.ygrid attr.show', true),
	imgconf('connimg.frame.ygrid attr.color1', 'whitesmoke@0.5'),
	imgconf('connimg.frame.ygrid attr.color2', 'lightblue@0.5')
);
$graph->ygrid->Show((bool)imgconf('connimg.frame.ygrid attr.show', true));

$font1 = constant(imgconf('connimg.frame.title attr.font', 'FF_FONT1'));
$legendfont = constant(imgconf('connimg.legend attr.font', 'FF_FONT1'));
$graph->legend->SetFont($legendfont,FS_NORMAL);
$graph->title->SetFont($font1, FS_BOLD);
$graph->yaxis->title->SetFont($font1,FS_BOLD);
$graph->xaxis->SetFont(FF_FONT0,FS_NORMAL);
//$graph->xaxis->title->SetFont($font1,FS_BOLD);

$graph->SetBackgroundGradient(
	imgconf('connimg.frame attr.color1', 'gray'),
	imgconf('connimg.frame attr.color2', 'whitesmoke'),
	constant(imgconf('connimg.frame attr.type', 'GRAD_LEFT_REFLECTION')),
	constant(imgconf('connimg.frame attr.style', 'BGRAD_MARGIN'))
); 
$graph->SetFrame(false);
//$graph->SetFrame(true,'gray',0); 
//$graph->SetShadow();

// Create the bar pot
$p1 = new BarPlot($data);
//$p1->SetFillGradient("lightgray","whitesmoke",GRAD_RAISED_PANEL);
//$p1->SetFillGradient("lightblue","lightgray",GRAD_HOR);
$p1->SetFillGradient(
	imgconf('connimg.frame.bar attr.color1', '#3658F5'),
	imgconf('connimg.frame.bar attr.color2', '#030C36'),
	constant(imgconf('connimg.frame.bar attr.type', 'GRAD_HOR'))
);
$p1->SetLegend(imgconf('connimg.frame.bar', 'Maximum') . " [$maxconn]");

$avg = intval($avg);
if ($avg) {
	$avgdata = array();
	for ($i=0; $i < count($data); $i++) {
		$avgdata[] = $avg;
	}

	$p2 = new LinePlot($avgdata);
//	$p2->SetStyle('dashed');
	$p2->SetLegend(imgconf('connimg.frame.plot', 'Average') . " [$avg]");
	$p2->SetWeight(imgconf('connimg.frame.plot attr.weight', 2));
	$p2->SetColor(imgconf('connimg.frame.plot attr.color', 'khaki4'));
	$p2->SetBarCenter();
	$graph->Add($p2);

	$graph->legend->SetAbsPos(
		imgconf('connimg.legend attr.x', 20),
		imgconf('connimg.legend attr.y', 15),
		imgconf('connimg.legend attr.halign', 'right'),
		imgconf('connimg.legend attr.valign', 'top')
	);
	$graph->legend->SetFillColor(imgconf('connimg.legend attr.color', 'lightblue@0.5'));
	$graph->legend->SetShadow(
		imgconf('connimg.legend.shadow attr.color', 'gray@0.5'),
		imgconf('connimg.legend.shadow attr.width', '2')
	);
}

$graph->Add($p1);

/*
$p3 = new LinePlot($kills);
$p3->SetBarCenter();
$p3->SetLegend("Kills");
$p3->SetFillColor('gray@0.65');

$graph->AddY2($p3);
/**/

if (!$sum) {
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

//if (imgconf('connimg attr.antialias', 0)) $graph->img->SetAntiAliasing();

stdImgFooter($graph);
$graph->Stroke();

?>
