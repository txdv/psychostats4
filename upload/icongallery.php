<?php
define("VALID_PAGE", 1);
include(dirname(__FILE__) . "/includes/common.php");

$validfields = array('themefile');
globalize($validfields);

if (empty($themefile) or !$ps->conf['theme']['allow_user_change']) $themefile = 'icongallery';


$data['PAGE'] = 'icongallery';
$data['icons'] = load_icons(catfile($ps->conf['theme']['rootimagesdir'], 'icons'));
$smarty->assign($data);
$smarty->parse($themefile);
ps_showpage($smarty->showpage());

include(PS_ROOTDIR . "/includes/footer.php");
?>
