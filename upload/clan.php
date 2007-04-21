<?php
define("VALID_PAGE", 1);
include(dirname(__FILE__) . "/includes/common.php");

$validfields = array(
	'id', 'themefile',
	'vsort','vorder','vstart','vlimit',
	'msort','morder','mstart','mlimit',
	'wsort','worder','wstart','wlimit',
	'psort','porder','pstart','plimit',
	'xml', 'weaponxml'
);
globalize($validfields);

if (empty($themefile) or !$ps->conf['theme']['allow_user_change']) $themefile = 'clan';

if (!$psort) $psort = 'skill';
if (!$vsort) $vsort = 'skill';
if (!$wlimit) $wlimit = 100;

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
	if (!is_numeric($$var) || $$var < 0) $$var = 10;
	break;
    default:
        break;
  }
  $data[$var] = $$var;			// save the variable into the theme hash
}

$data['clan'] = $ps->get_clan(array(
	'clanid' 	=> $id,
	'membersort'	=> $psort,
	'memberorder'	=> $porder,
	'memberstart'	=> $pstart,
	'memberlimit'	=> $plimit,
	'memberfields'	=> '',
	'weaponsort'	=> $wsort,
	'weaponorder'	=> $worder,
	'weaponstart'	=> $wstart,
	'weaponlimit'	=> $wlimit,
	'weaponfields'	=> '',
	'mapsort'	=> $msort,
	'maporder'	=> $morder,
	'mapstart'	=> $mstart,
	'maplimit'	=> $mlimit,
	'mapfields'	=> '',
	'loadvictims'	=> 0,
	'victimsort'	=> $vsort,
	'victimorder'	=> $vorder,
	'victimstart'	=> $vstart,
	'victimlimit'	=> $vlimit,
	'victimfields'	=> '',
), $smarty);

if ($xml) {

} elseif ($weaponxml) {
	$ary = array();
	// re-arrange the weapons list so the uniqueid of each weapon is a key.
	// weapon uniqueid's should never have any weird characters so this should be safe.
	foreach ($data['clan']['weapons'] as $w) {
		$ary[ $w['uniqueid'] ] = $w;
	} 
	print_xml($ary);
}

if ($data['clan']['clanid']) {
  $data['plrpager'] = pagination(array(
	'baseurl'	=> "$PHP_SELF?id=$id&plimit=$plimit&psort=$psort&porder=$porder",
	'total'		=> $data['clan']['totalmembers'],
	'startvar'	=> 'pstart',
	'start'		=> $pstart,
	'perpage'	=> $plimit,
	'prefix'	=> $ps_lang->trans("Goto") . ': ',
        'next'          => $ps_lang->trans("Next"),
        'prev'          => $ps_lang->trans("Previous"),
  ));

  $data['weaponpager'] = pagination(array(
	'baseurl'	=> "$PHP_SELF?id=$id&wlimit=$wlimit&wsort=$wsort&worder=$worder",
	'total'		=> $data['clan']['totalweapons'],
	'startvar'	=> 'wstart',
	'start'		=> $wstart,
	'perpage'	=> $wlimit,
	'urltail'	=> "weapons",
	'prefix'	=> $ps_lang->trans("Goto") . ': ',
        'next'          => $ps_lang->trans("Next"),
        'prev'          => $ps_lang->trans("Previous"),
  ));

  $data['mappager'] = pagination(array(
	'baseurl'	=> "$PHP_SELF?id=$id&mlimit=$mlimit&msort=$msort&morder=$morder",
	'total'		=> $data['clan']['totalmaps'],
	'startvar'	=> 'mstart',
	'start'		=> $mstart,
	'perpage'	=> $mlimit,
	'urltail'	=> "maps",
	'prefix'	=> $ps_lang->trans("Goto") . ': ',
        'next'          => $ps_lang->trans("Next"),
        'prev'          => $ps_lang->trans("Previous"),
  ));

  $data['victimpager'] = pagination(array(
	'baseurl'       => "$PHP_SELF?id=$id&vlimit=$vlimit&vsort=$vsort&vorder=$vorder",
	'total'         => $data['clan']['totalvictims'],
	'startvar'      => 'vstart',
	'start'         => $vstart,
	'perpage'       => $vlimit,
	'urltail'       => 'victims',
	'prefix'	=> $ps_lang->trans("Goto") . ': ',
	'next'          => $ps_lang->trans("Next"),
	'prev'          => $ps_lang->trans("Previous"),
  ));
}

$data['mapblockfile'] = $smarty->get_block_file('block_maps');
$data['teamblockfile'] = $smarty->get_block_file('block_team');

$data['PAGE'] = 'clan';
$smarty->assign($data);
if ($data['clan']['clanid']) {
  $smarty->parse($themefile);
} else {
  $smarty->assign(array(
	'errortitle'	=> $ps_lang->trans("No Clan Found!"),
	'errormsg'	=> $ps_lang->trans("No clan matches your search criteria"),
	'redirect'	=> "",
  ));
  $smarty->parse('nomatch');
}
ps_showpage($smarty->showpage());

include(PS_ROOTDIR . "/includes/footer.php");
?>
