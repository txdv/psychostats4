CREATE TABLE `ps_awards` (
  `id` int(10) unsigned NOT NULL default '0',
  `awardid` int(10) unsigned NOT NULL default '0',
  `awardtype` enum('player','weapon','weaponclass') NOT NULL default 'player',
  `awardname` varchar(128) NOT NULL default '',
  `awarddate` date NOT NULL,
  `awardrange` enum('month','week','day') NOT NULL default 'month',
  `awardweapon` varchar(64) default NULL,
  `awardcomplete` tinyint(1) unsigned NOT NULL default '1',
  `interpolate` text,
  `topplrid` int(10) unsigned NOT NULL default '0',
  `topplrvalue` varchar(16) NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `awardid` (`awardid`),
  KEY `awardrange` (`awardrange`,`awarddate`),
  KEY `awarddate` (`awarddate`),
  KEY `topplrid` (`topplrid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_awards_plrs` (
  `id` int(10) unsigned NOT NULL default '0',
  `idx` tinyint(3) unsigned NOT NULL default '0',
  `awardid` int(10) unsigned NOT NULL default '0',
  `plrid` int(10) unsigned NOT NULL default '0',
  `value` float NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `awardid` (`awardid`),
  KEY `plrid` (`plrid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_clan` (
  `clanid` int(10) unsigned NOT NULL default '0',
  `clantag` varchar(32) NOT NULL default '',
  `locked` tinyint(1) unsigned NOT NULL default '0',
  `allowrank` tinyint(1) unsigned NOT NULL default '0',
  PRIMARY KEY  (`clanid`),
  UNIQUE KEY `clantag` (`clantag`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_clan_profile` (
  `clantag` varchar(32) NOT NULL default '',
  `name` varchar(128) default NULL,
  `logo` text,
  `email` varchar(128) default NULL,
  `icon` varchar(64) default NULL,
  `website` varchar(255) default NULL,
  `aim` varchar(64) default NULL,
  `icq` varchar(16) default NULL,
  `msn` varchar(128) default NULL,
  `cc` varchar(2) default NULL,
  PRIMARY KEY  (`clantag`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_config` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `conftype` varchar(32) NOT NULL default 'main',
  `section` varchar(128) default NULL,
  `var` varchar(128) default NULL,
  `value` text NOT NULL,
  `label` varchar(128) default NULL,
  `type` enum('none','text','textarea','checkbox','select','boolean') NOT NULL default 'text',
  `locked` tinyint(1) unsigned NOT NULL default '0',
  `verifycodes` varchar(64) default NULL,
  `options` text,
  `help` text,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `conftype` (`conftype`,`section`,`var`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_config_awards` (
  `id` int(10) unsigned NOT NULL default '0',
  `enabled` tinyint(1) unsigned NOT NULL default '1',
  `idx` int(11) NOT NULL,
  `type` enum('player','weapon','weaponclass') NOT NULL default 'player',
  `negative` tinyint(1) unsigned NOT NULL default '0',
  `class` varchar(64) NOT NULL,
  `name` varchar(128) NOT NULL default '',
  `groupname` varchar(128) NOT NULL default '',
  `phrase` varchar(255) NOT NULL,
  `expr` varchar(255) NOT NULL default '',
  `order` enum('desc','asc') NOT NULL default 'desc',
  `where` varchar(255) NOT NULL default '',
  `limit` smallint(5) unsigned NOT NULL default '1',
  `format` varchar(64) NOT NULL default '',
  `gametype` varchar(32) default NULL,
  `modtype` varchar(32) default NULL,
  `rankedonly` tinyint(1) unsigned NOT NULL default '1',
  `description` text NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_config_clantags` (
  `id` int(10) unsigned NOT NULL default '0',
  `idx` int(10) unsigned NOT NULL default '0',
  `clantag` varchar(128) NOT NULL default '',
  `alias` varchar(64) default NULL,
  `pos` enum('left','right') default NULL,
  `type` enum('plain','regex') NOT NULL default 'plain',
  `example` varchar(64) NOT NULL default '',
  PRIMARY KEY  (`id`),
  KEY `idx` (`type`,`idx`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_config_events` (
  `id` int(10) unsigned NOT NULL,
  `gametype` varchar(255) NOT NULL,
  `modtype` varchar(255) default NULL,
  `eventname` varchar(64) NOT NULL,
  `alias` varchar(64) default NULL,
  `regex` varchar(255) NOT NULL,
  `idx` smallint(6) NOT NULL default '0',
  `ignore` tinyint(1) unsigned NOT NULL default '0',
  `codefile` varchar(255) default NULL,
  PRIMARY KEY  (`id`),
  KEY `idx` (`idx`),
  KEY `gametype` (`gametype`),
  KEY `modtype` (`modtype`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_config_overlays` (
  `id` int(10) unsigned NOT NULL,
  `gametype` varchar(32) NOT NULL,
  `modtype` varchar(32) NOT NULL,
  `map` varchar(64) NOT NULL,
  `minx` smallint(6) NOT NULL,
  `miny` smallint(6) NOT NULL,
  `maxx` smallint(6) NOT NULL,
  `maxy` smallint(6) NOT NULL,
  `width` smallint(5) unsigned NOT NULL,
  `height` smallint(5) unsigned NOT NULL,
  `flipv` tinyint(1) unsigned NOT NULL,
  `fliph` tinyint(1) unsigned NOT NULL,
  `rotate` smallint(6) NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `gametype` (`gametype`,`modtype`,`map`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_config_logsources` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `type` varchar(64) NOT NULL default 'file',
  `tz` varchar(64) default NULL,
  `path` varchar(255) default NULL,
  `host` varchar(255) default NULL,
  `port` smallint(5) unsigned default NULL,
  `passive` tinyint(1) unsigned NOT NULL default '0',
  `username` varchar(128) default NULL,
  `password` varchar(128) default NULL,
  `recursive` tinyint(1) unsigned NOT NULL default '0',
  `depth` tinyint(3) unsigned default NULL,
  `skiplast` tinyint(1) unsigned NOT NULL default '0',
  `skiplastline` tinyint(1) unsigned NOT NULL default '0',
  `delete` tinyint(1) unsigned NOT NULL default '0',
  `options` text,
  `defaultmap` varchar(128) NOT NULL default 'unknown',
  `gametype` varchar(32) NOT NULL,
  `modtype` varchar(32) NOT NULL,
  `enabled` tinyint(1) unsigned NOT NULL default '1',
  `idx` int(11) NOT NULL default '0',
  `lastupdate` int(10) unsigned default NULL,
  PRIMARY KEY  (`id`),
  KEY `idx` (`idx`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_config_plrbans` (
  `id` int(10) unsigned NOT NULL default '0',
  `bandate` int(10) unsigned NOT NULL default '0',
  `enabled` tinyint(1) unsigned NOT NULL default '1',
  `matchtype` enum('guid','ipaddr','name') NOT NULL default 'guid',
  `matchstr` varchar(128) NOT NULL default '',
  `reason` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`id`),
  UNIQUE KEY `match` (`matchtype`,`matchstr`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_config_plrbonuses` (
  `id` int(10) unsigned NOT NULL default '0',
  `eventname` varchar(64) NOT NULL,
  `enactor` int(11) NOT NULL default '0',
  `enactor_team` int(11) NOT NULL default '0',
  `victim` int(11) NOT NULL default '0',
  `victim_team` int(11) NOT NULL default '0',
  `description` varchar(255) default NULL,
  `gametype` varchar(255) default NULL,
  `modtype` varchar(255) default NULL,
  PRIMARY KEY  (`id`),
  KEY `gametype` (`gametype`),
  KEY `modtype` (`modtype`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_config_servers` (
  `id` smallint(5) unsigned NOT NULL,
  `host` varchar(255) NOT NULL,
  `port` smallint(5) unsigned NOT NULL default '27015',
  `alt` varchar(255) default NULL,
  `querytype` varchar(32) NOT NULL,
  `rcon` varchar(64) default NULL,
  `cc` char(2) default NULL,
  `idx` smallint(6) NOT NULL,
  `enabled` tinyint(1) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`),
  UNIQUE KEY `hostport` (`host`,`port`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_config_themes` (
  `name` varchar(128) NOT NULL,
  `parent` varchar(128) default NULL,
  `enabled` tinyint(1) unsigned NOT NULL,
  `version` varchar(32) NOT NULL default '1.0',
  `title` varchar(128) NOT NULL,
  `author` varchar(128) default NULL,
  `website` varchar(128) default NULL,
  `source` varchar(255) default NULL,
  `image` varchar(255) default NULL,
  `description` text,
  PRIMARY KEY  (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_heatmaps` (
  `heatid` int(10) unsigned NOT NULL,
  `heatkey` char(40) character set ascii NOT NULL,
  `statdate` date NOT NULL,
  `enddate` date default NULL,
  `hour` tinyint(2) unsigned default NULL,
  `who` enum('killer','victim','both') NOT NULL default 'victim',
  `mapid` smallint(5) unsigned NOT NULL,
  `weaponid` smallint(5) unsigned default NULL,
  `pid` int(10) unsigned default NULL,
  `kid` int(10) unsigned default NULL,
  `team` enum('CT','TERRORIST','BLUE','RED','ALLIES','AXIS','MARINES','ALIENS') default NULL,
  `kteam` enum('CT','TERRORIST','BLUE','RED','ALLIES','AXIS','MARINES','ALIENS') default NULL,
  `vid` int(10) unsigned default NULL,
  `vteam` enum('CT','TERRORIST','BLUE','RED','ALLIES','AXIS','MARINES','ALIENS') default NULL,
  `headshot` tinyint(1) unsigned default NULL,
  `datatype` enum('blob','file') NOT NULL default 'blob',
  `datafile` varchar(255) default NULL,
  `datablob` mediumblob,
  `lastupdate` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`heatid`),
  UNIQUE KEY `heatkey` (`heatkey`,`statdate`,`enddate`,`hour`,`who`),
  KEY `mapid` (`mapid`,`heatkey`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_errlog` (
  `id` int(10) unsigned NOT NULL default '0',
  `timestamp` int(10) unsigned NOT NULL default '0',
  `severity` enum('info','warning','fatal') NOT NULL default 'info',
  `userid` int(10) unsigned default NULL,
  `msg` text NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `timestamp` (`timestamp`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_geoip_cc` (
  `cc` char(2) NOT NULL,
  `cn` varchar(50) NOT NULL,
  PRIMARY KEY  (`cc`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_geoip_ip` (
  `cc` char(2) NOT NULL,
  `start` int(10) unsigned NOT NULL,
  `end` int(10) unsigned NOT NULL,
  PRIMARY KEY  (`start`,`end`),
  KEY `cc` (`cc`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_map` (
  `mapid` smallint(5) unsigned NOT NULL auto_increment,
  `name` varchar(64) NOT NULL,
  `gametype` enum('halflife') NOT NULL,
  `modtype` enum('cstrike','tf','dod') NOT NULL,
  PRIMARY KEY  (`mapid`),
  UNIQUE KEY `map` (`name`,`gametype`,`modtype`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_map_data` (
  `dataid` int(10) unsigned NOT NULL auto_increment,
  `mapid` smallint(5) unsigned NOT NULL default '0',
  `statdate` date NOT NULL default '0000-00-00',
  `firstseen` int(10) unsigned NOT NULL default '0',
  `lastseen` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`),
  UNIQUE KEY `mapid` (`mapid`,`statdate`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_map_hourly` (
  `dataid` int(10) unsigned NOT NULL default '0',
  `mapid` smallint(5) unsigned NOT NULL default '0',
  `statdate` date NOT NULL default '0000-00-00',
  `hour` smallint(2) unsigned NOT NULL default '0',
  `games` smallint(5) unsigned NOT NULL default '0',
  `rounds` smallint(5) unsigned NOT NULL default '0',
  `kills` smallint(5) unsigned NOT NULL default '0',
  `connections` smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`),
  UNIQUE KEY `mapid` (`mapid`,`statdate`,`hour`),
  KEY `global` (`statdate`,`hour`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_map_spatial` (
  `mapid` smallint(5) unsigned NOT NULL,
  `weaponid` smallint(5) unsigned NOT NULL,
  `statdate` date NOT NULL,
  `hour` tinyint(2) unsigned NOT NULL,
  `roundtime` smallint(5) unsigned NOT NULL default '0',
  `kid` int(10) unsigned NOT NULL,
  `kx` smallint(6) NOT NULL,
  `ky` smallint(6) NOT NULL,
  `kz` smallint(6) NOT NULL,
  `kteam` enum('CT','TERRORIST','BLUE','RED','ALLIES','AXIS','MARINES','ALIENS') default NULL,
  `vid` int(10) unsigned NOT NULL,
  `vx` smallint(6) NOT NULL,
  `vy` smallint(6) NOT NULL,
  `vz` smallint(6) NOT NULL,
  `vteam` enum('CT','TERRORIST','BLUE','RED','ALLIES','AXIS','MARINES','ALIENS') default NULL,
  `headshot` tinyint(1) unsigned NOT NULL,
  KEY `mapid` (`mapid`,`statdate`,`hour`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_live_entities` (
  `game_id` int(10) unsigned NOT NULL,
  `ent_id` int(11) NOT NULL,
  `ent_type` enum('unknown','player','bot','medkit','ammo','weapon','structure','turret','teleport') NOT NULL default 'unknown',
  `ent_name` varchar(255) NOT NULL,
  `ent_team` tinyint(3) unsigned NOT NULL default '0',
  `onlinetime` int(10) unsigned NOT NULL default '0',
  `kills` int(11) NOT NULL default '0',
  `deaths` int(10) unsigned NOT NULL default '0',
  `suicides` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`game_id`,`ent_id`,`ent_type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_live_events` (
  `game_id` int(10) unsigned NOT NULL,
  `event_idx` int(10) unsigned NOT NULL,
  `event_time` int(10) unsigned NOT NULL,
  `event_type` enum('ROUND_START','ROUND_END','PLR_CONNECT','PLR_DISCONNECT','PLR_SPAWN','PLR_MOVE','PLR_KILL','PLR_TEAM','PLR_NAME','PLR_HURT','PLR_BOMB_PICKUP','PLR_BOMB_DROPPED','PLR_BOMB_PLANTED','PLR_BOMB_DEFUSED','PLR_BOMB_EXPLODED') NOT NULL,
  `ent_id` int(11) default NULL,
  `ent_id2` int(11) default NULL,
  `xyz` varchar(20) default NULL,
  `weapon` varchar(32) default NULL,
  `value` varchar(255) default NULL,
  `json` text,
  PRIMARY KEY  (`game_id`,`event_idx`),
  KEY `game_time` (`game_id`,`event_time`),
  KEY `event_types` (`game_id`,`event_type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_live_games` (
  `game_id` int(10) unsigned NOT NULL auto_increment,
  `start_time` int(10) unsigned NOT NULL,
  `end_time` int(10) unsigned default NULL,
  `server_ip` int(10) unsigned default NULL,
  `server_port` smallint(5) unsigned default NULL,
  `server_name` varchar(255) default NULL,
  `game_name` varchar(255) default NULL,
  `gametype` varchar(32) NOT NULL,
  `modtype` varchar(32) default NULL,
  `map` varchar(64) default NULL,
  PRIMARY KEY  (`game_id`),
  KEY `start_time` (`start_time`,`end_time`),
  KEY `lastgame` (`server_ip`,`server_port`,`start_time`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_plr` (
  `plrid` int(10) unsigned NOT NULL auto_increment,
  `uniqueid` varchar(200) NOT NULL,
  `gametype` enum('halflife') NOT NULL,
  `modtype` enum('cstrike','tf','dod') NOT NULL,
  `firstseen` int(10) unsigned NOT NULL,
  `lastseen` int(10) unsigned NOT NULL,
  `activity` tinyint(3) unsigned NOT NULL default '0',
  `activity_updated` int(10) unsigned NOT NULL default '0',
  `clanid` int(10) unsigned default NULL,
  `skill` float(10,4) default NULL,
  `skill_yesterday` float(10,4) default NULL,
  `rank` int(10) unsigned default NULL,
  `rank_yesterday` int(10) unsigned default NULL,
  PRIMARY KEY  (`plrid`),
  UNIQUE KEY `uniqueid` (`uniqueid`,`gametype`,`modtype`),
  KEY `rank` (`rank`,`gametype`,`modtype`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_plr_aliases` (
  `id` int(10) unsigned NOT NULL default '0',
  `guid` varchar(128) NOT NULL,
  `alias` varchar(128) NOT NULL default '',
  PRIMARY KEY  (`id`),
  KEY `uniqueid` (`guid`),
  KEY `alias` (`alias`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_plr_bans` (
  `plrid` int(10) unsigned NOT NULL,
  `ban_date` int(10) unsigned NOT NULL default '0',
  `unban_date` int(10) unsigned default NULL,
  `ban_reason` varchar(255) default NULL,
  `unban_reason` varchar(255) default NULL,
  KEY `plrid` (`plrid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_plr_chat` (
  `plrid` int(10) unsigned NOT NULL,
  `timestamp` int(10) unsigned NOT NULL,
  `message` varchar(255) NOT NULL,
  `team` varchar(16) default NULL,
  `team_only` tinyint(1) unsigned NOT NULL default '0',
  `dead` tinyint(1) unsigned NOT NULL default '0',
  KEY `plrid` (`plrid`,`timestamp`),
  KEY `timestamp` (`timestamp`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_plr_data` (
  `dataid` int(10) unsigned NOT NULL auto_increment,
  `plrid` int(10) unsigned NOT NULL default '0',
  `statdate` date NOT NULL default '0000-00-00',
  `firstseen` int(10) unsigned NOT NULL default '0',
  `lastseen` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`),
  UNIQUE KEY `plr_game_day` (`plrid`,`statdate`),
  KEY `statdate` (`statdate`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_plr_ids_guid` (
  `plrid` int(10) unsigned NOT NULL default '0',
  `guid` varchar(128) NOT NULL,
  `totaluses` int(10) unsigned NOT NULL default '1',
  `firstseen` datetime NOT NULL,
  `lastseen` datetime NOT NULL,
  PRIMARY KEY  (`plrid`,`guid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_plr_ids_ipaddr` (
  `plrid` int(10) unsigned NOT NULL default '0',
  `ipaddr` int(10) unsigned NOT NULL default '0',
  `totaluses` int(10) unsigned NOT NULL default '1',
  `firstseen` datetime NOT NULL,
  `lastseen` datetime NOT NULL,
  PRIMARY KEY  (`plrid`,`ipaddr`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_plr_ids_name` (
  `plrid` int(10) unsigned NOT NULL default '0',
  `name` varchar(128) NOT NULL default '',
  `totaluses` int(10) unsigned NOT NULL default '1',
  `firstseen` datetime NOT NULL,
  `lastseen` datetime NOT NULL,
  PRIMARY KEY  (`plrid`,`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_plr_maps` (
  `dataid` int(10) unsigned NOT NULL auto_increment,
  `plrid` int(10) unsigned NOT NULL default '0',
  `mapid` int(10) unsigned NOT NULL default '0',
  `statdate` date NOT NULL default '0000-00-00',
  `firstseen` int(10) unsigned NOT NULL default '0',
  `lastseen` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`),
  UNIQUE KEY `plr_game_day` (`plrid`,`statdate`,`mapid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_plr_profile` (
  `uniqueid` varchar(128) NOT NULL,
  `name` varchar(128) NOT NULL default '',
  `name_locked` tinyint(1) unsigned NOT NULL default '0',
  `userid` int(10) unsigned default NULL,
  `email` varchar(128) default NULL,
  `aim` varchar(64) default NULL,
  `icq` varchar(16) default NULL,
  `msn` varchar(128) default NULL,
  `website` varchar(128) default NULL,
  `icon` varchar(64) default NULL,
  `cc` char(2) character set ascii default NULL,
  `latitude` double default NULL,
  `longitude` double default NULL,
  `logo` text,
  PRIMARY KEY  (`uniqueid`),
  KEY `name` (`name`),
  KEY `cc` (`cc`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_plr_roles` (
  `dataid` int(10) unsigned NOT NULL auto_increment,
  `plrid` int(10) unsigned NOT NULL default '0',
  `roleid` smallint(5) unsigned NOT NULL default '0',
  `statdate` date NOT NULL default '0000-00-00',
  `firstseen` int(10) unsigned NOT NULL default '0',
  `lastseen` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`),
  KEY `plr_game_day` (`plrid`,`statdate`,`roleid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_plr_sessions` (
  `dataid` int(10) unsigned NOT NULL default '0',
  `plrid` int(10) unsigned NOT NULL default '0',
  `mapid` int(10) unsigned NOT NULL default '0',
  `session_start` int(10) unsigned NOT NULL,
  `session_end` int(10) unsigned NOT NULL,
  `skill` float(8,2) NOT NULL default '0.00',
  `prevskill` float(8,2) NOT NULL default '0.00',
  PRIMARY KEY  (`dataid`),
  KEY `plrid` (`plrid`,`session_start`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_plr_victims` (
  `dataid` int(10) unsigned NOT NULL auto_increment,
  `plrid` int(10) unsigned NOT NULL default '0',
  `victimid` int(10) unsigned NOT NULL default '0',
  `statdate` date NOT NULL default '0000-00-00',
  `firstseen` int(10) unsigned NOT NULL default '0',
  `lastseen` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`),
  UNIQUE KEY `plr_game_day` (`plrid`,`statdate`,`victimid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_plr_weapons` (
  `dataid` int(10) unsigned NOT NULL auto_increment,
  `plrid` int(10) unsigned NOT NULL default '0',
  `weaponid` smallint(5) unsigned NOT NULL default '0',
  `statdate` date NOT NULL default '0000-00-00',
  `firstseen` int(10) unsigned NOT NULL default '0',
  `lastseen` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`),
  UNIQUE KEY `plr_game_day` (`plrid`,`statdate`,`weaponid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_plugins` (
  `plugin` varchar(64) NOT NULL,
  `version` varchar(32) NOT NULL,
  `enabled` tinyint(1) unsigned NOT NULL default '0',
  `idx` smallint(6) NOT NULL default '0',
  `installdate` int(10) unsigned NOT NULL default '0',
  `description` text NOT NULL,
  PRIMARY KEY  (`plugin`),
  KEY `enabled` (`enabled`,`idx`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_role` (
  `roleid` smallint(5) unsigned NOT NULL auto_increment,
  `name` varchar(64) NOT NULL,
  `gametype` enum('halflife') NOT NULL,
  `modtype` enum('cstrike','tf','dod') NOT NULL,
  PRIMARY KEY  (`roleid`),
  UNIQUE KEY `name` (`name`,`gametype`,`modtype`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_role_data` (
  `dataid` int(10) unsigned NOT NULL auto_increment,
  `roleid` smallint(5) unsigned NOT NULL default '0',
  `statdate` date NOT NULL default '0000-00-00',
  `firstseen` int(10) unsigned NOT NULL default '0',
  `lastseen` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`),
  UNIQUE KEY `roleid` (`roleid`,`statdate`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_search_results` (
  `search_id` char(32) NOT NULL,
  `session_id` char(32) NOT NULL,
  `phrase` varchar(255) NOT NULL,
  `result_total` int(10) unsigned NOT NULL default '0',
  `abs_total` int(10) unsigned NOT NULL default '0',
  `results` text,
  `query` text,
  `updated` datetime NOT NULL,
  PRIMARY KEY  (`search_id`),
  KEY `session_id` (`session_id`),
  KEY `updated` (`updated`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_sessions` (
  `session_id` char(32) NOT NULL default '',
  `session_userid` int(10) unsigned NOT NULL default '0',
  `session_start` int(10) unsigned NOT NULL default '0',
  `session_last` int(10) unsigned NOT NULL default '0',
  `session_ip` int(10) unsigned NOT NULL default '0',
  `session_logged_in` tinyint(1) NOT NULL default '0',
  `session_is_admin` tinyint(1) unsigned NOT NULL default '0',
  `session_is_bot` tinyint(1) unsigned NOT NULL default '0',
  `session_key` char(32) default NULL,
  `session_key_time` int(10) unsigned default NULL,
  PRIMARY KEY  (`session_id`),
  KEY `session_userid` (`session_userid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_state` (
  `id` int(10) unsigned NOT NULL,
  `last_update` int(10) unsigned NOT NULL default '0',
  `line` int(10) unsigned NOT NULL default '0',
  `pos` int(10) unsigned NOT NULL default '0',
  `file` varchar(255) default NULL,
  `game_state` mediumtext,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_user` (
  `userid` int(10) unsigned NOT NULL default '0',
  `username` varchar(64) NOT NULL default '',
  `password` varchar(32) NOT NULL default '',
  `session_last` int(10) unsigned NOT NULL default '0',
  `session_login_key` varchar(8) default NULL,
  `lastvisit` int(10) unsigned NOT NULL default '0',
  `accesslevel` tinyint(3) NOT NULL default '1',
  `confirmed` tinyint(1) unsigned NOT NULL default '0',
  PRIMARY KEY  (`userid`),
  UNIQUE KEY `username` (`username`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_weapon` (
  `weaponid` smallint(5) unsigned NOT NULL auto_increment,
  `name` varchar(32) NOT NULL,
  `gametype` enum('halflife') NOT NULL,
  `modtype` enum('cstrike','tf','dod') NOT NULL,
  `fullname` varchar(128) default NULL,
  `weight` float(4,2) default NULL,
  `class` varchar(32) default NULL,
  PRIMARY KEY  (`weaponid`),
  UNIQUE KEY `weapon` (`name`,`gametype`,`modtype`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
CREATE TABLE `ps_weapon_data` (
  `dataid` int(10) unsigned NOT NULL auto_increment,
  `weaponid` smallint(5) unsigned NOT NULL default '0',
  `statdate` date NOT NULL default '0000-00-00',
  `firstseen` int(10) unsigned NOT NULL default '0',
  `lastseen` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`),
  UNIQUE KEY `weaponid` (`weaponid`,`statdate`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
