<?php
define("PSYCHOSTATS_PAGE", true);
include(dirname(__FILE__) . "/includes/common.php");
$cms->init_theme($ps->conf['main']['theme'], $ps->conf['theme']);
$ps->theme_setup($cms->theme);

// collect url parameters ...
$validfields = array('s');
$cms->theme->assign_request_vars($validfields, true);

$servers = array();
$servers = $ps->db->fetch_rows(1, 
	"SELECT * " . 
	"FROM $ps->t_config_servers " . 
	"WHERE enabled=1 " . 
	"ORDER BY idx,host,port"
);

for ($i=0; $i < count($servers); $i++) {
	$servers[$i]['ip'] = gethostbyname($servers[$i]['host']);
}

// assign variables to the theme
$cms->theme->assign(array(
	'servers'	=> $servers
));

// display the output
$basename = basename(__FILE__, '.php');
$cms->theme->add_css('css/2column.css');	// this page has a left column
$cms->theme->add_css('css/query.css');
$cms->theme->add_js('js/' . $basename . '.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer');

?>
