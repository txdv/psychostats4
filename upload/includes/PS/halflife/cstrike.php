<?php
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");

$this->use_roles = FALSE;

$this->CLAN_MODTYPES = array(
	'ctkills'		=> '+',
	'terroristkills'	=> '+',
	'ctdeaths'		=> '+',
	'terroristdeaths'	=> '+',
	'joinedct'		=> '+',
	'joinedterrorist'	=> '+',
	'joinedspectator'	=> '+',
	'bombdefuseattempts'	=> '+',
	'bombdefused'		=> '+',
	'bombdefusedpct'	=> array( 'percent', 'bombdefused', 'bombdefuseattempts' ),
	'bombplanted'		=> '+',
	'bombplantedpct'	=> array( 'percent', 'bombplanted', 'rounds' ),
	'bombexploded'		=> '+',
	'bombexplodedpct'	=> array( 'percent', 'bombexploded', 'bombplanted' ),
	'bombspawned'		=> '+',
	'bombrunner'		=> '+',
	'killedhostages'	=> '+',
	'touchedhostages'	=> '+',
	'rescuedhostages'	=> '+',
	'rescuedhostagespct'	=> array( 'percent', 'rescuedhostages', 'touchedhostages' ),
	'vip'			=> '+',
	'vipescaped'		=> '+',
	'vipkilled'		=> '+',
	'ctwon'			=> '+',
	'ctwonpct'		=> array( 'percent2', 'ctwon', 'terroristwon' ),
	'ctlost'		=> '+',
	'terroristwon'		=> '+',
	'terroristwonpct'	=> array( 'percent2', 'terroristwon', 'ctwon' ),
	'terroristlost'		=> '+',
);

$this->CLAN_MAP_MODTYPES = $this->CLAN_MODTYPES;

?>
