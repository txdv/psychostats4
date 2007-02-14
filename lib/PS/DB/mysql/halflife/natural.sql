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
-- Table structure for table `ps_map_data_halflife_natural`
--

DROP TABLE IF EXISTS `ps_map_data_halflife_natural`;
CREATE TABLE `ps_map_data_halflife_natural` (
  `dataid` int(10) unsigned NOT NULL default '0',
  `alienkills` smallint(5) unsigned NOT NULL default '0',
  `marinekills` smallint(5) unsigned NOT NULL default '0',
  `joinedalien` smallint(5) unsigned NOT NULL default '0',
  `joinedmarine` smallint(5) unsigned NOT NULL default '0',
  `joinedspectator` smallint(5) unsigned NOT NULL default '0',
  `alienwon` smallint(5) unsigned NOT NULL default '0',
  `marinewon` smallint(5) unsigned NOT NULL default '0',
  `alienlost` smallint(5) unsigned NOT NULL default '0',
  `marinelost` smallint(5) unsigned NOT NULL default '0',
  `structuresbuilt` smallint(5) unsigned NOT NULL default '0',
  `structuresdestroyed` smallint(5) unsigned NOT NULL default '0',
  `structuresrecycled` smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_plr_data_halflife_natural`
--

DROP TABLE IF EXISTS `ps_plr_data_halflife_natural`;
CREATE TABLE `ps_plr_data_halflife_natural` (
  `dataid` int(10) unsigned NOT NULL default '0',
  `alienkills` smallint(5) unsigned NOT NULL default '0',
  `marinekills` smallint(5) unsigned NOT NULL default '0',
  `aliendeaths` smallint(5) unsigned NOT NULL default '0',
  `marinedeaths` smallint(5) unsigned NOT NULL default '0',
  `joinedalien` smallint(5) unsigned NOT NULL default '0',
  `joinedmarine` smallint(5) unsigned NOT NULL default '0',
  `joinedspectator` smallint(5) unsigned NOT NULL default '0',
  `alienwon` smallint(5) unsigned NOT NULL default '0',
  `marinewon` smallint(5) unsigned NOT NULL default '0',
  `alienlost` smallint(5) unsigned NOT NULL default '0',
  `marinelost` smallint(5) unsigned NOT NULL default '0',
  `votedown` smallint(5) unsigned NOT NULL default '0',
  `commander` smallint(5) unsigned NOT NULL,
  `commanderwon` smallint(5) unsigned NOT NULL default '0',
  `structuresbuilt` smallint(5) unsigned NOT NULL default '0',
  `structuresdestroyed` smallint(5) unsigned NOT NULL default '0',
  `structuresrecycled` smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_plr_maps_halflife_natural`
--

DROP TABLE IF EXISTS `ps_plr_maps_halflife_natural`;
CREATE TABLE `ps_plr_maps_halflife_natural` (
  `dataid` int(10) unsigned NOT NULL default '0',
  `alienkills` smallint(5) unsigned NOT NULL default '0',
  `marinekills` smallint(5) unsigned NOT NULL default '0',
  `aliendeaths` smallint(5) unsigned NOT NULL default '0',
  `marinedeaths` smallint(5) unsigned NOT NULL default '0',
  `joinedalien` smallint(5) unsigned NOT NULL default '0',
  `joinedmarine` smallint(5) unsigned NOT NULL default '0',
  `joinedspectator` smallint(5) unsigned NOT NULL default '0',
  `alienwon` smallint(5) unsigned NOT NULL default '0',
  `marinewon` smallint(5) unsigned NOT NULL default '0',
  `alienlost` smallint(5) unsigned NOT NULL default '0',
  `marinelost` smallint(5) unsigned NOT NULL default '0',
  `votedown` smallint(5) unsigned NOT NULL default '0',
  `commander` smallint(5) unsigned NOT NULL,
  `commanderwon` smallint(5) unsigned NOT NULL default '0',
  `structuresbuilt` smallint(5) unsigned NOT NULL default '0',
  `structuresdestroyed` smallint(5) unsigned NOT NULL default '0',
  `structuresrecycled` smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_role`
--

DROP TABLE IF EXISTS `ps_role`;
CREATE TABLE `ps_role` (
  `roleid` smallint(5) unsigned NOT NULL default '0',
  `uniqueid` varchar(32) NOT NULL default '',
  `name` varchar(128) NOT NULL default '',
  `team` varchar(16) NOT NULL default '',
  PRIMARY KEY  (`roleid`),
  UNIQUE KEY `uniqueid` (`uniqueid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_role_data`
--

DROP TABLE IF EXISTS `ps_role_data`;
CREATE TABLE `ps_role_data` (
  `dataid` int(10) unsigned NOT NULL default '0',
  `roleid` smallint(5) unsigned NOT NULL default '0',
  `statdate` date NOT NULL default '0000-00-00',
  `deaths` smallint(5) unsigned NOT NULL default '0',
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
  `joined` smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`),
  UNIQUE KEY `roleid` (`roleid`,`statdate`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_plr_roles`
--

DROP TABLE IF EXISTS `ps_plr_roles`;
CREATE TABLE `ps_plr_roles` (
  `dataid` int(10) unsigned NOT NULL default '0',
  `plrid` int(10) unsigned NOT NULL default '0',
  `roleid` smallint(5) unsigned NOT NULL default '0',
  `statdate` date NOT NULL default '0000-00-00',
  `kills` smallint(5) unsigned NOT NULL default '0',
  `deaths` smallint(5) unsigned NOT NULL default '0',
  `headshotkills` smallint(5) unsigned NOT NULL default '0',
  `shots` int(10) unsigned NOT NULL default '0',
  `hits` int(10) unsigned NOT NULL default '0',
  `damage` int(10) unsigned NOT NULL default '0',
  `ffkills` smallint(5) unsigned NOT NULL default '0',
  `shot_head` smallint(5) unsigned NOT NULL default '0',
  `shot_chest` smallint(5) unsigned NOT NULL default '0',
  `shot_stomach` smallint(5) unsigned NOT NULL default '0',
  `shot_leftarm` smallint(5) unsigned NOT NULL default '0',
  `shot_rightarm` smallint(5) unsigned NOT NULL default '0',
  `shot_leftleg` smallint(5) unsigned NOT NULL default '0',
  `shot_rightleg` smallint(5) unsigned NOT NULL default '0',
  `joined` smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`),
  KEY `plrroles` (`plrid`,`roleid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2007-02-14 22:37:38
