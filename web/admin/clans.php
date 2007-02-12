<?php
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");

if ($register_admin_controls) {
	$menu =& $PSAdminMenu->getSection( $ps_lang->trans("Manage Clans") );

	$opt =& $menu->newOption( $ps_lang->trans("View"), 'clans' );
	$opt->link(ps_url_wrapper(array('c' => 'clans')));

	return 1;
}

$data['PS_ADMIN_PAGE'] = "clans";

if ($cancel) previouspage('admin.php?c=' . urlencode($c));

$validfields = array('filter','export','import','new','actionlist','start','limit','delete','rank','norank','allowrank','msg');
globalize($validfields);
foreach ($validfields as $var) { $data[$var] = $$var; }

if ($import) gotopage(ps_url_wrapper(array('c' => 'clans_import')));

if (!is_numeric($start) || $start < 0) $start = 0;
if (!is_numeric($limit) || $limit < 0) $limit = 50;
$sort = "cp.name,c.clantag";
$order = "";
$filter = trim($filter);

//if ($new) gotopage("editclan.php?new=1&ref=" . urlencode("$PHP_SELF?c=$c&filter=$filter&acl=$acl"));

// perform the action requested on the selected clans ...
if (is_array($actionlist) and count($actionlist)) {
	for ($i=0; $i < count($actionlist); $i++) {
		// remove invalid elements
		if (!is_numeric($actionlist[$i])) unset($actionlist[$i]);
	}

	if ($delete) {
		$tags = $ps_db->fetch_list("SELECT clantag FROM $ps->t_clan WHERE clanid IN (" . join(',', $actionlist) . ")");
		$ps_db->query("UPDATE $ps->t_plr SET clanid=0 WHERE clanid IN (" . join(',', $actionlist) . ")");
		$ps_db->query("DELETE FROM $ps->t_clan WHERE clanid IN (" . join(',', $actionlist) . ")");
		$ps_db->query("DELETE FROM $ps->t_clan_profile WHERE clantag IN (" . join(',',array_map('_callback_doesc', $tags)) . ")");
		$data['msg'] = count($actionlist) . " " . $ps_lang->trans("clans deleted");
	} elseif ($rank or $norank) {
		$r = $rank ? 1 : 0;
		$ps_db->query("UPDATE $ps->t_clan SET allowrank=$r WHERE clanid IN (" . join(',', $actionlist) . ")");
		$count = $ps_db->affected_rows();
		$data['msg'] = "$count " . $ps_lang->trans("clans updated");
	}
}

$where = '';
if ($filter != '') {
	if ($where) $where .= "AND ";
	$where .= "(";
	$where .= "c.clantag LIKE '%" . $ps_db->escape($filter) . "%' ";
	$vars = array('name', 'email', 'website', 'aim', 'icq', 'msn');
	foreach ($vars as $v) {
		$where .= "OR cp.$v LIKE '%" . $ps_db->escape($filter) . "%' ";
	}
	$where .= ") ";
}
if ($allowrank != '') {
	if ($where) $where .= "AND ";
	$where .= "(c.allowrank='" . $ps->db->escape($allowrank) . "')";
}

$list = array();
$cmd .= "SELECT c.*,cp.* FROM $ps->t_clan c ";
$cmd .= "LEFT JOIN $ps->t_clan_profile cp ON cp.clantag=c.clantag ";
if ($where) $cmd .= "WHERE $where ";
if (!$export) $cmd .= $ps->_getsortorder(array('start' => $start, 'limit' => $limit, 'order' => $order, 'sort' => $sort));
$list = $ps_db->fetch_rows(1,$cmd);
#print "$cmd<BR><BR>";

$cmd  = "SELECT count(*) FROM $ps->t_clan c ";
$cmd .= "LEFT JOIN $ps->t_clan_profile cp ON cp.clantag=c.clantag ";
if ($where) $cmd .= "WHERE $where ";
list($filtertotal) = $ps_db->fetch_list($cmd);

# export the data and exit
if ($export) {
	// get the first item in the list so we can determine what keys are available
	$i = $list[0];
	unset($i['clantag'],$i['clanid'],$i['allowrank'],$i['locked']);	// remove unwanted keys
	$keys = array_keys($i);				// get a list of the keys (no values)
	array_unshift($keys, 'clantag');		// make sure clantag is always the first key

	$csv = csv($keys);				// 1st row is always the key order
	foreach ($list as $i) {
		$set = array();
		foreach ($keys as $k) {			// we want to make sure our key order is the same
			$set[] = $i[$k];		// and we only use keys from the original $keys list
		}
		$csv .= csv($set);
	}

	// remove all pending output buffers first 
	while (@ob_end_clean());
	header("Pragma: no-cache");
	header("Content-Type: text/csv");
	header("Content-Length: " . strlen($csv));
	header("Content-Disposition: attachment; filename=\"ps-clans.csv\"");
	print $csv;
	exit();
}

$data['totalclans'] = $ps_db->count($ps->t_clan, '*', $where);
$data['clanlist'] = $list;

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
