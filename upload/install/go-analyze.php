<?php
/*
	Analyze system for minimum requirements
	$Id$
	
*/
if (!defined("PSYCHOSTATS_INSTALL_PAGE")) die("Unauthorized access to " . basename(__FILE__));

$validfields = array();
$cms->theme->assign_request_vars($validfields, true);

$min_php_version = '4.1';
$min_mysql_version = '4.1.11';

$loaded_exts = array_flip(get_loaded_extensions());

$required_err = 0;
$required_ext = array(
//	'fake'	=> "Fake extension to cause an error",
	'mysql' => "MySQL support must be enabled in order to view your stats online."
);

$optional_err = 0;
$optional_ext = array(
//	'blarg' => 'This is not a real extension',
	'ftp'	=> "FTP support is only needed if you want to be able to download updates, themes and plugins " .
		   "directly from your stats web pages. Also, the installer can use FTP to save your configuration.",
	'gd'	=> "GD (version 2) support is recommended so that some dynamic images can be created within the " . 
		   "player stats (charts and graphs).",
	'mcrypt'=> "mcrypt support (encryption) is only needed if you want to enable some extra security features " . 
		   "with user sessions. The extra security granted by having this extension is minimal to PsychoStats.",
	'zip'	=> "ZIP support will enable some advanced features within the PsychoStats software but is not required."
);

// check php and mysql versions for minimum requirement
$php_version_ok = (version_compare($min_php_version, phpversion()) < 1);

$required_ext_ok = array();
foreach ($required_ext as $e => $desc) {
		if (array_key_exists($e, $loaded_exts)) {
			$required_ext_ok[$e] = true; // phpversion($e);
		} else {
			$required_ext_ok[$e] = false;
			$required_err++;
		}
}

$optional_ext_ok = array();
foreach ($optional_ext as $e => $desc) {
		if (array_key_exists($e, $loaded_exts)) {
			$optional_ext_ok[$e] = true; //phpversion($e);
		} else {
			$optional_ext_ok[$e] = false;
			$optional_err++;
		}
}

$allow_next = ($required_err == 0);

$cms->theme->assign(array(
	'php_version'		=> phpversion(),
	'php_version_ok'	=> $php_version_ok,
	'php_sapi_name'		=> php_sapi_name(),
	'min_php_version'	=> $min_php_version,
	'required_err'		=> $required_err,
	'required_ext'		=> $required_ext,
	'required_ext_ok'	=> $required_ext_ok,
	'optional_err'		=> $optional_err,
	'optional_ext'		=> $optional_ext,
	'optional_ext_ok'	=> $optional_ext_ok,
	'server_os'		=> PHP_OS,
	'server_uname'		=> php_uname(),
));

if ($ajax_request) {
//	sleep(1);
	$pagename = 'go-analyze-results';
	$cms->tiny_page($pagename, $pagename);
}


?>
