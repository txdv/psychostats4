<?php
if (!defined("PSYCHOSTATS_ADMIN_PAGE")) die("Unauthorized access to " . basename(__FILE__));

// ADMIN pages need to setup the theme a little differently than the others
$opts = array( 
	'theme_default'	=> 'acp',
	'theme_opt'	=> 'admin_theme',
	'force_theme'	=> true,
	'in_db' 	=> false,				// the admin theme is not in the database
	'fetch_compile'	=> $ps->conf['theme']['fetch_compile'],
	'compile_dir'	=> $ps->conf['theme']['compile_dir'],
	'template_dir' 	=> dirname(__FILE__) . '/themes', 	// force the admin theme here
	'theme_url'	=> 'themes',				// force the url here too
	'compile_id' 	=> 'admin' 				// set an id for admin pages
);
// at all costs, the admin page should never break due to file permissions.
// safe-guard. If the compile directory is not writable we fallback to not saving compiled themes to disk
// which is slower. But shouldn't be a big problem since only a single person is usually accessing the admin page.
if ($opts['fetch_compile'] and !is_writable($opts['compile_dir'])) {
	$opts['fetch_compile'] = false;
}

$cms->init_theme('acp', $opts);
$ps->theme_setup($cms->theme);

$cms->crumb('Stats', dirname(dirname(SAFE_PHP_SELF)) . '/');
$cms->crumb('Admin', 'index.php');

$file = basename(PHP_SELF, '.php');
if (!$cms->user->admin_logged_in()) {
	if (!defined("PSYCHOSTATS_LOGIN_PAGE")) {
		gotopage(ps_url_wrapper(array('_base' => dirname($_SERVER['SCRIPT_NAME']) . '/login.php', '_ref' => $_SERVER['REQUEST_URI'])));
	}
}

?>
