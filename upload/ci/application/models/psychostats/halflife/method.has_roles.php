<?php
/**
 * PsychoStats method has_roles()
 * $Id$
 *
 * Returns true/false if the gametype uses roles.
 *
 */

include dirname(__FILE__) . '/../' . basename(__FILE__);

class   Psychostats_Method_Has_Roles_Halflife 
extends Psychostats_Method_Has_Roles {
	function execute() {
		return false;
	}
} 

?>
