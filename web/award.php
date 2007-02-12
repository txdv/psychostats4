<?php
define("VALID_PAGE", 1);
require(dirname(__FILE__) . "/includes/common.php");
include(PS_ROOTDIR . "/includes/class_calendar.php");

$maxdaterange = 45;	// # of days to go forward/backward for the date listing

$validfields = array('id','sort','order','start','limit','themefile');
globalize($validfields);

$sort = strtolower($sort);
$order = strtolower($order);
if (!preg_match('/^\w+$/', $sort)) $sort = 'value';
if (!in_array($order, array('asc','desc'))) $order = 'desc';
if (!is_numeric($start) || $start < 0) $start = 0;
if (!is_numeric($limit) || $limit < 0) $limit = 100;



foreach ($validfields as $var) {
  $data[$var] = $$var;
}

if (empty($themefile) or !$ps->conf['theme']['allow_user_change']) $themefile = 'award';

$award = $ps->get_award(array(
	'id' 	=> $id
), $smarty);

$plrlist = array();
$plr = array();
if ($award['id']) {
	$plrlist = $ps->get_award_player_list(array(
		'id'	=> $award['id'],
		'sort'	=> 'value',
		'order'	=> $award['order'],
		'limit' => $award['limit'],
	), $smarty);

	$plr = $ps->get_player(array(
		'plrid'		=> $award['topplrid'],
		'loadsessions'	=> 0,
		'loadmaps'	=> 0,
		'loadroles'	=> 0,
		'loadweapons'	=> 0,
		'loadvictims'	=> 0,
		'loadids'	=> 0,
		'loadipaddrs'	=> 0,
		'loadcounts'	=> 0,
	), $smarty);
}

// load a range of dates that this award also falls on (+/- 1 month)
$now = ymd2time($award['awarddate']);
$low = time2ymd($now - 60*60*24*$maxdaterange);
$high = time2ymd($now + 60*60*24*$maxdaterange);
$ranges = $ps_db->fetch_rows(1,
	"SELECT a.*,pp.name FROM $ps->t_awards a " .
	"LEFT JOIN $ps->t_plr p ON p.plrid=a.topplrid " .
	"LEFT JOIN $ps->t_plr_profile pp ON p.uniqueid=pp.uniqueid " .
	"WHERE awardname='" . $ps_db->escape($award['awardname']) . "' " .
	"AND awardid='" . $ps_db->escape($award['awardid']) . "' " .
	"AND (awarddate BETWEEN '$low' AND '$high') " .
	"ORDER BY awarddate DESC "
);
$datelist = array();
/*
foreach ($ranges as $r) {
	$datelist[ $r['awardrange'] ][] = array(
		'id'	=> $r['id'],
		'time'	=> ymd2time($r['awarddate']),
		'date'	=> $r['awarddate'],
		'week'	=> date('W', ymd2time($r['awarddate'])),
		'y'	=> substr($r['awarddate'],0,4),
		'm'	=> substr($r['awarddate'],5,2),
		'd'	=> substr($r['awarddate'],8,2),
	);
}
/**/
foreach ($ranges as $r) {
	$time = ymd2time($r['awarddate']);
	$datelist[ date("F",$time) ][] = array(
		'time'	=> $time,
		'date'	=> $r['awarddate'],
		'week'	=> date('W', $time),
		'y'	=> substr($r['awarddate'],0,4),
		'm'	=> substr($r['awarddate'],5,2),
		'd'	=> substr($r['awarddate'],8,2),
	) + $r;
}
/**/
//print_r($datelist);

$awardlist = array();
$cmd  = "SELECT a.*, ac.format, ac.desc, ac.groupname, plr.*, pp.* ";
$cmd .= "FROM ($ps->t_awards a, $ps->t_config_awards ac) ";
$cmd .= "LEFT JOIN $ps->t_plr plr ON plr.plrid=a.topplrid ";
$cmd .= "LEFT JOIN $ps->t_plr_profile pp ON pp.uniqueid=plr.uniqueid ";
$cmd .= "WHERE awarddate='" . $award['awarddate'] . "' AND awardtype='" . $award['awardtype'] . "' ";
$cmd .= "AND awardweapon='" . $award['awardweapon'] . "' AND awardrange='" . $award['awardrange'] . "' ";
$cmd .= "AND topplrid != 0 ";
$cmd .= "AND ac.id=a.awardid ";
$cmd .= "ORDER BY awardname ";
$awardlist = $ps_db->fetch_rows(1, $cmd);
//print "explain " . $ps_db->lastcmd . ";";

$cal = new Calendar($award['awarddate']);
$cal->set_conf(array('show_timeurl' => false));
$cal->day($award['awarddate'], array('link'=> '#'));

$data['calendar'] = $cal->draw();
$data['award'] = $award;
$data['datelist'] = $datelist;
$data['playerlist'] = $plrlist;
$data['awardlist'] = $awardlist;
$data['topplr'] = $plr;
$data['maxdaterange'] = $maxdaterange;

$time = ymd2time($award['awarddate']);
if ($award['awardrange'] == 'month') {
	$data['awardtitle'] = sprintf($ps_lang->trans("Monthly Award for %s in %s"), 
		$award['awardname'], 
		date("F Y", $time)
	);
} elseif ($award['awardrange'] == 'week') {
	$data['awardtitle'] = sprintf($ps_lang->trans("Week #%d Award for %s on %s"), 
		date("W", $time), 
		$award['awardname'],
		date("F j, Y", $time)
	);
} else {
	$data['awardtitle'] = sprintf($ps_lang->trans("Daily Award for %s on %s"), 
		$award['awardname'],
		date("D, F j, Y", $time)
	);
}

$smarty->assign($data);
if ($data['award']['awardid']) {
  $smarty->parse($themefile);
} else {
  $smarty->assign(array(
	'errortitle'	=> $ps_lang->trans("No Award Found!"),
	'errormsg'	=> $ps_lang->trans("No award matches your search criteria"),
	'redirect'	=> "<a href='awards.php'>" . $ps_lang->trans("Return to the awards list") . "</a>",
  ));
  $smarty->parse('nomatch');
}
ps_showpage($smarty->showpage());

include(PS_ROOTDIR . "/includes/footer.php");
?>
