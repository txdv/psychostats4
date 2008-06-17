CREATE TABLE `ps_map_data_soldat` (
  `dataid` int(10) unsigned NOT NULL default '0',
  `alphakills` smallint(5) unsigned NOT NULL default '0',
  `bravokills` smallint(5) unsigned NOT NULL default '0',
  `joinedalpha` smallint(5) unsigned NOT NULL default '0',
  `joinedbravo` smallint(5) unsigned NOT NULL default '0',
  `joinedspectator` smallint(5) unsigned NOT NULL default '0',
  `alphawon` smallint(5) unsigned NOT NULL default '0',
  `alphalost` smallint(5) unsigned NOT NULL default '0',
  `bravowon` smallint(5) unsigned NOT NULL default '0',
  `bravolost` smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_plr_data_soldat` (
  `dataid` int(10) unsigned NOT NULL default '0',
  `alphakills` smallint(5) unsigned NOT NULL default '0',
  `bravokills` smallint(5) unsigned NOT NULL default '0',
  `alphadeaths` smallint(5) unsigned NOT NULL default '0',
  `bravodeaths` smallint(5) unsigned NOT NULL default '0',
  `joinedalpha` smallint(5) unsigned NOT NULL default '0',
  `joinedbravo` smallint(5) unsigned NOT NULL default '0',
  `joinedspectator` smallint(5) unsigned NOT NULL default '0',
  `alphawon` smallint(5) unsigned NOT NULL default '0',
  `alphalost` smallint(5) unsigned NOT NULL default '0',
  `bravowon` smallint(5) unsigned NOT NULL default '0',
  `bravolost` smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_plr_maps_soldat` (
  `dataid` int(10) unsigned NOT NULL default '0',
  `alphakills` smallint(5) unsigned NOT NULL default '0',
  `bravokills` smallint(5) unsigned NOT NULL default '0',
  `alphadeaths` smallint(5) unsigned NOT NULL default '0',
  `bravodeaths` smallint(5) unsigned NOT NULL default '0',
  `joinedalpha` smallint(5) unsigned NOT NULL default '0',
  `joinedbravo` smallint(5) unsigned NOT NULL default '0',
  `joinedspectator` smallint(5) unsigned NOT NULL default '0',
  `alphawon` smallint(5) unsigned NOT NULL default '0',
  `alphalost` smallint(5) unsigned NOT NULL default '0',
  `bravowon` smallint(5) unsigned NOT NULL default '0',
  `bravolost` smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
