<?php
define("VALID_PAGE", 1);
include(dirname(__FILE__) . '/includes/common.php');
include(dirname(__FILE__) . '/includes/class_Color.php');

$validfields = array('themefile','c');
globalize($validfields);

foreach ($validfields as $var) {
	$data[$var] = $$var;
}

$ids = array();
$comparelist = array();
$gradient = array();

if (empty($themefile) or !$ps->conf['theme']['allow_user_change']) $themefile = 'compare';

$compare = $c;
if (!is_array($compare)) {
	$compare = explode(',',$compare);
}

// remove dups
$dup = array();
$list = array();
foreach ($compare as $c) {
	if (!array_key_exists($c, $dup) and is_numeric($c) and $c > 0) {
		$list[] = $c;
	} 
	$dup[$c]++;
}
$compare = $list;
unset($list);

$data['compare'] = $compare;
$ids = $compare;

$comparelist = array();		// holds gradient INDEXES for each stat[plrid]
$gradientlist = array();	// holds color values (int)
$players = array();
$statkeys = array( 
	'rank', 'skill', 'prevskill', 
	'kills', 'killsperdeath', 'killsperminute', 'ffkills', 'ffkillspct', 'kills_streak',
	'deaths_streak',
	'headshotkills', 'headshotpct', 
	'shotsperkill',
	'totalbonus', 'onlinetime',
);
$reversekeys = array(
	'rank','ffkills','ffkillspct','deaths_streak','shotsperkill'
);

if (count($ids) > 1) {
	$players = $ps->get_player_list(array(
		'sort'		=> 'rank',
		'order'		=> 'asc',
		'start'		=> 0,
		'limit'		=> 25,
		'joinclaninfo' 	=> 1,
		'where'		=> "plr.plrid IN (" . implode(',',$ids) . ")",
	), $smarty);
	//print $ps->db->lastcmd;
}
// spit out XML string and exit
if ($xml) print_xml($players);

if ($players) {
	// setup our shading gradient (best to worst)
//	$gradientlist = rgbGradient(0x00ff00, 0x000000, count($players));
//	$gradientlist = rgbGradient(0x00ff00, 0xDDDDDD, count($players));
//	$gradientlist = rgbGradient(0x00ff00, 0xff0000, count($players));
/*
	$gradientlist = rgbGradient(
		hexdec($ps->conf['theme']['format']['compare_grad1']), 
		hexdec($ps->conf['theme']['format']['compare_grad2']), 
		count($players)
	);
/**/
/**/
	$c = new Image_Color;
	$c->setColors($ps->conf['theme']['format']['compare_grad1'], $ps->conf['theme']['format']['compare_grad2']);
	$gradientlist = $c->getRange(count($players), 1);
/**/

	$ignore = array(	// keys that are not valid stats for comparison
		'plrid','uniqueid','firstseen','lastdecay','clanid','allowrank','dataid','statdate','id',
		'name','worldid','ipaddr','totaluses','userid','email','aim','icq','msn','website','icon',
		'cc','cn','logo','namelocked','clantag','locked','firstdate','lastdate','lasttime'
	);
#	$statkeys = array_diff(array_keys($players[0]), $ignore);
#	sort($statkeys);

	// build comparison array
	$stats = array();
	foreach ($statkeys as $stat) {				// loop through stat keys 
		$list = array();
		foreach ($players as $plr) {			// loop through players for each stat key
			$list[$plr['plrid']] = $plr[$stat];
		}
		asort($list, SORT_NUMERIC);
		if (!in_array($stat, $reversekeys)) $list = array_reverse($list, true);		// order stats from greatest to least

		$data['min'][$stat] = round(min(array_values($list)));
		$data['max'][$stat] = round(max(array_values($list)));
		$data['interval'][$stat] = $data['max'][$stat] - $data['min'][$stat];

		$last = null;
		$idx = -1;
		foreach ($list as $key => $value) {		// loop and assign gradients
			if ($last == null or $last != $list[$key]) {
				$idx++;
			}
			$last = $list[$key];
			$list[$key] = $idx;
		}
//		print_r($list); print "<br>"; //print(count(array_unique($list))); print "<br>";
		$comparelist[$stat] = $list;
	}

	list($data['min']['skill'],$data['max']['skill']) = 
		$ps_db->fetch_row(0,"SELECT min(dayskill),max(dayskill) FROM $ps->t_plr_data WHERE plrid IN (" . join(',',$ids) . ") ORDER BY statdate LIMIT 15");

}

$data['players'] = $players;
$data['totalplayers'] = count($players);
$data['statkeys'] = $statkeys;
$data['comparebodyfile'] = $smarty->get_block_file('compare_body');
$data['comparelist'] = $comparelist;
$data['gradientlist'] = $gradientlist;
$data['tablewidth'] = 100 * count($players);
$data['comparestr'] = implode(',',$compare);

$data['PAGE'] = 'compare';
$smarty->assign($data);
$smarty->parse($themefile);
ps_showpage($smarty->showpage());


include(PS_ROOTDIR . '/includes/footer.php');
?>
