<?php
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_ADMIN_PAGE", true);
include("../includes/common.php");
include("./common.php");
$cms->theme->assign('page', 'roles');

$validfields = array('ref','id','del','submit','cancel');
$cms->theme->assign_request_vars($validfields, true);

$message = '';
$cms->theme->assign_by_ref('message', $message);

if ($cancel) {
	previouspage(ps_url_wrapper(array( '_amp' => '&', '_base' => 'roles.php' )));
}

// load the matching role if an ID was given
$role = array();
if (is_numeric($id)) {
	$role = $ps->db->fetch_row(1, "SELECT * FROM $ps->t_role WHERE roleid=" . $ps->db->escape($id, true));
	if (!$role['roleid']) {
		$data = array('message' => $cms->trans("Invalid role ID Specified"));
		$cms->full_page_err(basename(__FILE__, '.php'), $data);
		exit();		
	}
} elseif (!empty($id)) {
	$data = array('message' => $cms->trans("Invalid role ID Specified"));
	$cms->full_page_err(basename(__FILE__, '.php'), $data);
	exit();		
}

// delete it, if asked to
if ($del and $role['roleid'] == $id) {
	$ps->db->delete($ps->t_role, 'roleid', $id);
	previouspage(ps_url_wrapper(array( '_amp' => '&', '_base' => 'roles.php' )));
}

// create the form variables
$form = $cms->new_form();
$form->default_modifier('trim');
$form->field('uniqueid','blank');
$form->field('name');
$form->field('team');

// process the form if submitted
$valid = true;
if ($submit) {
	$form->validate();
	$input = $form->values();
	$valid = !$form->has_errors();
	// protect against CSRF attacks
	if ($ps->conf['main']['security']['csrf_protection']) $valid = ($valid and $form->key_is_valid($cms->session));

	list($exists) = $ps->db->fetch_list("SELECT roleid FROM $ps->t_role WHERE uniqueid='" . $ps->db->escape($input['uniqueid']) . "'");
	if (($id and $exists != $id) or (!$id and $exists)) {
		$form->error('uniqueid', $cms->trans("A role already exists with this identifier!"));
	}

	$valid = ($valid and !$form->has_errors());
	if ($valid) {
		$ok = false;
		if (empty($input['name'])) $input['name'] = null;
		if (empty($input['team'])) $input['team'] = null;
		if ($id) {
			$ok = $ps->db->update($ps->t_role, $input, 'roleid', $id);
		} else {
			$input['roleid'] = $ps->db->next_id($ps->t_role, 'roleid');
			$ok = $ps->db->insert($ps->t_role, $input);
		}
		if (!$ok) {
			$form->error('fatal', "Error updating database: " . $ps->db->errstr);
		} else {
			previouspage(ps_url_wrapper('roles.php'));
		}
	}

} else {
	// fill in defaults
	if ($id) {
		$form->input($role);
	}
}

$cms->crumb('Manage', ps_url_wrapper('manage.php'));
$cms->crumb('Roles', ps_url_wrapper('roles.php'));
$cms->crumb('Edit');

// save a new form key in the users session cookie
// this will also be put into a 'hidden' field in the form
if ($ps->conf['main']['security']['csrf_protection']) $cms->session->key($form->key());

$cms->theme->assign(array(
	'errors'	=> $form->errors(),
	'role'		=> $role,
	'form'		=> $form->values(),
	'form_key'	=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
));

// display the output
$basename = basename(__FILE__, '.php');
$cms->theme->add_css('css/forms.css');
$cms->theme->add_js('js/forms.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer', '');

?>
