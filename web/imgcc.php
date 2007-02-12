<?php
/*
	Displays a piechart showing the breakdown of the various countries that players are from
*/
define("VALID_PAGE", 1);
include(dirname(__FILE__) . '/includes/imgcommon.php');
include(JPGRAPH_DIR . '/jpgraph_pie.php');


$imgfilename = 'auto';
$data = array();
$labels = array();






if (!isImgCached($imgfilename)) {
	$timeframe = imgconf('ccimg attr.timeframe', '0');
	$total = imgconf('ccimg.slices attr.total', '10');
	if (!is_numeric($total) or $total < 1 or $total > 20) $total = 10;

	// total profiles with a non-empty country code
#	list($total) = $ps_db->fetch_row(0, "SELECT COUNT(cc) FROM $ps->t_plr_profile WHERE cc != ''");
#	if (!$total) $total = 1;

	// top 10 countries by count
#	if ($timeframe) {
#		$ps_db->query("SELECT pp.cc,cn,COUNT(pp.cc) FROM $ps->t_plr_profile pp, $ps->t_geoip_cc cc WHERE pp.cc != '' AND cc.cc=pp.cc GROUP BY pp.cc ORDER BY 3 DESC LIMIT $total");
#	} else {
		$ps_db->query("SELECT pp.cc,cn,COUNT(pp.cc) FROM $ps->t_plr_profile pp, $ps->t_geoip_cc cc WHERE pp.cc != '' AND cc.cc=pp.cc GROUP BY pp.cc ORDER BY 3 DESC LIMIT $total");
#	}
#	print $ps_db->lastcmd;
	while (list($cc,$cn,$cctotal) = $ps_db->fetch_row(0)) {
		$data[] = $cctotal;
		$labels[] = '(' . strtoupper($cc) . ") $cn";
	}

	// total of unknown CC's
/**
	$ps_db->query("SELECT COUNT(*) FROM $ps->t_plr_profile pp WHERE cc IN ('','00') LIMIT 1");
	list($cc,$cctotal) = $ps_db->fetch_row(0);
	$data[] = $cctotal;
	$labels[] = '?';
/**/

	// if we have no country data show a 100% unknown slice
	if (!count($data)) {
		$data[] = 1;
		$labels[] = 'unknown';
	}
}

//$graph = new PieGraph(375, 285, $imgfilename, CACHE_TIMEOUT);
$graph = new PieGraph(600, 300, $imgfilename, CACHE_TIMEOUT);
if (imgconf('ccimg attr.antialias', 0)) $graph->SetAntiAliasing();
$graph->SetColor(imgconf('ccimg attr.margin', '#d7d7d7'));

$graph->title->Set(imgconf('ccimg.frame.title', 'Breakdown of Countries'));
$graph->title->SetFont(constant(imgconf('ccimg.frame.title attr.font', 'FF_FONT1')),FS_BOLD);
//$graph->subtitle->Set("(Excludes unknown)");
//$graph->subtitle->SetFont(FF_FONT0,FS_NORMAL);

$p1 = new PiePlot($data);
$p1->ExplodeSlice(0);		// make the largest slice explode out from the rest
#$p1->ExplodeAll();
//$p1->SetStartAngle(45); 
if (imgconf('ccimg.slices attr.border', 0) == 0) $p1->ShowBorder(false,false);
$p1->SetGuideLines();
$p1->SetCenter(0.35);
$p1->SetTheme(imgconf('ccimg.slices attr.theme', 'earth'));
$p1->SetLegends($labels);
$p1->SetGuideLinesAdjust(1.1);


$graph->SetBackgroundGradient(
	imgconf('ccimg.frame attr.color1', 'gray'),
	imgconf('ccimg.frame attr.color2', 'whitesmoke'),
	constant(imgconf('ccimg.frame attr.type', 'GRAD_LEFT_REFLECTION')),
	constant(imgconf('ccimg.frame attr.style', 'BGRAD_MARGIN'))
); 
$graph->SetFrame(false);

$graph->Add($p1);

/*
if (count($data)) {
	$t = new Text("Not enough history\navailable\nto display piechart");
	$t->SetPos(0.5,0.5,'center','center');
	$t->SetFont(FF_FONT2, FS_BOLD);
	$t->ParagraphAlign('centered');
	$t->SetBox('lightyellow','black','gray');
	$t->SetColor('orangered4');
//	$graph->legend->Hide();
	$graph->AddText($t);
}
*/

stdImgFooter($graph);
$graph->Stroke();

?>
