<?php
/**
	PS::halflife
	$Id$

	Halflife support for PsychoStats front-end.
	This is just a stub for mod sub-classes to override.
*/
if (!defined("PSYCHOSTATS_PAGE")) die("Unauthorized access to " . basename(__FILE__));
if (defined("CLASS_PS_HALFLIFE_PHP")) return 1;
define("CLASS_PS_HALFLIFE_PHP", 1);

include_once(dirname(__FILE__) . '/PS.php');

class PS_halflife extends PS {

var $class = 'PS::halflife';

function PS_halflife(&$db) {
	parent::PS($db);
}

}

?>
