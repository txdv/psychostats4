<?php
define("VALID_PAGE", 1);
include(dirname(__FILE__) . "/includes/common.php");

$validfields = array('id','sort','order','start','limit','themefile');
globalize($validfields);

$sort = strtolower($sort);
$order = strtolower($order);
if (!preg_match('/^\w+$/', $sort)) $sort = 'kills';
if (!in_array($order, array('asc','desc'))) $order = 'desc';
if (!is_numeric($start) || $start < 0) $start = 0;
if (!is_numeric($limit) || $limit < 0) $limit = 30;



foreach ($validfields as $var) {
  $data[$var] = $$var;
}

if (empty($themefile) or !$ps->conf['theme']['allow_user_change']) $themefile = 'role';


$data['totalroles'] = $ps->get_total_roles(array(), $smarty);
$data['roles'] = $ps->get_role_list(array(
	'sort'		=> 'kills',
	'order'		=> 'desc',
	'start'		=> 0,
	'limit'		=> 100
), $smarty);

$data['role'] = $ps->get_role(array(
	'roleid' 	=> $id
), $smarty);


if ($data['role']['roleid']) {
  $data['toptenkills'] = $ps->get_role_player_list(array(
	'roleid' 	=> $id,
	'sort'		=> $sort,
	'order'		=> $order,
	'limit'		=> $limit,
  ), $smarty);
}


$smarty->assign($data);
if ($data['role']['roleid']) {
  $smarty->parse($themefile);
} else {
  $smarty->assign(array(
	'errortitle'	=> $ps_lang->trans("No Role Found!"),
	'errormsg'	=> $ps_lang->trans("No role matches your search criteria"),
	'redirect'	=> "<a href='roles.php'>" . $ps_lang->trans("Return to the roles list") . "</a>",
  ));
  $smarty->parse('nomatch');
}
ps_showpage($smarty->showpage());

include(PS_ROOTDIR . "/includes/footer.php");
?>
