<?php
define("VALID_PAGE", 1);
include(dirname(__FILE__) . "/includes/common.php");
include(PS_ROOTDIR . '/includes/forms.php');

$validfields = array('s','f','v','submit','cancel','search','themefile');
globalize($validfields);

if (empty($themefile) or !$ps->conf['theme']['allow_user_change']) $themefile = 'plrsearch';
$data['PAGE'] = 'plrsearch';


if (empty($s)) $s = '';			// search
if (empty($v)) $v = 'plrname';		// variable
if (empty($f)) $f = 'editform';		// form
$search = $s;
$andor = 'and';

foreach ($validfields as $var) {
	$data[$var] = $$var;
}

if (!empty($search)) {
	 $data['playerlist'] = $ps->get_player_list(array(
		'sort'		=> 'pp.name',
		'order'		=> 'asc',
		'search'	=> "$andor:$search",
		'joinclaninfo' 	=> 0,
		'joinccinfo'	=> 0,
		'fields'	=> 'plr.plrid,pp.name',
		'limit'		=> 100,
	), $smarty);
}

$smarty->assign($data);
$smarty->parse($themefile);
ps_showpage($smarty->showpage());

include(PS_ROOTDIR . '/includes/footer.php');
?>
