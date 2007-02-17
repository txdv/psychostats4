<?php
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");

if ($register_admin_controls) {
	$menu =& $PSAdminMenu->getSection( $ps_lang->trans("Manage Users") );

	$opt =& $menu->newOption( $ps_lang->trans("View"), 'users' );
	$opt->link(ps_url_wrapper(array('c' => 'users')));

	return 1;
}

$data['PS_ADMIN_PAGE'] = "users";

if ($cancel) previouspage('admin.php?c=' . urlencode($c));

$validfields = array('filter','export','import','new','id','act','actionlist','start','limit','acl','delete','confirm','unconfirm');
globalize($validfields);
foreach ($validfields as $var) { $data[$var] = $$var; }

//if ($import) gotopage("$PHP_SELF?c=users_import");

if (!is_numeric($id)) $id = 0;
if (!is_numeric($acl) and $acl != '') $acl = ACL_USER;
if (!is_numeric($start) || $start < 0) $start = 0;
if (!is_numeric($limit) || $limit < 0) $limit = 50;
$sort = "username";
$order = "asc";
$filter = trim($filter);

if ($new) gotopage("edituser.php?new=1&ref=" . urlencode("$PHP_SELF?c=$c&filter=$filter&acl=$acl"));

// perform the action requested on the selected users ...
if (is_array($actionlist) and count($actionlist)) {
	for ($i=0; $i < count($actionlist); $i++) {
		// remove invalid elements, and the userid that matches the current user
		if (!is_numeric($actionlist[$i])) unset($actionlist[$i]);
		if ($actionlist[$i] == $ps_user['userid']) unset($actionlist[$i]);
	}

	if ($delete) {
		$ps_db->query("UPDATE $ps->t_plr_profile SET userid=0 WHERE userid IN (" . join(',', $actionlist) . ")");
		$ps_db->query("DELETE FROM $ps->t_user WHERE userid IN (" . join(',', $actionlist) . ")");
		$data['msg'] = count($actionlist) . " " . $ps_lang->trans("users deleted");
	} elseif ($confirm) {
		$ps_db->query("UPDATE $ps->t_user SET confirmed=1 WHERE userid IN (" . join(',', $actionlist) . ")");
		$data['msg'] = count($actionlist) . " " . $ps_lang->trans("users confirmed");
	} elseif ($unconfirm) {
		$ps_db->query("UPDATE $ps->t_user SET confirmed=0 WHERE userid IN (" . join(',', $actionlist) . ")");
		$data['msg'] = count($actionlist) . " " . $ps_lang->trans("users unconfirmed");
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
if ($acl != '') {
	if ($where) $where .= "AND ";
	$where .= "accesslevel = $acl";
}

$list = array();
$cmd .= "SELECT u.*,p.plrid,pp.name FROM $ps->t_user u ";
$cmd .= "LEFT JOIN $ps->t_plr_profile pp ON pp.userid=u.userid ";
$cmd .= "LEFT JOIN $ps->t_plr p ON p.uniqueid=pp.uniqueid ";
if ($where) $cmd .= "WHERE $where ";
$cmd .= $ps->_getsortorder(array('start' => $start, 'limit' => $limit, 'order' => $order, 'sort' => $sort));
$list = $ps_db->fetch_rows(1,$cmd);

# export the data and exit
# DISABLED FOR THE MOMENT; I have security concerns with allowing this, since passwords (MD5 hashes) are exported
if (FALSE and $export) {
	// get the first item in the list so we can determine what keys are available
	$i = $list[0];
	unset($i['username'], $i['userid']);		// remove unwanted keys
	$keys = array_keys($i);				// get a list of the keys (no values)
	array_unshift($keys, 'username');		// make sure username is always the first key

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
	header("Content-Disposition: attachment; filename=\"ps-users.csv\"");
	print $csv;
	exit();
}

$data['totalusers'] = $ps_db->count($ps->t_user, '*', $where);
$data['userlist'] = $list;

$data['pagerstr'] = pagination(array(
	'baseurl'	=> "$PHP_SELF?c=$c&limit=$limit&filter=" . urlencode($filter) . "&acl=" . urlencode($acl),
	'total'		=> $data['totalusers'],
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
