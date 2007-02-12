<?php
define("VALID_PAGE", 1);
include(dirname(__FILE__) . "/includes/common.php");
include(PS_ROOTDIR . "/includes/class_calendar.php");

$validfields = array('themefile','time','y','m','d');
globalize($validfields);

if (empty($themefile) or !$ps->conf['theme']['allow_user_change']) $themefile = 'awards';

$type = 'player';

// get all available award dates (newest first)
$dates = $ps_db->fetch_list("select distinct awarddate from $ps->t_awards WHERE awardtype='$type' AND topplrid != 0 order by awarddate DESC");

$hadnotime = !is_numeric($time);

// $time is needed because thats what the calendar class uses to prev/next months
if (!is_numeric($time)) $time = time();

if (!is_numeric($y)) $y = date('Y',$time);
if (!is_numeric($m)) $m = date('m',$time);
if (!is_numeric($d)) $d = '01'; //date('d',$time);
$date = sprintf("%04d-%02d-%02d",$y,$m,$d);

// select the newest monthly award day (or weekly if there are no monthly)
if (count($dates) and !in_array($date, $dates)) {
	if ($hadnotime) {
		$list = $ps_db->fetch_list("select awarddate from $ps->t_awards where awardrange='month' AND awardtype='$type' AND topplrid != 0 order by awarddate DESC limit 1");
		$date = count($list) ? $list[0] : $dates[0];
	} else {
		$low = sprintf("%04d-%02d-%02d",$y,$m,1);
		$high = sprintf("%04d-%02d-%02d",$y,$m, date('t', $time));
		$list = $ps_db->fetch_list("select awarddate from $ps->t_awards where (awarddate BETWEEN '$low' and '$high') AND awardtype='$type' AND topplrid != 0 order by awarddate limit 1");
		$date = count($list) ? $list[0] : $low;
	}
	list($y,$m,$d) = split('-', $date);
}

$cal = new Calendar($date);
$cal->set_conf(array(
	'timeurl_callback' => 'cal_timeurl'
));

$awardlist = array();
$cmd  = "SELECT a.*, ac.format, ac.desc, plr.*, pp.* FROM ($ps->t_awards a, $ps->t_config_awards ac) ";
$cmd .= "LEFT JOIN $ps->t_plr plr ON plr.plrid=a.topplrid ";
$cmd .= "LEFT JOIN $ps->t_plr_profile pp ON pp.uniqueid=plr.uniqueid ";
$cmd .= "WHERE awarddate='$date' AND awardtype='$type' ";
$cmd .= "AND topplrid != 0 ";
$cmd .= "AND ac.id=a.awardid ";
$cmd .= "ORDER BY awardname ";
$awardlist = $ps_db->fetch_rows(1, $cmd);

if (!empty($awardlist)) {
	$range = $awardlist[0]['awardrange'];
	$data['awardlisttitle'] = ($type == 'player' ? $ps_lang->trans("Player") : $ps_lang->trans("Weapon")) . " ";
	$t = $cal->ymd2time($date);
	if ($range == 'month') {
		$data['awardlisttitle'] .= $ps_lang->trans("Awards for") . date(" F Y", $t);
	} elseif ($range == 'week') {
		$data['awardlisttitle'] .= $ps_lang->trans("Awards for week") . date(" #W: M j, Y", $t);
	} else {
		$data['awardlisttitle'] .= $ps_lang->trans("Awards for day") . date(" #z: D M j", $t);
	}
} else {
	$data['awardlisttitle'] .= $ps_lang->trans("Awards");
}

// get a list of award ranges available for the current month
$low = sprintf("%04d-%02d-%02d",$y,$m,1);
$high = sprintf("%04d-%02d-%02d",$y,$m, date('t', $time));
$ranges = $ps_db->fetch_rows(1, 
	"SELECT awardrange,awarddate,count(*) total FROM $ps->t_awards a " .
	"WHERE (awarddate BETWEEN '$low' AND '$high') " .
	"AND awardtype='$type' " . 
	"AND topplrid != 0 " .
	"GROUP BY awardrange,awarddate " .
	"ORDER BY awarddate"
);

$datelist = array();
foreach ($ranges as $r) {
	$datelist[ $r['awardrange'] ][] = array(
		'total' => $r['total'],
		'time'	=> $cal->ymd2time($r['awarddate']),
		'date'	=> $r['awarddate'],
		'week'	=> date('W', $cal->ymd2time($r['awarddate'])),
		'y'	=> substr($r['awarddate'],0,4),
		'm'	=> substr($r['awarddate'],5,2),
		'd'	=> substr($r['awarddate'],8,2),
	);
}

// populate calendar days that have awards
foreach ($dates as $day) {
	list($_y,$_m,$_d) = split('-', $day);
	$cal->day($day, array(
		'link'  => ps_url_wrapper(array('_base' => $PHP_SELF, 'y' => $_y, 'm' => $_m, 'd' => $_d)),
	));
}


$data['calendar'] = $cal->draw();
$data['awardlist'] = $awardlist;
$data['datelist'] = $datelist;
$data['date'] = $date;
$data['time'] = $cal->ymd2time($date);
$data['type'] = $type;
$data['y'] = $y;
$data['m'] = $m;
$data['d'] = $d;

$data['PAGE'] = 'awards';
$smarty->assign($data);
$smarty->parse($themefile);
ps_showpage($smarty->showpage());

include(PS_ROOTDIR . "/includes/footer.php");

function cal_timeurl(&$cal, $time) {
	global $PHP_SELF;
	return ps_url_wrapper(array( '_base' => $PHP_SELF, 'time' => $time ));
}
?>
