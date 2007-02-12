<?php
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");

if ($register_admin_controls) {
	$menu =& $PSAdminMenu->getSection( $ps_lang->trans("Manage Players") );

	$opt =& $menu->newOption( $ps_lang->trans("View"), 'players' );
	$opt->link(ps_url_wrapper(array('c' => 'players')));

	return 1;
}

$data['PS_ADMIN_PAGE'] = "players";

if ($cancel) previouspage('admin.php?c=' . urlencode($c));

$validfields = array('filter','act','actionlist','start','limit','delete','allowrank');
globalize($validfields);
foreach ($validfields as $var) { $data[$var] = $$var; }

if (!is_numeric($allowrank) and $allowrank != '') $allowrank = '';
if (!is_numeric($start) || $start < 0) $start = 0;
if (!is_numeric($limit) || $limit < 0) $limit = 100;
$sort = "name";
$order = "asc";
$filter = trim($filter);

// perform the action requested on the selected users ...
if (is_array($actionlist) and count($actionlist)) {
	for ($i=0; $i < count($actionlist); $i++) {
		// remove invalid elements
		if (!is_numeric($actionlist[$i])) unset($actionlist[$i]);
	}

	if ($delete) {
		$count = 0;
		foreach ($actionlist as $plrid) {
			$ok = $ps->delete_player($plrid);
			if ($ok) $count++;
		}
		$data['msg'] = "$count " . $ps_lang->trans("users deleted");
	}
}

$where = '';
if ($filter != '') {
	if ($where) $where .= "AND ";
	$where .= "(";
	$where .= "u.username LIKE '%" . $ps_db->escape($filter) . "%' ";
	$where .= "OR pp.name LIKE '%" . $ps_db->escape($filter) . "%' ";
	$where .= ") ";
}
if ($allowrank != '') {
	if ($where) $where .= "AND ";
	$where .= "(p.allowrank='" . $ps->db->escape($allowrank) . "')";
}

$list = array();
$cmd  = "SELECT p.*,pp.*,u.* FROM $ps->t_plr p ";
$cmd .= "LEFT JOIN $ps->t_plr_profile pp ON pp.uniqueid=p.uniqueid ";
$cmd .= "LEFT JOIN $ps->t_user u ON u.userid=pp.userid ";
if ($where) $cmd .= "WHERE $where ";
$cmd .= $ps->_getsortorder(array('start' => $start, 'limit' => $limit, 'order' => $order, 'sort' => $sort));
$list = $ps_db->fetch_rows(1,$cmd);

$cmd  = "SELECT count(*) FROM $ps->t_plr p ";
$cmd .= "LEFT JOIN $ps->t_plr_profile pp ON pp.uniqueid=p.uniqueid ";
$cmd .= "LEFT JOIN $ps->t_user u ON u.userid=pp.userid ";
if ($where) $cmd .= "WHERE $where ";
list($filtertotal) = $ps_db->fetch_list($cmd);

$data['totalplayers'] 	= $ps_db->count($ps->t_plr, '*');
$data['totalranked'] 	= $ps_db->count($ps->t_plr, '*', 'allowrank=1');
$data['totalunranked'] 	= $ps_db->count($ps->t_plr, '*', 'allowrank=0');
$data['playerlist'] 	= $list;

$data['pagerstr'] = pagination(array(
	'baseurl'	=> ps_url_wrapper(array('c' => $c, 'limit' => $limit, 'filter' => $filter, 'allowrank' => $allowrank)),
	'total'		=> $filtertotal,
	'start'		=> $start,
	'perpage'	=> $limit, 
	'pergroup'	=> 3,
	'prefix'	=> '', //$ps_lang->___trans("Goto") . ': ',
	'next'		=> $ps_lang->trans("Next"),
	'prev'		=> $ps_lang->trans("Prev"),
//	'class'		=> 'menu',
));

foreach ($validfields as $var) {
	$data[$var] = $$var;
}

?>
