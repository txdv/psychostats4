-- MySQL dump 10.11
--
-- Host: localhost    Database: psychostats
-- ------------------------------------------------------
-- Server version	5.0.32-Debian_3-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `ps_awards`
--

DROP TABLE IF EXISTS `ps_awards`;
CREATE TABLE `ps_awards` (
  `id` int(10) unsigned NOT NULL default '0',
  `awardid` int(10) unsigned NOT NULL default '0',
  `awardtype` enum('player','weapon','weaponclass') NOT NULL default 'player',
  `awardname` varchar(128) NOT NULL default '',
  `awarddate` date default NULL,
  `awardrange` enum('month','week','day') NOT NULL default 'month',
  `awardweapon` varchar(64) NOT NULL default '',
  `awardcomplete` tinyint(1) unsigned NOT NULL default '1',
  `topplrid` int(10) unsigned NOT NULL default '0',
  `topplrvalue` float NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `awardid` (`awardid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_awards_plrs`
--

DROP TABLE IF EXISTS `ps_awards_plrs`;
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

--
-- Table structure for table `ps_clan`
--

DROP TABLE IF EXISTS `ps_clan`;
CREATE TABLE `ps_clan` (
  `clanid` int(10) unsigned NOT NULL default '0',
  `clantag` varchar(32) NOT NULL default '',
  `locked` tinyint(1) unsigned NOT NULL default '0',
  `allowrank` tinyint(1) unsigned NOT NULL default '0',
  PRIMARY KEY  (`clanid`),
  UNIQUE KEY `clantag` (`clantag`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_clan_profile`
--

DROP TABLE IF EXISTS `ps_clan_profile`;
CREATE TABLE `ps_clan_profile` (
  `clantag` varchar(32) NOT NULL default '',
  `name` varchar(128) NOT NULL default '',
  `logo` text NOT NULL,
  `email` varchar(128) NOT NULL default '',
  `icon` varchar(64) NOT NULL default '',
  `website` varchar(255) NOT NULL default '',
  `aim` varchar(64) NOT NULL default '',
  `icq` varchar(16) NOT NULL default '',
  `msn` varchar(128) NOT NULL default '',
  PRIMARY KEY  (`clantag`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_config`
--

DROP TABLE IF EXISTS `ps_config`;
CREATE TABLE `ps_config` (
  `id` int(10) unsigned NOT NULL default '0',
  `idx` smallint(6) NOT NULL default '0',
  `conftype` varchar(32) NOT NULL default 'main',
  `section` varchar(128) NOT NULL default '',
  `var` varchar(128) NOT NULL default '',
  `value` text NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `conftype` (`conftype`,`idx`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_config_awards`
--

DROP TABLE IF EXISTS `ps_config_awards`;
CREATE TABLE `ps_config_awards` (
  `id` int(10) unsigned NOT NULL default '0',
  `enabled` tinyint(1) unsigned NOT NULL default '1',
  `type` enum('player','weapon','weaponclass') NOT NULL default 'player',
  `class` varchar(64) NOT NULL default 'empty',
  `name` varchar(128) NOT NULL default '',
  `groupname` varchar(128) NOT NULL default '',
  `expr` varchar(255) NOT NULL default '',
  `order` enum('desc','asc') NOT NULL default 'desc',
  `where` varchar(255) NOT NULL default '',
  `limit` smallint(5) unsigned NOT NULL default '0',
  `format` varchar(64) NOT NULL default '',
  `gametype` varchar(32) NOT NULL default '',
  `modtype` varchar(32) NOT NULL default '',
  `desc` text NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_config_clantags`
--

DROP TABLE IF EXISTS `ps_config_clantags`;
CREATE TABLE `ps_config_clantags` (
  `id` int(10) unsigned NOT NULL default '0',
  `idx` int(10) unsigned NOT NULL default '0',
  `clantag` varchar(128) NOT NULL default '',
  `overridetag` varchar(64) NOT NULL default '',
  `pos` enum('left','right') NOT NULL default 'left',
  `type` enum('plain','regex') NOT NULL default 'plain',
  `example` varchar(64) NOT NULL default '',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_config_layout`
--

DROP TABLE IF EXISTS `ps_config_layout`;
CREATE TABLE `ps_config_layout` (
  `id` smallint(5) unsigned NOT NULL auto_increment,
  `conftype` varchar(32) NOT NULL default '',
  `section` varchar(128) NOT NULL default '',
  `var` varchar(128) NOT NULL default '',
  `type` enum('none','text','textarea','checkbox','select','boolean') NOT NULL default 'text',
  `options` text NOT NULL,
  `verifycodes` varchar(8) NOT NULL default '',
  `multiple` tinyint(1) unsigned NOT NULL default '0',
  `locked` tinyint(1) unsigned NOT NULL default '0',
  `comment` text NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=155 DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_config_plrbans`
--

DROP TABLE IF EXISTS `ps_config_plrbans`;
CREATE TABLE `ps_config_plrbans` (
  `id` int(10) unsigned NOT NULL default '0',
  `bandate` int(10) unsigned NOT NULL default '0',
  `enabled` tinyint(1) unsigned NOT NULL default '1',
  `matchtype` enum('worldid','ipaddr','name') NOT NULL default 'worldid',
  `matchstr` varchar(128) NOT NULL default '',
  `reason` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`id`),
  UNIQUE KEY `matchtype` (`matchtype`,`matchstr`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_config_plrbonuses`
--

DROP TABLE IF EXISTS `ps_config_plrbonuses`;
CREATE TABLE `ps_config_plrbonuses` (
  `id` int(10) unsigned NOT NULL default '0',
  `gametype` varchar(32) NOT NULL default '',
  `modtype` varchar(32) NOT NULL default '',
  `event` varchar(64) NOT NULL default '',
  `enactor` int(11) NOT NULL default '0',
  `enactor_team` int(11) NOT NULL default '0',
  `victim` int(11) NOT NULL default '0',
  `victim_team` int(11) NOT NULL default '0',
  `desc` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`id`),
  KEY `gametype` (`gametype`,`modtype`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_config_servers`
--

DROP TABLE IF EXISTS `ps_config_servers`;
CREATE TABLE `ps_config_servers` (
  `id` smallint(5) unsigned NOT NULL,
  `serverip` int(10) unsigned NOT NULL,
  `serverport` smallint(5) unsigned NOT NULL default '27015',
  `displayip` varchar(32) default NULL,
  `query` varchar(16) NOT NULL,
  `rcon` varchar(32) default NULL,
  `enabled` tinyint(1) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`),
  UNIQUE KEY `serverip` (`serverip`,`serverport`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_errlog`
--

DROP TABLE IF EXISTS `ps_errlog`;
CREATE TABLE `ps_errlog` (
  `id` int(10) unsigned NOT NULL default '0',
  `timestamp` int(10) unsigned NOT NULL default '0',
  `severity` enum('info','warning','fatal') NOT NULL default 'info',
  `userid` int(10) unsigned default NULL,
  `msg` text NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `timestamp` (`timestamp`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_geoip_cc`
--

DROP TABLE IF EXISTS `ps_geoip_cc`;
CREATE TABLE `ps_geoip_cc` (
  `cc` char(2) NOT NULL,
  `cn` varchar(50) NOT NULL,
  PRIMARY KEY  (`cc`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_geoip_ip`
--

DROP TABLE IF EXISTS `ps_geoip_ip`;
CREATE TABLE `ps_geoip_ip` (
  `cc` char(2) NOT NULL,
  `start` int(10) unsigned NOT NULL,
  `end` int(10) unsigned NOT NULL,
  KEY `cc` (`cc`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_map`
--

DROP TABLE IF EXISTS `ps_map`;
CREATE TABLE `ps_map` (
  `mapid` smallint(5) unsigned NOT NULL default '0',
  `uniqueid` varchar(32) NOT NULL default '',
  `name` varchar(128) NOT NULL default '',
  PRIMARY KEY  (`mapid`),
  UNIQUE KEY `uniqueid` (`uniqueid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_map_data`
--

DROP TABLE IF EXISTS `ps_map_data`;
CREATE TABLE `ps_map_data` (
  `dataid` int(10) unsigned NOT NULL default '0',
  `mapid` smallint(5) unsigned NOT NULL default '0',
  `statdate` date NOT NULL default '0000-00-00',
  `games` smallint(5) unsigned NOT NULL default '0',
  `rounds` smallint(5) unsigned NOT NULL default '0',
  `kills` smallint(5) unsigned NOT NULL default '0',
  `suicides` smallint(5) unsigned NOT NULL default '0',
  `ffkills` smallint(5) unsigned NOT NULL default '0',
  `connections` smallint(5) unsigned NOT NULL default '0',
  `onlinetime` int(10) unsigned NOT NULL default '0',
  `lasttime` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`),
  UNIQUE KEY `mapid` (`mapid`,`statdate`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_plr`
--

DROP TABLE IF EXISTS `ps_plr`;
CREATE TABLE `ps_plr` (
  `plrid` int(10) unsigned NOT NULL default '0',
  `uniqueid` varchar(128) NOT NULL default '',
  `firstseen` int(10) unsigned NOT NULL default '0',
  `clanid` int(10) unsigned NOT NULL default '0',
  `rank` mediumint(8) unsigned NOT NULL default '0',
  `prevrank` mediumint(8) unsigned NOT NULL default '0',
  `skill` float(8,2) NOT NULL default '0.00',
  `prevskill` float(8,2) NOT NULL default '0.00',
  `lastdecay` int(10) unsigned NOT NULL,
  `allowrank` tinyint(1) unsigned NOT NULL default '1',
  PRIMARY KEY  (`plrid`),
  UNIQUE KEY `uniqueid` (`uniqueid`),
  KEY `allowrank` (`allowrank`,`clanid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_plr_aliases`
--

DROP TABLE IF EXISTS `ps_plr_aliases`;
CREATE TABLE `ps_plr_aliases` (
  `id` int(10) unsigned NOT NULL default '0',
  `uniqueid` varchar(128) NOT NULL default '',
  `alias` varchar(128) NOT NULL default '',
  PRIMARY KEY  (`id`),
  KEY `uniqueid` (`uniqueid`),
  KEY `alias` (`alias`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_plr_data`
--

DROP TABLE IF EXISTS `ps_plr_data`;
CREATE TABLE `ps_plr_data` (
  `dataid` int(10) unsigned NOT NULL default '0',
  `plrid` int(10) unsigned NOT NULL default '0',
  `statdate` date NOT NULL default '0000-00-00',
  `dayskill` float(8,2) NOT NULL default '0.00',
  `dayrank` int(10) unsigned NOT NULL default '0',
  `connections` smallint(5) unsigned NOT NULL default '0',
  `kills` smallint(5) unsigned NOT NULL default '0',
  `deaths` smallint(5) unsigned NOT NULL default '0',
  `headshotkills` smallint(5) unsigned NOT NULL default '0',
  `headshotdeaths` smallint(5) unsigned NOT NULL default '0',
  `ffkills` smallint(5) unsigned NOT NULL default '0',
  `ffdeaths` smallint(5) unsigned NOT NULL default '0',
  `kills_streak` smallint(5) unsigned NOT NULL default '0',
  `deaths_streak` smallint(5) unsigned NOT NULL default '0',
  `damage` int(10) unsigned NOT NULL default '0',
  `shots` smallint(5) unsigned NOT NULL default '0',
  `hits` smallint(5) unsigned NOT NULL default '0',
  `suicides` smallint(5) unsigned NOT NULL default '0',
  `games` smallint(5) unsigned NOT NULL default '0',
  `rounds` smallint(5) unsigned NOT NULL default '0',
  `kicked` smallint(5) unsigned NOT NULL default '0',
  `banned` smallint(5) unsigned NOT NULL default '0',
  `cheated` smallint(5) unsigned NOT NULL default '0',
  `totalbonus` smallint(6) NOT NULL default '0',
  `onlinetime` int(10) unsigned NOT NULL default '0',
  `lasttime` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`),
  UNIQUE KEY `plrid` (`plrid`,`statdate`),
  KEY `statdate` (`statdate`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_plr_ids`
--

DROP TABLE IF EXISTS `ps_plr_ids`;
CREATE TABLE `ps_plr_ids` (
  `id` int(10) unsigned NOT NULL default '0',
  `plrid` int(10) unsigned NOT NULL default '0',
  `name` varchar(128) NOT NULL default '',
  `worldid` varchar(128) NOT NULL,
  `ipaddr` int(10) unsigned NOT NULL default '0',
  `totaluses` int(10) unsigned NOT NULL default '1',
  PRIMARY KEY  (`id`),
  UNIQUE KEY `uniqplr` (`name`,`worldid`,`ipaddr`),
  KEY `plrid` (`plrid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_plr_maps`
--

DROP TABLE IF EXISTS `ps_plr_maps`;
CREATE TABLE `ps_plr_maps` (
  `dataid` int(10) unsigned NOT NULL default '0',
  `plrid` int(10) unsigned NOT NULL default '0',
  `mapid` int(10) unsigned NOT NULL default '0',
  `statdate` date NOT NULL default '0000-00-00',
  `games` smallint(5) unsigned NOT NULL default '0',
  `rounds` smallint(5) unsigned NOT NULL default '0',
  `kills` smallint(5) unsigned NOT NULL default '0',
  `deaths` smallint(5) unsigned NOT NULL default '0',
  `ffkills` smallint(5) unsigned NOT NULL default '0',
  `ffdeaths` smallint(5) unsigned NOT NULL default '0',
  `connections` smallint(5) unsigned NOT NULL default '0',
  `onlinetime` int(10) unsigned NOT NULL default '0',
  `lasttime` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`),
  UNIQUE KEY `plrid` (`plrid`,`mapid`,`statdate`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_plr_profile`
--

DROP TABLE IF EXISTS `ps_plr_profile`;
CREATE TABLE `ps_plr_profile` (
  `uniqueid` varchar(128) NOT NULL default '',
  `userid` int(11) default NULL,
  `name` varchar(128) NOT NULL default '',
  `email` varchar(128) NOT NULL default '',
  `aim` varchar(64) NOT NULL default '',
  `icq` varchar(16) NOT NULL default '',
  `msn` varchar(128) NOT NULL default '',
  `website` varchar(128) NOT NULL default '',
  `icon` varchar(64) NOT NULL default '',
  `cc` varchar(2) NOT NULL default '',
  `logo` text NOT NULL,
  `namelocked` tinyint(1) unsigned NOT NULL default '0',
  PRIMARY KEY  (`uniqueid`),
  UNIQUE KEY `userid` (`userid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_plr_sessions`
--

DROP TABLE IF EXISTS `ps_plr_sessions`;
CREATE TABLE `ps_plr_sessions` (
  `dataid` int(10) unsigned NOT NULL default '0',
  `plrid` int(10) unsigned NOT NULL default '0',
  `sessionstart` int(10) unsigned NOT NULL default '0',
  `sessionend` int(10) unsigned NOT NULL default '0',
  `skill` float(8,2) NOT NULL default '0.00',
  `kills` smallint(5) unsigned NOT NULL default '0',
  `deaths` smallint(5) unsigned NOT NULL default '0',
  `headshotkills` smallint(5) unsigned NOT NULL default '0',
  `headshotdeaths` smallint(5) unsigned NOT NULL default '0',
  `ffkills` smallint(5) unsigned NOT NULL default '0',
  `ffdeaths` smallint(5) unsigned NOT NULL default '0',
  `damage` int(10) unsigned NOT NULL default '0',
  `shots` smallint(5) unsigned NOT NULL default '0',
  `hits` smallint(5) unsigned NOT NULL default '0',
  `suicides` smallint(5) unsigned NOT NULL default '0',
  `totalbonus` smallint(6) NOT NULL default '0',
  PRIMARY KEY  (`dataid`),
  KEY `plrid` (`plrid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_plr_victims`
--

DROP TABLE IF EXISTS `ps_plr_victims`;
CREATE TABLE `ps_plr_victims` (
  `dataid` int(10) unsigned NOT NULL default '0',
  `plrid` int(10) unsigned NOT NULL default '0',
  `victimid` int(10) unsigned NOT NULL default '0',
  `statdate` date NOT NULL default '0000-00-00',
  `kills` smallint(5) unsigned NOT NULL default '0',
  `deaths` smallint(5) unsigned NOT NULL default '0',
  `headshotkills` smallint(5) unsigned NOT NULL default '0',
  `headshotdeaths` smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`),
  UNIQUE KEY `plrid` (`plrid`,`victimid`,`statdate`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_plr_weapons`
--

DROP TABLE IF EXISTS `ps_plr_weapons`;
CREATE TABLE `ps_plr_weapons` (
  `dataid` int(10) unsigned NOT NULL default '0',
  `plrid` int(10) unsigned NOT NULL default '0',
  `weaponid` smallint(5) unsigned NOT NULL default '0',
  `statdate` date NOT NULL default '0000-00-00',
  `kills` smallint(5) unsigned NOT NULL default '0',
  `deaths` smallint(5) unsigned NOT NULL default '0',
  `headshotkills` smallint(5) unsigned NOT NULL default '0',
  `headshotdeaths` smallint(5) unsigned NOT NULL default '0',
  `shots` int(10) unsigned NOT NULL default '0',
  `hits` int(10) unsigned NOT NULL default '0',
  `damage` int(10) unsigned NOT NULL default '0',
  `ffkills` smallint(5) unsigned NOT NULL default '0',
  `ffdeaths` smallint(5) unsigned NOT NULL default '0',
  `shot_head` smallint(5) unsigned NOT NULL default '0',
  `shot_chest` smallint(5) unsigned NOT NULL default '0',
  `shot_stomach` smallint(5) unsigned NOT NULL default '0',
  `shot_leftarm` smallint(5) unsigned NOT NULL default '0',
  `shot_rightarm` smallint(5) unsigned NOT NULL default '0',
  `shot_leftleg` smallint(5) unsigned NOT NULL default '0',
  `shot_rightleg` smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`),
  KEY `plrweaps` (`plrid`,`weaponid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_search`
--

DROP TABLE IF EXISTS `ps_search`;
CREATE TABLE `ps_search` (
  `session_id` varchar(32) NOT NULL default '',
  `time` int(10) unsigned NOT NULL default '0',
  `query` varchar(255) NOT NULL default '',
  `results` text NOT NULL,
  PRIMARY KEY  (`session_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_sessions`
--

DROP TABLE IF EXISTS `ps_sessions`;
CREATE TABLE `ps_sessions` (
  `session_id` char(32) NOT NULL default '',
  `session_userid` int(10) unsigned NOT NULL default '0',
  `session_start` int(10) unsigned NOT NULL default '0',
  `session_last` int(10) unsigned NOT NULL default '0',
  `session_ip` int(10) unsigned NOT NULL default '0',
  `session_logged_in` tinyint(1) NOT NULL default '0',
  `session_is_bot` tinyint(3) unsigned NOT NULL default '0',
  PRIMARY KEY  (`session_id`),
  KEY `session_userid` (`session_userid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_state`
--

DROP TABLE IF EXISTS `ps_state`;
CREATE TABLE `ps_state` (
  `id` smallint(5) unsigned NOT NULL default '0',
  `logsource` varchar(255) NOT NULL default '',
  `lastupdate` int(10) unsigned NOT NULL default '0',
  `timestamp` int(10) unsigned NOT NULL default '0',
  `file` varchar(255) NOT NULL default '',
  `line` int(11) NOT NULL default '0',
  `map` varchar(32) NOT NULL default '',
  PRIMARY KEY  (`id`),
  UNIQUE KEY `logsource` (`logsource`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_state_plrs`
--

DROP TABLE IF EXISTS `ps_state_plrs`;
CREATE TABLE `ps_state_plrs` (
  `id` smallint(5) unsigned NOT NULL default '0',
  `plrid` int(10) unsigned NOT NULL default '0',
  `uid` smallint(5) unsigned NOT NULL default '0',
  `isdead` tinyint(1) unsigned NOT NULL default '0',
  `team` varchar(32) NOT NULL default '',
  `role` varchar(32) NOT NULL default '',
  `plrsig` varchar(255) NOT NULL,
  `name` varchar(128) NOT NULL,
  `worldid` varchar(128) NOT NULL,
  `ipaddr` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`,`plrid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_user`
--

DROP TABLE IF EXISTS `ps_user`;
CREATE TABLE `ps_user` (
  `userid` int(11) NOT NULL default '0',
  `username` varchar(64) NOT NULL default '',
  `password` varchar(32) NOT NULL default '',
  `session_last` int(10) unsigned NOT NULL default '0',
  `lastvisit` int(10) unsigned NOT NULL default '0',
  `accesslevel` tinyint(3) NOT NULL default '1',
  `confirmed` tinyint(1) unsigned NOT NULL default '0',
  PRIMARY KEY  (`userid`),
  UNIQUE KEY `username` (`username`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_weapon`
--

DROP TABLE IF EXISTS `ps_weapon`;
CREATE TABLE `ps_weapon` (
  `weaponid` smallint(5) unsigned NOT NULL default '0',
  `uniqueid` varchar(32) NOT NULL default '',
  `name` varchar(128) NOT NULL default '',
  `skillweight` float(4,2) NOT NULL default '0.00',
  `class` varchar(32) NOT NULL default '',
  PRIMARY KEY  (`weaponid`),
  UNIQUE KEY `uniqueid` (`uniqueid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_weapon_data`
--

DROP TABLE IF EXISTS `ps_weapon_data`;
CREATE TABLE `ps_weapon_data` (
  `dataid` int(10) unsigned NOT NULL default '0',
  `weaponid` smallint(5) unsigned NOT NULL default '0',
  `statdate` date NOT NULL default '0000-00-00',
  `kills` smallint(5) unsigned NOT NULL default '0',
  `ffkills` smallint(5) unsigned NOT NULL default '0',
  `headshotkills` smallint(5) unsigned NOT NULL default '0',
  `shots` int(10) unsigned NOT NULL default '0',
  `hits` int(10) unsigned NOT NULL default '0',
  `damage` int(10) unsigned NOT NULL default '0',
  `shot_head` smallint(5) unsigned NOT NULL default '0',
  `shot_chest` smallint(5) unsigned NOT NULL default '0',
  `shot_stomach` smallint(5) unsigned NOT NULL default '0',
  `shot_leftarm` smallint(5) unsigned NOT NULL default '0',
  `shot_rightarm` smallint(5) unsigned NOT NULL default '0',
  `shot_leftleg` smallint(5) unsigned NOT NULL default '0',
  `shot_rightleg` smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`),
  UNIQUE KEY `weaponid` (`weaponid`,`statdate`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2007-02-19  7:00:49
