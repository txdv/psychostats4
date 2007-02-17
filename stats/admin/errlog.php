<?php
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");

if ($register_admin_controls) {
	$menu =& $PSAdminMenu->getSection( $ps_lang->trans("Error Logs") );

	$opt =& $menu->newOption( $ps_lang->trans("View"), 'errlog' );
	$opt->link(ps_url_wrapper(array('c' => 'errlog')));

	return 1;
}

// Normal page processing would be done here...

$validfields = array('start','limit','filter','severity','del');
globalize($validfields);
foreach ($validfields as $var) { $data[$var] = $$var; }

$data['PS_ADMIN_PAGE'] = "errlog";

$sort = 'timestamp';
$order = 'desc';
if (!is_numeric($start) || $start < 0) $start = 0;
if (!is_numeric($limit) || $limit < 0) $limit = 50;
$filter = trim($filter);

$where = '';
if ($filter != '') {
	if ($where) $where .= "AND ";
	$where .= "msg LIKE '%" . $ps_db->escape($filter) . "%' ";
}
if ($severity) {
	if ($where) $where .= "AND ";
	$where .= "severity = '" . $ps_db->escape($severity) . "' ";
}

// delete the logs that matched the filter
if ($del) {
	$cmd = "DELETE FROM $ps->t_errlog ";
	if ($where) $cmd .= "WHERE $where";
	$ps_db->query($cmd);
	$ps_db->optimize($ps->t_errlog);
	$filter = '';
	$severity = '';
	$where = '';
}

$cmd = "SELECT * FROM $ps->t_errlog ";
if ($where) $cmd .= "WHERE $where ";
$cmd .= "ORDER BY " . $ps_db->qi($sort) . " $order, id DESC";
$cmd .= $ps->_getsortorder(array('start' => $start, 'limit' => $limit));
$errlogs = array();
$errlogs = $ps_db->fetch_rows(1, $cmd);

$data['totalerrlogs'] = $ps_db->count($ps->t_errlog, '*', $where);

$data['pagerstr'] = pagination(array(
	'baseurl'	=> "$PHP_SELF?c=$c&limit=$limit&filter=" . urlencode($filter) . "&severity=" . urlencode($severity),
	'total'		=> $data['totalerrlogs'],
	'start'		=> $start,
	'perpage'	=> $limit, 
	'pergroup'	=> 3,
	'prefix'	=> '', //$ps_lang->___trans("Goto") . ': ',
	'next'		=> $ps_lang->trans("Next"),
	'prev'		=> $ps_lang->trans("Prev"),
//	'class'		=> 'menu',
));

foreach ($validfields as $var) {
	$data[$var] = $$var;
}
$data['errlogs'] = $errlogs;

?>
