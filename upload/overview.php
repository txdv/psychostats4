<?php
define("PSYCHOSTATS_PAGE", true);
include(dirname(__FILE__) . "/includes/common.php");
$cms->init_theme($ps->conf['main']['theme'], $ps->conf['theme']);
$ps->theme_setup($cms->theme);

// collect url parameters ...
$validfields = array('ip','ofc');
$cms->theme->assign_request_vars($validfields, true);

// return a list of geocoded IP's using the most active IPs in the database
if (is_numeric($ip) and $ip > 0) {
	if ($ip > 100) $ip = 100;
	// this will eventually be updated to return the associated player information too, 
	// so each pin in the map can show what player it is.
	$list = $ps->db->fetch_list(
		"SELECT DISTINCT INET_NTOA(ipaddr) ipaddr " .
		"FROM $ps->t_plr_ids_ipaddr ip " . 
		"WHERE (ipaddr NOT BETWEEN 167772160 AND 184549375) AND " .	// 10/8
		"(ipaddr NOT BETWEEN 2886729728 AND 2887778303) AND " .		// 172.16/12
		"(ipaddr NOT BETWEEN 3232235520 AND 3232301055) AND " .		// 192.168/16
		"(NOT ipaddr IN (2130706433, 0)) " .				// 127.0.0.1, 0.0.0.0
		"ORDER BY totaluses DESC LIMIT $ip"
	);
	header("Content-Type: text/xml");
	print $ps->ip_lookup($list);
	exit;
} elseif ($ofc) {	// collect hourly stats for OFC
	return_ofc_data();
	exit;
}

// assign variables to the theme
$cms->theme->assign(array(
	'page'	=> basename($PHP_SELF,'.php'),
));

// display the output
$basename = basename(__FILE__, '.php');
if ($ps->conf['theme']['map']['google_key']) {
	$cms->theme->add_js('http://maps.google.com/maps?file=api&amp;v=2&amp;key=' . $ps->conf['theme']['map']['google_key']);
//	$cms->theme->add_js('http://www.google.com/jsapi?key=' . $ps->conf['theme']['map']['google_key']);
	$cms->theme->add_js('js/map.js');
}
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer');

function return_ofc_data() {
	global $cms, $ps;

	$hours = array();
	$labels = array();
	$data = array();
	$data_avg = array();
	$conns = array();
	$conns_avg = array();
	$sum = 0;
	$avg = 0;
	$maxlimit = 100;
	$maxlimit2 = 100;
	$minlimit = 0;
	$max = 24;

	list($newest) = $ps->db->fetch_list("SELECT hour FROM $ps->t_map_hourly ORDER BY statdate DESC,hour DESC LIMIT 1");
	if ($newest === null) $newest = date("H");

	// build a list of hours in the proper order
	for ($h=$newest; count($hours)<24; $h--) {
		if ($h < 0) $h = 23;
		$hours[ sprintf('%02d:00', $h) ] = 'null';
	}
	$hours = array_reverse($hours);

	// get the last 24 hours of data
	$ps->db->query(
		"SELECT statdate,hour,SUM(kills),SUM(connections) " . 
		"FROM $ps->t_map_hourly " . 
		"GROUP BY statdate,hour " . 
		"ORDER BY statdate DESC,hour DESC LIMIT $max"
	);

	// build our data and labels
	$data = $hours;
	$conns = $hours;
	$maxdata = 0;
	$maxconn = 0;
	while (list($statdate,$hour,$kills,$connections) = $ps->db->fetch_row(0)) {
		$hh = sprintf('%02d:00', $hour);
		$sum += $kills;
		$data[$hh] = $kills;
		$conns[$hh] = $connections;
		$maxdata = max($maxdata, $kills);
		$maxconn = max($maxconn, $connections);
		print "($statdate, $hour, $kills, $connections)<br>\n";
	}
	$labels = array_keys($hours);
#	print_r($hours);
#	print_r($data);
#	print_r($conns);
#	print_r($labels);

	if ($data) {
		$avg = $sum / count($data);
		$data_avg = array_pad(array(), count($data), $avg);
#		$maxlimit  = ceil(ceil($maxdata / 100) * 100);
	}
	if ($conns) {
		$avg = $sum / count($conns);
		$conns_avg = array_pad(array(), count($conns), $avg);
#		$maxlimit2 = ceil(ceil($maxconn / 100) * 100);
	}

	include_once(PS_ROOTDIR . '/includes/ofc/open-flash-chart.php');
	$g = new graph();

	$g->title('Last 24 Hours', '{font-size: 16px; font-weight: bold}');
	$g->bg_colour = '#C4C4C4';

	$g->set_data($data_avg);
	$g->set_data($data);
//	$g->set_data($conns_avg);
	$g->set_data($conns);
	$g->attach_to_y_right_axis(3);

	$g->line(1, '#9999ee', 'Average Kills', 9);
	$g->line_dot(2, 5, '#5555ff', 'Kills', 9);
//	$g->line(1, '#666666', 'Average Connections', 9);
	$g->line_hollow(1, 3, '#000000', 'Connections', 9);

	// label each point with its value
	$g->set_x_labels($labels);
//	$g->set_x_axis_steps(count($labels) / 3 + 1);
//	$g->set_x_tick_size(1);

	$g->set_x_label_style( 10, '#000000', 0, 3, '#cccccc' );
//	$g->set_x_label_style( 10, '0x000000', 0, 2 );

//	$g->set_x_label_style('none');
#	$g->set_x_label_style( 8, '#000000', 2 );
	$g->set_inner_background( '#E3F0FD', '#CBD7E6', 90 );
	$g->x_axis_colour( '#eeeeee', '#eeeeee' );
	$g->y_axis_colour( '#5555ff', '#eeeeee' );
	$g->y_right_axis_colour( '#000000', '#eeeeee' );
//	$g->set_x_offset( false );

	// set the Y max
	$g->set_y_max($maxdata);
	$g->set_y_min(0);
	$g->set_y_right_max($maxconn);
	$g->set_y_right_min(0);
/*
	$g->set_y_max($maxlimit);
	$g->set_y_min($minlimit);
	$g->set_y_right_min($minlimit);
	$g->set_y_right_max($maxlimit2);
*/
	$g->set_y_legend('Kills',12,'#5555ff');
	$g->set_y_right_legend('Connections',12,'#000000');
//	$g->y_label_steps();

	$g->set_tool_tip( '#key#<br>#val# (#x_label#)' );

	// label every 20 (0,20,40,60)
//	$g->x_label_steps( 2 );

	// display the data
	print $g->render();
}

?>
