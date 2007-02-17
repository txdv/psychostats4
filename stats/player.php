<?php
define("VALID_PAGE", 1);
include(dirname(__FILE__) . "/includes/common.php");

$validfields = array(
	'id', 'v', 'themefile',
	'vsort','vorder','vstart','vlimit',
	'msort','morder','mstart','mlimit',
	'wsort','worder','wstart','wlimit',
	'rsort','rorder','rstart','rlimit',
	'ssort','sorder','sstart','slimit',
//	'isort','iorder','istart','ilimit',
	'xml','weaponxml'
);
globalize($validfields);

if (empty($themefile) or !$ps->conf['theme']['allow_user_change']) $themefile = 'player';
$data['load_google'] = (bool)($ps->conf['theme']['map']['google_key'] != '');

if (!$ssort) $ssort = 'sessionstart';
if (!$slimit) $slimit = '10';

// SET DEFAULTS. Since they're basically the same for each list, we do this in a loop
foreach ($validfields as $var) {
  switch (substr($var, 1)) {
    case 'sort':
	if (!$$var) $$var = 'kills';
	break;
    case 'order':
	if (!$$var) $$var = 'desc';
	break;
    case 'start':
	if (!is_numeric($$var) || $$var < 0) $$var = 0;
	break;
    case 'limit':
	if (!is_numeric($$var) || $$var < 0) $$var = 20;
	break;
    default:
        break;
  }
  $data[$var] = $$var;			// save the variable into the theme hash
}

$data['plr'] = $ps->get_player(array(
	'plrid' 	=> $id,
	'loadsessions'	=> 1,
	'loadipaddrs'	=> $ps->conf['theme']['show_ips'] || user_is_admin(),
	'loadgeoinfo'	=> 1,
	'weaponsort'	=> $wsort,
	'weaponorder'	=> $worder,
	'sessionsort'	=> $ssort,
	'sessionorder'	=> $sorder,
	'sessionstart'	=> $sstart,
	'sessionlimit'	=> $slimit,
	'mapsort'	=> $msort,
	'maporder'	=> $morder,
	'mapstart'	=> $mstart,
	'maplimit'	=> $mlimit,
	'rolesort'	=> $rsort,
	'roleorder'	=> $rorder,
	'rolestart'	=> $rstart,
	'rolelimit'	=> $rlimit,
	'victimsort'	=> $vsort,
	'victimorder'	=> $vorder,
	'victimstart'	=> $vstart,
	'victimlimit'	=> $vlimit,
), $smarty);

if ($xml) {
	// we have to alter some of the data for player arrays otherwise we'll end up with invalid or strange keys
	$ary = $data['plr'];
	$names = $ary['ids']['names'];
	$worldids = $ary['ids']['worldids'];
	$ipaddrs = $ary['ids']['ipaddrs'];
	$ary['ids']['names'] = array();
	$ary['ids']['worldids'] = array();
	$ary['ids']['ipaddrs'] = array();
	foreach ($names as $n => $t) $ary['ids']['names'][] = array( 'name' => $n, 'total' => $t );
	foreach ($worldids as $w => $t) $ary['ids']['worldids'][] = array( 'worldid' => $w, 'total' => $t );
	foreach ($ipaddrs as $i => $t) $ary['ids']['ipaddrs'][] = array( 'ipaddr' => long2ip($i), 'total' => $t );
	print_xml($ary);

} elseif ($weaponxml) {
	$ary = array();
	// re-arrange the weapons list so the uniqueid of each weapon is a key.
	// weapon uniqueid's should never have any weird characters so this should be safe.
	foreach ($data['plr']['weapons'] as $w) {
		$ary[ $w['uniqueid'] ] = $w;
	} 
	print_xml($ary);
}


$data['victimpager'] = pagination(array(
	'baseurl'       => "$PHP_SELF?id=$id&vlimit=$vlimit&vsort=$vsort&vorder=$vorder",
	'total'         => $data['plr']['totalvictims'],
	'start'         => $vstart,
	'startvar'      => 'vstart',
	'perpage'       => $vlimit,
	'urltail'       => 'victims',
	'next'          => $ps_lang->trans("Next"),
	'prev'          => $ps_lang->trans("Previous"),
	'pergroup'	=> 5,
));

$data['mappager'] = pagination(array(
	'baseurl'       => "$PHP_SELF?id=$id&mlimit=$mlimit&msort=$msort&morder=$morder",
	'total'         => $data['plr']['totalmaps'],
	'start'         => $mstart,
	'startvar'      => 'mstart',
	'perpage'       => $mlimit,
	'urltail'       => 'maps',
	'next'          => $ps_lang->trans("Next"),
	'prev'          => $ps_lang->trans("Previous"),
));

$data['sessionpager'] = pagination(array(
	'baseurl'       => "$PHP_SELF?id=$id&slimit=$slimit&ssort=$ssort&sorder=$morder",
	'total'         => $data['plr']['totalsessions'],
	'start'         => $sstart,
	'startvar'      => 'sstart',
	'perpage'       => $slimit,
	'urltail'       => 'plrsessions',
	'next'          => $ps_lang->trans("Next"),
	'prev'          => $ps_lang->trans("Previous"),
));

$data['teamblockfile'] = $smarty->get_block_file('block_team');
$data['mapblockfile'] = $smarty->get_block_file('block_maps');
$data['roleblockfile'] = $ps->use_roles ? $smarty->get_block_file('block_roles') : '';

$data['PAGE'] = 'player';
$smarty->assign($data);
if ($data['plr']['plrid']) {
	$smarty->parse($themefile);
} else {
	$smarty->assign(array(
		'errortitle'	=> $ps_lang->trans("No Player Found!"),
		'errormsg'	=> $ps_lang->trans("No player matches your search criteria"),
		'redirect'	=> "",
	));
	$smarty->parse('nomatch');
}
ps_showpage($smarty->showpage());

include(PS_ROOTDIR . "/includes/footer.php");
?>
