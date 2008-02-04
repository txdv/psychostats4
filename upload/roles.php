<?php
define("PSYCHOSTATS_PAGE", true);
include(dirname(__FILE__) . "/includes/common.php");
$cms->init_theme($ps->conf['main']['theme'], $ps->conf['theme']);
$ps->theme_setup($cms->theme);

// change this if you want the default sort of the player listing to be something else like 'kills'
$DEFAULT_SORT = 'kills';

$validfields = array('sort','order','xml','v');
$cms->theme->assign_request_vars($validfields, true);

$v = strtolower($v);
$sort = trim(strtolower($sort));
$order = trim(strtolower($order));
$start = 0;
$limit = 100;
if (!preg_match('/^\w+$/', $sort)) $sort = $DEFAULT_SORT;
if (!in_array($order, array('asc','desc'))) $order = 'desc';

$stats = $ps->get_sum(array('kills','damage'), $ps->c_plr_data);

$roles = $ps->get_role_list(array(
	'sort'		=> $sort,
	'order'		=> $order,
	'start'		=> $start,
	'limit'		=> $limit,
));
$totalroles = count($roles);

// calculate some extra percentages for each role and determine max values
$max = array();
$keys = array('kills', 'damage', 'headshotkills');
for ($i=0; $i < count($roles); $i++) {
	foreach ($keys as $k) {
		if ($stats[$k]) {
			$roles[$i][$k.'pct'] = ($stats[$k]) ? ceil($roles[$i][$k] / $stats[$k] * 100) : 0;
		}
		if ($roles[$i][$k] > $max[$k]) $max[$k] = $roles[$i][$k];
	}
}
// calculate scale width of pct's based on max
$scale = 200;
$ofs   = $scale; // + 40;
for ($i=0; $i < count($roles); $i++) {
	foreach ($keys as $k) {
		if ($max[$k] == 0) {
			$roles[$i][$k.'width'] = $ofs - ceil($roles[$i][$k] / 1 * $scale);
		} else {
			$roles[$i][$k.'width'] = $ofs - ceil($roles[$i][$k] / $max[$k] * $scale);
		}
	}
}

if ($xml) {
	$ary = array();
	foreach ($roles as $r) {
		unset($r['dataid']);
		$ary[ $r['uniqueid'] ] = $r;
	} 
	print_xml($ary);
}

// build a dynamic table that plugins can use to add custom columns of data
$table = $cms->new_table($roles);
$table->if_no_data($cms->trans("No Roles Found"));
$table->attr('class', 'ps-table ps-role-table');
$table->start_and_sort($start, $sort, $order);
$table->columns(array(
	'uniqueid'		=> array( 'label' => $cms->trans("Role"), 'callback' => 'ps_table_role_link' ),
	'kills'			=> array( 'label' => $cms->trans("Kills"), 'modifier' => 'commify' ),
	'headshotkills'		=> array( 'label' => $cms->trans("HS"), 'modifier' => 'commify', 'tooltip' => $cms->trans("Headshot Kills") ),
	'headshotkillspct'	=> array( 'label' => $cms->trans("HS%"), 'modifier' => '%s%%', 'tooltip' => $cms->trans("Headshot Kills Percentage") ),
	'ffkills'		=> array( 'label' => $cms->trans("FF"), 'modifier' => 'commify', 'tooltip' => $cms->trans("Friendly Fire Kills") ),
	'ffkillspct'		=> array( 'label' => $cms->trans("FF%"), 'modifier' => '%s%%', 'tooltip' => $cms->trans("Friendly Fire Kills Percentage") ),
	'accuracy'		=> array( 'label' => $cms->trans("Acc"), 'modifier' => '%s%%', 'tooltip' => $cms->trans("Accuracy") ),
	'shotsperkill' 		=> array( 'label' => $cms->trans("S:K"), 'tooltip' => $cms->trans("Shots Per Kill") ),
	'damage' 		=> array( 'label' => $cms->trans("Dmg"), 'modifier' => 'abbrnum0', 'tooltip' => $cms->trans("Damage") ),
));
$table->column_attr('uniqueid', 'class', 'first');
$ps->roles_table_mod($table);
$cms->filter('roles_table_object', $table);

// assign variables to the theme
$cms->theme->assign(array(
	'roles'			=> $roles,
	'roles_table'		=> $table->render(),
	'totalroles'		=> $totalroles,
	'totalkills'		=> $stats['kills'],
	'totaldamage'		=> $stats['damage'],
));

// display the output
$basename = basename(__FILE__, '.php');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer');

?>
