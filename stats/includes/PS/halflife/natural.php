<?php
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");

$this->use_roles = TRUE;

$this->CLAN_MODTYPES = array(
	'marinekills'		=> '+',
	'alienkills'		=> '+',
	'marinedeaths'		=> '+',
	'aliendeaths'		=> '+',
	'joinedmarine'		=> '+',
	'joinedalien'		=> '+',
	'marinewon'		=> '+',
	'marinewonpct'		=> array( 'percent2', 'marinewon', 'alienwon' ),
	'alienwon'		=> '+',
	'alienwonpct'		=> array( 'percent2', 'alienwon', 'marinewon' ),
	'marinelost'		=> '+',
	'alienlost'		=> '+',
	'commander'		=> '+',
	'commanderwon'		=> '+',
	'commanderwonpct'	=> array( 'percent', 'commanderwon', 'commander' ),
	'votedown'		=> '+',
	'structuresbuilt'	=> '+',
	'structuresdestroyed'	=> '+',
	'structuresrecycled'	=> '+',
);

$this->CLAN_MAP_MODTYPES = $this->CLAN_MODTYPES;

?>
