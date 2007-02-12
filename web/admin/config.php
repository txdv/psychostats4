<?php
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");

if ($register_admin_controls) {
	// put a " " in front of the main config section so it sorts FIRST in the list

	$menu =& $PSAdminMenu->getSection( $ps_lang->trans("Configuration") );

	$opt =& $menu->newOption( " " . $ps_lang->trans("MAIN CONFIG"), 'config_main' );
	$opt->link(ps_url_wrapper(array('c' => 'config', 't' => 'main')));

	$opt =& $menu->newOption( " " . $ps_lang->trans("THEME CONFIG"), 'config_theme' );
	$opt->link(ps_url_wrapper(array('c' => 'config', 't' => 'theme')));

	return 1;
}

$validfields = array('t','s','newopt','adv','export');
globalize($validfields);
foreach ($validfields as $var) { $data[$var] = $$var; }

$data['PS_ADMIN_PAGE'] = "config_$t";

if ($cancel) previouspage('admin.php');

if ($export) {
	$text = $ps->export_config($t);
	while (@ob_end_clean());
	header("Pragma: no-cache");
	header("Content-Type: text/plain");
	header("Content-Length: " . strlen($text));
	header("Content-Disposition: attachment; filename=\"ps-config-$t.txt\"");
#	print "<pre>";
	print $text;
	exit();
}

if ($newopt) {
	gotopage("admin.php?c=config_new&t=" . urlencode($t) . "&s=" . urlencode($s));
}

// enable advanced editor mode (set a cookie to save setting)
if ($adv != '' and ($adv == 0 or $adv == 1)) {
	$ps_user_opts['advconfig'] = $adv;
	$data['user_opts'] = $ps_user_opts;
	session_save_user_opts($ps_user_opts);
}

$conftypes = array( 'main' );
$ps_db->query("SELECT DISTINCT c.conftype FROM $ps->t_config c LEFT JOIN $ps->t_config_layout cl ON c.conftype=cl.conftype WHERE ISNULL(cl.locked) OR !cl.locked");
while ($row = $ps_db->fetch_row(0)) {
	$conftypes[] = $row[0];
}

if (empty($t)) $t = 'main';
//if (empty($s)) $s = '';

if (!in_array($t, $conftypes)) {
	abort('admin_invalid', 
		$ps_lang->trans("Invalid Request"), 
		$ps_lang->trans("Invalid config type specified"), 
		"<a href='" . 
			ps_url_wrapper(array('_base' => 'admin.php', 'c' => $c, 't' => 'main')) . 
		"'>" . $ps_lang->trans("Return to main config") . "</a>"
	);
}

// load the config, if it wasn't already
if (!array_key_exists($t, $ps->conf)) {
	$ps->load_config($t);
}

// get a list of 'sub sections' (tabs) in the current config block
$sections = array();
$t_esc = $ps_db->escape($t);
$sections = $ps_db->fetch_rows(1,"select distinct c.section label,l.comment from $ps->t_config c " .
	"left join $ps->t_config_layout l on (l.conftype='$t_esc' and l.section=c.section and l.var='') " .
	"where c.conftype='$t_esc' order by c.section,c.idx"
);
//print $ps_db->lastcmd;
if (empty($s)) $s = $sections[0]['label'];

// each $conf_* array has a purpose within the form or within the smarty plugins confvar*()
// it can be confusing, but it's currently the only way I can think of doing it. Having it all
// in a single array would be even more confusing, IMHO.
$conf_values = array();		// array of var+id==value
$conf_idxs = array();		// array of var+id==idx
$conf_ids = array();		// array of id==var+id
$conf_varids = array();		// array of var==id
$conf_layout = array();		// array of var=={layout array}
$conf_form = array();		// array of section==var+id==value (for easy form separation)
$conf_errors = array();		// array of var+id==error string

$ps->load_conf_form($t, VAR_SEPARATOR, TRUE);	// sets the 5 global arrays below
//show($conf_form);
//show($conf_values);
//show($conf_ids);
//show($conf_idxs);
//show($conf_varids);

// load the config layout
$ps_db->query("SELECT * FROM $ps->t_config_layout WHERE conftype='$t_esc'");
while ($row = $ps_db->fetch_row()) {
	if ($row['var'] == 'logsource') continue;
	$var = $row['section'] . VAR_SEPARATOR . $row['var']; 
#	$var .= VAR_SEPARATOR . $conf_varids[$var];
	$conf_layout[$var] = $row;
}
//show($conf_layout);

if ($submit and $_SERVER['REQUEST_METHOD'] = 'POST') {
	$VARS = $_POST['opts'];
	if (!is_array($VARS)) $VARS = array();
	$setlist = array();
	// loop through known keys and add the matching form values to our set if they're valid
 	foreach ($conf_values as $var => $orig) {
		if (!isset($VARS[$var])) continue;
		$VARS[$var] = trim($VARS[$var]);
		$parts = explode(VAR_SEPARATOR, $var);
		$id = array_pop($parts);
		$localvar = implode(VAR_SEPARATOR, $parts);

		if ($conf_layout[$localvar]['locked']) continue;	// do not change locked settings
		if ($orig == $VARS[$var]) continue;			// do not change if it's the same

		// create a formfield so the form_checks() function will work here...
		$err = array( 'val' => $conf_layout[$localvar]['verifycodes'], 'error' => '' );
		form_checks($VARS[$var], $err);
		if ($err['error']) {
			$conf_errors[$var] = $err['error'];
			$conf_values[$var] = $VARS[$var];
			continue;
		}

		$conf_values[$var] = $VARS[$var];			// update value on form
		$setlist[$id]['value'] = $VARS[$var];			// add var=value to be updated
	}

	// save our config if there were no errors
	if (!count($conf_errors) and count($setlist)) {
		foreach ($setlist as $id => $set) {
			$ps_db->update($ps->t_config, $set, 'id', $id);
		}
		$data['config_was_saved'] = 1;
	}
}

$f = "admin_left_help.html";
if (file_exists(catfile(THEME_DIR, $f)) and !$ps->conf['theme']['hide_config_help']) {
	$data['admincontrol_left_file'] = $f;
};

$data['adminpage'] = "config_main";

$data['conf_form'] = $conf_form;
$data['conf_values'] = $conf_values;
$data['conf_idxs'] = $conf_idxs;
$data['conf_layout'] = $conf_layout;
$data['conf_varids'] = $conf_varids;
$data['conf_errors'] = $conf_errors;
$data['sections'] = $sections;
$data['s'] = $s;

?>
