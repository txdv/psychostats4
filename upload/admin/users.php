<?php
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_ADMIN_PAGE", true);
include("../includes/common.php");
include("./common.php");

$validfields = array('ref','start','limit','order','sort','filter','c', 'sel', 'delete','confirm');
$cms->theme->assign_request_vars($validfields, true);

$message = '';
$cms->theme->assign_by_ref('message', $message);

if (!is_numeric($start) or $start < 0) $start = 0;
if (!is_numeric($limit) or $limit < 0) $limit = 100;
if (!in_array($order, array('asc','desc'))) $order = 'asc';
if (!in_array($sort, array('username'))) $sort = 'username';
$c = trim($c);
if ($c == '') $c = -1;

$_order = array(
	'start'		=> $start,
	'limit'		=> $limit,
	'order' 	=> $order, 
	'sort'		=> $sort,
	'username'	=> $filter,
	'confirmed'	=> $c
);

if (($delete or $confirm) and is_array($sel) and count($sel)) {
	$total_processed = 0;
	foreach ($sel as $id) {
		// do not allow the current user to mess with their own account
		if (is_numeric($id) and $id != $cms->user->userid()) {
			if ($delete) {
				if ($cms->user->delete_user($id)) {
					$ps->db->update($ps->t_plr_profile, array( 'userid' => null ), 'userid', $id);
					$total_processed++;
				}
			} else { // confirm
				if ($cms->user->confirm_user(1, $id)) {
					$total_processed++;
				}
			}
		}
	}
	if ($delete) {
		$message = $cms->message('success', array(
			'message_title'	=> $cms->trans("Users Deleted!"),
			'message'	=> $cms->trans("%d users were deleted successfully", $total_processed),
		));
	} else {
		$message = $cms->message('success', array(
			'message_title'	=> $cms->trans("Users Confirmed!"),
			'message'	=> $cms->trans("%d users were confirmed successfully", $total_processed),
		));
	}
}

$uobj =& $cms->new_user();	// start a user object

$users = $uobj->get_user_list(true, $_order);	// true = get associated plr info too
$total = $uobj->total_users($_order);
$pager = pagination(array(
	'baseurl'	=> ps_url_wrapper(array('sort' => $sort, 'order' => $order, 'limit' => $limit, 'filter' => $filter, 'c' => $c)),
	'total'		=> $total,
	'start'		=> $start,
	'perpage'	=> $limit, 
	'pergroup'	=> 5,
	'separator'	=> ' ', 
	'force_prev_next' => true,
	'next'		=> $cms->trans("Next"),
	'prev'		=> $cms->trans("Previous"),
));

$cms->crumb('Manage', ps_url_wrapper(array('_base' => 'manage.php' )));
$cms->crumb('Users', ps_url_wrapper(array('_base' => $PHP_SELF )));


// assign variables to the theme
$cms->theme->assign(array(
	'page'		=> basename(__FILE__, '.php'), 
	'user'		=> $cms->user->to_form_input(),
	'users'		=> $users,
	'pager'		=> $pager,
));

// display the output
$basename = basename(__FILE__, '.php');
$cms->theme->add_css('css/2column.css');
$cms->theme->add_css('css/forms.css');
$cms->theme->add_js('js/users.js');
$cms->theme->add_js('js/message.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer', '');

?>
