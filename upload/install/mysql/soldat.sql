CREATE TABLE `ps_map_data_soldat` (
  `dataid` int(10) unsigned NOT NULL default '0',
  `ctkills` smallint(5) unsigned NOT NULL default '0',
  `terroristkills` smallint(5) unsigned NOT NULL default '0',
  `joinedct` smallint(5) unsigned NOT NULL default '0',
  `joinedterrorist` smallint(5) unsigned NOT NULL default '0',
  `joinedspectator` smallint(5) unsigned NOT NULL default '0',
  `ctwon` smallint(5) unsigned NOT NULL default '0',
  `ctlost` smallint(5) unsigned NOT NULL default '0',
  `terroristwon` smallint(5) unsigned NOT NULL default '0',
  `terroristlost` smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_plr_data_soldat` (
  `dataid` int(10) unsigned NOT NULL default '0',
  `ctkills` smallint(5) unsigned NOT NULL default '0',
  `terroristkills` smallint(5) unsigned NOT NULL default '0',
  `ctdeaths` smallint(5) unsigned NOT NULL default '0',
  `terroristdeaths` smallint(5) unsigned NOT NULL default '0',
  `joinedct` smallint(5) unsigned NOT NULL default '0',
  `joinedterrorist` smallint(5) unsigned NOT NULL default '0',
  `joinedspectator` smallint(5) unsigned NOT NULL default '0',
  `ctwon` smallint(5) unsigned NOT NULL default '0',
  `ctlost` smallint(5) unsigned NOT NULL default '0',
  `terroristwon` smallint(5) unsigned NOT NULL default '0',
  `terroristlost` smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_plr_maps_soldat` (
  `dataid` int(10) unsigned NOT NULL default '0',
  `ctkills` smallint(5) unsigned NOT NULL default '0',
  `terroristkills` smallint(5) unsigned NOT NULL default '0',
  `ctdeaths` smallint(5) unsigned NOT NULL default '0',
  `terroristdeaths` smallint(5) unsigned NOT NULL default '0',
  `joinedct` smallint(5) unsigned NOT NULL default '0',
  `joinedterrorist` smallint(5) unsigned NOT NULL default '0',
  `joinedspectator` smallint(5) unsigned NOT NULL default '0',
  `ctwon` smallint(5) unsigned NOT NULL default '0',
  `ctlost` smallint(5) unsigned NOT NULL default '0',
  `terroristwon` smallint(5) unsigned NOT NULL default '0',
  `terroristlost` smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
