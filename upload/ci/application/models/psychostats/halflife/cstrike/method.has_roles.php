<?php
/**
 * PsychoStats method has_roles()
 * $Id$
 *
 * Returns true/false if the gametype:modtype uses roles.
 *
 */

include dirname(__FILE__) . '/../' . basename(__FILE__);

class   Psychostats_Method_Has_Roles_Halflife_Cstrike
extends Psychostats_Method_Has_Roles_Halflife {
	function execute() {
		return false;
	}
} 

?>
