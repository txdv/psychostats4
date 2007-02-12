<?php
/*

*/
define("VALID_PAGE", 1);
include(dirname(__FILE__) . '/includes/imgcommon.php');
include(JPGRAPH_DIR . '/jpgraph_line.php');

$plrid = is_numeric($_GET['plrid']) ? $_GET['plrid'] : 0;
//$_GET = array( 'plrid' => $plrid );

$imgfilename = 'auto';
$kills = array();
$hs = array();
$total = array();
$labels = array();
$sum = 0;
$avg = 0;

$max = 30;	// more than 30 starts to look ugly


if (!isImgCached($imgfilename)) {
	$t = 0;
	$ps_db->query("SELECT statdate,kills,headshotkills FROM $ps->t_plr_data WHERE plrid='" . $ps_db->escape($plrid) . "' ORDER BY statdate LIMIT $max");
	while (list($statdate,$k,$h) = $ps_db->fetch_row(0)) {
		$labels[] = $statdate;
		$kills[] = $k;
		$hs[] = $h;
		$t += $k + $h;
		$total[] = $t;
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
if (!count($kills)) {
	$kills[] = 0;
	$hs[] = 0;
	$total[] = 0;
}

// Setup the graph.
$graph = new Graph(240,160,$imgfilename, CACHE_TIMEOUT);
$graph->SetScale("textlin");
$graph->SetMargin(45,10,10,20);
$graph->title->Set("Kills");

//$graph->yaxis->HideZeroLabel(); 
$graph->yaxis->SetLabelFormat('%d'); 
$graph->xaxis->Hide();

$graph->ygrid->SetFill(true,'whitesmoke@0.5','lightblue@0.5');
$graph->ygrid->Show();

$graph->title->SetFont(FF_FONT1,FS_BOLD);
$graph->yaxis->title->SetFont(FF_FONT1,FS_BOLD);
$graph->xaxis->title->SetFont(FF_FONT1,FS_BOLD);
$graph->xaxis->SetFont(FF_FONT0,FS_NORMAL);
$graph->legend->SetFont(FF_FONT0,FS_NORMAL);

$graph->SetMarginColor('#d7d7d7'); 
$graph->SetFrame(true,'gray',0); 
//$graph->img->SetAntiAliasing();

$p1 = new LinePlot($kills);
$p2 = new LinePlot($hs);
$p3 = new LinePlot($total);

$p1->SetLegend("Kills");
$p2->SetLegend("Headshots");
$p3->SetLegend("Total");

$p1->SetFillColor("red@0.25");
$p2->SetFillColor("blue@0.50");
$p3->SetFillColor("green");

$graph->legend->SetPos(0.02,0.03,'right','top');
$graph->legend->SetFillColor('lightblue@0.50');
$graph->legend->SetShadow('gray@0.5');

$acc = new AccLinePlot(array($p1,$p2,$p3));

$graph->Add($acc);

if (count($kills) < 2) {
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

?>
