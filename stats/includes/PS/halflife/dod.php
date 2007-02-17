<?php
if (!defined("VALID_PAGE")) die("<b>Access Denied!</b>");

$this->use_roles = TRUE;

$this->CLAN_MODTYPES = array(
	'allieskills'		=> '+',
	'axiskills'		=> '+',
	'alliesdeaths'		=> '+',
	'axisdeaths'		=> '+',
	'joinedallies'		=> '+',
	'joinedaxis'		=> '+',
	'allieswon'		=> '+',
	'allieswonpct'		=> array( 'percent2', 'allieswon', 'axiswon' ),
	'axiswon'		=> '+',
	'axiswonpct'		=> array( 'percent2', 'axiswon', 'allieswon' ),
	'allieslost'		=> '+',
	'axislost'		=> '+',
#	'tnt'			=> '+',
#	'tntused'		=> '+',
	'alliesflagscaptured'	=> '+',
	'alliesflagscapturedpct'=> array( 'percent', 'alliesflagscaptured', 'flagscaptured' ),
	'axisflagscaptured'	=> '+',
	'axisflagscapturedpct'	=> array( 'percent', 'axisflagscaptured', 'flagscaptured' ),
	'flagscaptured'		=> '+',

	'alliesflagsblocked'	=> '+',
	'alliesflagsblockedpct'	=> array( 'percent', 'alliesflagsblocked', 'flagsblocked' ),
	'axisflagsblocked'	=> '+',
	'axisflagsblockedpct'	=> array( 'percent', 'axisflagsblocked', 'flagsblocked' ),
	'flagsblocked'		=> '+',

	'bombdefused'		=> '+',
	'bombplanted'		=> '+',
	'killedbombplanter'	=> '+',
	'alliesscore'		=> '+',	
	'alliesscorepct'	=> array( 'percent2', 'alliesscore', 'axisscore' ),
	'axisscore'		=> '+',	
	'axisscorepct'		=> array( 'percent2', 'axisscore', 'alliesscore' ),
);

$this->CLAN_MAP_MODTYPES = $this->CLAN_MODTYPES;

?>
