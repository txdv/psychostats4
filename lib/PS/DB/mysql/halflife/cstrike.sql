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
-- Table structure for table `ps_map_data_halflife_cstrike`
--

DROP TABLE IF EXISTS `ps_map_data_halflife_cstrike`;
CREATE TABLE `ps_map_data_halflife_cstrike` (
  `dataid` int(10) unsigned NOT NULL default '0',
  `ctkills` smallint(5) unsigned NOT NULL default '0',
  `terroristkills` smallint(5) unsigned NOT NULL default '0',
  `joinedct` smallint(5) unsigned NOT NULL default '0',
  `joinedterrorist` smallint(5) unsigned NOT NULL default '0',
  `joinedspectator` smallint(5) unsigned NOT NULL default '0',
  `bombdefuseattempts` smallint(5) unsigned NOT NULL default '0',
  `bombdefused` smallint(5) unsigned NOT NULL default '0',
  `bombexploded` smallint(5) unsigned NOT NULL default '0',
  `bombplanted` smallint(5) unsigned NOT NULL default '0',
  `bombrunner` smallint(5) unsigned NOT NULL default '0',
  `killedhostages` smallint(5) unsigned NOT NULL default '0',
  `rescuedhostages` smallint(5) unsigned NOT NULL default '0',
  `touchedhostages` smallint(5) unsigned NOT NULL default '0',
  `vipescaped` smallint(5) unsigned NOT NULL default '0',
  `vipkilled` smallint(5) unsigned NOT NULL default '0',
  `ctwon` smallint(5) unsigned NOT NULL default '0',
  `ctlost` smallint(5) unsigned NOT NULL default '0',
  `terroristwon` smallint(5) unsigned NOT NULL default '0',
  `terroristlost` smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_plr_data_halflife_cstrike`
--

DROP TABLE IF EXISTS `ps_plr_data_halflife_cstrike`;
CREATE TABLE `ps_plr_data_halflife_cstrike` (
  `dataid` int(10) unsigned NOT NULL default '0',
  `ctkills` smallint(5) unsigned NOT NULL default '0',
  `terroristkills` smallint(5) unsigned NOT NULL default '0',
  `ctdeaths` smallint(5) unsigned NOT NULL default '0',
  `terroristdeaths` smallint(5) unsigned NOT NULL default '0',
  `joinedct` smallint(5) unsigned NOT NULL default '0',
  `joinedterrorist` smallint(5) unsigned NOT NULL default '0',
  `joinedspectator` smallint(5) unsigned NOT NULL default '0',
  `bombdefuseattempts` smallint(5) unsigned NOT NULL default '0',
  `bombdefused` smallint(5) unsigned NOT NULL default '0',
  `bombexploded` smallint(5) unsigned NOT NULL default '0',
  `bombplanted` smallint(5) unsigned NOT NULL default '0',
  `bombspawned` smallint(5) unsigned NOT NULL default '0',
  `bombrunner` smallint(5) unsigned NOT NULL default '0',
  `killedhostages` smallint(5) unsigned NOT NULL default '0',
  `rescuedhostages` smallint(5) unsigned NOT NULL default '0',
  `touchedhostages` smallint(5) unsigned NOT NULL default '0',
  `vip` smallint(5) unsigned NOT NULL default '0',
  `vipescaped` smallint(5) unsigned NOT NULL default '0',
  `vipkilled` smallint(5) unsigned NOT NULL default '0',
  `ctwon` smallint(5) unsigned NOT NULL default '0',
  `ctlost` smallint(5) unsigned NOT NULL default '0',
  `terroristwon` smallint(5) unsigned NOT NULL default '0',
  `terroristlost` smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table structure for table `ps_plr_maps_halflife_cstrike`
--

DROP TABLE IF EXISTS `ps_plr_maps_halflife_cstrike`;
CREATE TABLE `ps_plr_maps_halflife_cstrike` (
  `dataid` int(10) unsigned NOT NULL default '0',
  `ctkills` smallint(5) unsigned NOT NULL default '0',
  `terroristkills` smallint(5) unsigned NOT NULL default '0',
  `ctdeaths` smallint(5) unsigned NOT NULL default '0',
  `terroristdeaths` smallint(5) unsigned NOT NULL default '0',
  `joinedct` smallint(5) unsigned NOT NULL default '0',
  `joinedterrorist` smallint(5) unsigned NOT NULL default '0',
  `joinedspectator` smallint(5) unsigned NOT NULL default '0',
  `bombdefuseattempts` smallint(5) unsigned NOT NULL default '0',
  `bombdefused` smallint(5) unsigned NOT NULL default '0',
  `bombexploded` smallint(5) unsigned NOT NULL default '0',
  `bombplanted` smallint(5) unsigned NOT NULL default '0',
  `bombspawned` smallint(5) unsigned NOT NULL default '0',
  `bombrunner` smallint(5) unsigned NOT NULL default '0',
  `killedhostages` smallint(5) unsigned NOT NULL default '0',
  `rescuedhostages` smallint(5) unsigned NOT NULL default '0',
  `touchedhostages` smallint(5) unsigned NOT NULL default '0',
  `vip` smallint(5) unsigned NOT NULL default '0',
  `vipescaped` smallint(5) unsigned NOT NULL default '0',
  `vipkilled` smallint(5) unsigned NOT NULL default '0',
  `ctwon` smallint(5) unsigned NOT NULL default '0',
  `ctlost` smallint(5) unsigned NOT NULL default '0',
  `terroristwon` smallint(5) unsigned NOT NULL default '0',
  `terroristlost` smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY  (`dataid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2007-02-10 20:07:50
