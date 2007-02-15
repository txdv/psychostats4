<?php
define("VALID_PAGE", 1);
include(dirname(__FILE__) . "/includes/common.php");

$validfields = array('id', 'sort', 'order', 'start', 'limit', 'maxmaps', 'themefile');
globalize($validfields);

$sort = strtolower($sort);
$order = strtolower($order);
if (!preg_match('/^\w+$/', $sort)) $sort = 'kills';
if (!in_array($order, array('asc','desc'))) $order = 'desc';
if (!is_numeric($start) || $start < 0) $start = 0;
if (!is_numeric($limit) || $limit < 0) $limit = 10;
if (!is_numeric($maxmaps) || $maxmaps < 0) $maxmaps = 50;

foreach ($validfields as $var) {
	$data[$var] = $$var;
}

if (empty($themefile) or !$ps->conf['theme']['allow_user_change']) $themefile = 'map';

$data['toptenlimit'] = $limit;
$data['totalmaps'] = $ps->get_total_maps(array(), $smarty);

$data['maps'] = $ps->get_map_list(array(
	'sort'		=> 'kills',
	'order'		=> 'desc',
	'start'		=> 0,
	'limit'		=> $maxmaps,
), $smarty);

$map = $ps->get_map(array( 
	'mapid' => $id 
), $smarty);

$data['teamblockfile'] = $smarty->get_block_file('block_team');
$data['toptenblockfile'] = $smarty->get_block_file('block_map_topten');
$data['map'] = $map;

if ($map['mapid'] and $data['toptenblockfile']) {
	// generic vars that will work for any MODTYPE
	$vars = array('kills', 'ffkills','onlinetime');
	$data['toptendesc'] = array(
		'kills'		=> array( 'label' => $ps_lang->trans("Most Kills")),
		'ffkills'	=> array( 'label' => $ps_lang->trans("Most FF Kills")),
		'onlinetime'	=> array( 'label' => $ps_lang->trans("Most Online Time"), 'modifier' => 'compacttime'),
	);

	// vars for halflife
	if ($ps->conf['main']['gametype'] == 'halflife') {
		$modtype = $ps->conf['main']['modtype'];
		// cstrike
		if ($modtype == 'cstrike') {
			$prefix = substr($map['uniqueid'], 0, 3);
			if ($prefix == 'cs_') {
				$vars = array_merge($vars, array('rescuedhostages', 'touchedhostages', 'killedhostages'));
				$data['toptendesc'] += array(
					'touchedhostages'	=> array( 'label' => $ps_lang->trans("Most Hostages Touched")),
					'rescuedhostages'	=> array( 'label' => $ps_lang->trans("Most Hostages Rescued")),
					'killedhostages'	=> array( 'label' => $ps_lang->trans("Most Hostages Killed")),
				);
			} elseif ($prefix == 'de_') {
				$vars = array_merge($vars, array('bombdefused', 'bombexploded', 'bombplanted', 'bombrunner'));
				$data['toptendesc'] += array(
					'bombdefused'	=> array( 'label' => $ps_lang->trans("Most Bombs Defused")),
					'bombexploded'	=> array( 'label' => $ps_lang->trans("Most Bombs Exploded")),
					'bombplanted'	=> array( 'label' => $ps_lang->trans("Most Bombs Planted")),
					'bombrunner'	=> array( 'label' => $ps_lang->trans("Most Active Bomb Runner")),
				);
			} elseif ($prefix == 'as_') {
				$vars = array_merge($vars, array('vip', 'vipescaped', 'vipkilled'));
			} 

		// dod
		} elseif ($modtype == 'dod') {
			$vars = array_merge($vars, array('alliesflagscaptured', 'alliesareascaptured', 'axisflagscaptured'));
			$vars = array_merge($vars, array('axisareascaptured', 'flagscaptured', 'areascaptured'));
			$data['toptendesc'] += array(
				'alliesflagscaptured'	=> array( 'label' => $ps_lang->trans("Most Ally flags captured")),
				'alliesareascaptured'	=> array( 'label' => $ps_lang->trans("Most Ally areas captured")),
				'axisflagscaptured'	=> array( 'label' => $ps_lang->trans("Most Axis flags captured")),
				'axisareascaptured'	=> array( 'label' => $ps_lang->trans("Most Axis areas captured")),
				'flagscaptured'		=> array( 'label' => $ps_lang->trans("Most flags captured")),
				'areascaptured'		=> array( 'label' => $ps_lang->trans("Most flags captured")),
			);
		}

		// natural
		} elseif ($modtype == 'natural') {
			$vars = array_merge($vars, array('structuresbuilt', 'structuresdestroyed', 'structuresrecycled'));
			$data['toptendesc'] += array(
				'structuresbuilt'	=> array( 'label' => $ps_lang->trans("Structures Built")),
				'structuresdestroyed'	=> array( 'label' => $ps_lang->trans("Structures Destroyed")),
				'structuresrecycled'	=> array( 'label' => $ps_lang->trans("Structures Recycled")),
			);
		}
	}

	// load the top10 data for each $var discovered above ...
	foreach ($vars as $v) {
		$list = $ps->get_map_player_list(array(
			'mapid' 	=> $id,
			'sort'		=> $v,
			'order'		=> 'desc',
			'limit'		=> $limit,
			'fields'	=> $v,
			'where'		=> sprintf("%s > 0", $ps->db->qi($v)),
		), $smarty);
		if (count($list)) {
			$data["topten"][$v] = $list;
		}
	}
}

$smarty->assign($data);

if ($data['map']['mapid']) {
	$smarty->parse($themefile);
} else {
	$smarty->assign(array(
		'errortitle'	=> $ps_lang->trans("No Map Found!"),
		'errormsg'	=> $ps_lang->trans("No map matches your search criteria"),
		'redirect'	=> "<a href='maps.php'>" . $ps_lang->trans("Return to the maps list") . "</a>",
	));
	$smarty->parse('nomatch');
}
ps_showpage($smarty->showpage());

include(PS_ROOTDIR . "/includes/footer.php");
?>
