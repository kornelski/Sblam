-- MySQL dump 10.13  Distrib 5.1.24-rc, for pc-linux-gnu (i686)
--
-- ------------------------------------------------------
-- Server version	5.1.24-rc-community

SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT ;
SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS ;
SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION ;
SET NAMES utf8 ;
SET @OLD_TIME_ZONE=@@TIME_ZONE ;
SET TIME_ZONE='+00:00' ;
SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 ;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 ;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' ;
SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 ;

--
-- Table structure for table `account_messages`
--

SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `account_messages` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `account` mediumint(8) unsigned NOT NULL,
  `type` varchar(16) DEFAULT NULL,
  `message_html` mediumtext NOT NULL,
  `read` enum('N','Y') NOT NULL DEFAULT 'N',
  `sent` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account` (`account`,`type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `accounts`
--

SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `accounts` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `apikey` char(18) NOT NULL,
  `created` datetime NOT NULL,
  `email` varchar(128) DEFAULT NULL,
  `apikeyhash` binary(16) NOT NULL,
  `script` enum('N','Y') NOT NULL,
  `lasthost` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `apikey` (`apikey`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;
SET @saved_cs_client      = @@character_set_client  ;
SET @saved_cs_results     = @@character_set_results  ;
SET @saved_col_connection = @@collation_connection  ;
SET character_set_client  = utf8  ;
SET character_set_results = utf8  ;
SET collation_connection  = utf8_general_ci  ;
SET @saved_sql_mode       = @@sql_mode  ;
SET sql_mode              = ''  ;
DELIMITER ;;
CREATE trigger hashapikey_i before insert on accounts for each row set NEW.apikeyhash = unhex(md5(concat("^&$@$2\n", COALESCE(NEW.apikey), "@@"))) ;;
DELIMITER ;
SET sql_mode              = @saved_sql_mode  ;
SET character_set_client  = @saved_cs_client  ;
SET character_set_results = @saved_cs_results  ;
SET collation_connection  = @saved_col_connection  ;

--
-- Table structure for table `bayestotal`
--

SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `bayestotal` (
  `totalspam` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `totalham` mediumint(8) unsigned NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `bayestranslate`
--

SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `bayestranslate` (
  `wordh` binary(16) NOT NULL,
  `word` varchar(64) NOT NULL,
  PRIMARY KEY (`wordh`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `bayeswordsh`
--

SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `bayeswordsh` (
  `wordh` binary(16) NOT NULL,
  `ham` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `spam` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `added` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `flags` tinyint(3) unsigned NOT NULL,
  PRIMARY KEY (`wordh`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

SET character_set_client = @saved_cs_client;
SET @saved_cs_client      = @@character_set_client  ;
SET @saved_cs_results     = @@character_set_results  ;
SET @saved_col_connection = @@collation_connection  ;
SET character_set_client  = utf8  ;
SET character_set_results = utf8  ;
SET collation_connection  = utf8_general_ci  ;
SET @saved_sql_mode       = @@sql_mode  ;
SET sql_mode              = ''  ;
DELIMITER ;;
CREATE TRIGGER bayeswordsh_i AFTER INSERT ON `bayeswordsh` FOR EACH ROW REPLACE bayeswordsh_s (wordh,spam,ham,added) VALUES(NEW.wordh,NEW.spam,NEW.ham,NEW.added) ;;
DELIMITER ;
SET sql_mode              = @saved_sql_mode  ;
SET character_set_client  = @saved_cs_client  ;
SET character_set_results = @saved_cs_results  ;
SET collation_connection  = @saved_col_connection  ;
SET @saved_cs_client      = @@character_set_client  ;
SET @saved_cs_results     = @@character_set_results  ;
SET @saved_col_connection = @@collation_connection  ;
SET character_set_client  = utf8  ;
SET character_set_results = utf8  ;
SET collation_connection  = utf8_general_ci  ;
SET @saved_sql_mode       = @@sql_mode  ;
SET sql_mode              = ''  ;
DELIMITER ;;
CREATE TRIGGER bayeswordsh_u AFTER UPDATE ON `bayeswordsh` FOR EACH ROW REPLACE bayeswordsh_s (wordh,spam,ham,added) VALUES(NEW.wordh,NEW.spam,NEW.ham,NEW.added) ;;
DELIMITER ;
SET sql_mode              = @saved_sql_mode  ;
SET character_set_client  = @saved_cs_client  ;
SET character_set_results = @saved_cs_results  ;
SET collation_connection  = @saved_col_connection  ;

--
-- Table structure for table `bayeswordsh_s`
--

SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `bayeswordsh_s` (
  `wordh` binary(16) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0',
  `ham` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `spam` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `added` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`wordh`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `dnscache`
--

SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `dnscache` (
  `host` varchar(255) NOT NULL,
  `ip` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`host`,`ip`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `dnsrevcache`
--

SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `dnsrevcache` (
  `ip` int(10) unsigned NOT NULL DEFAULT '0',
  `host` varchar(255) NOT NULL,
  PRIMARY KEY (`ip`),
  KEY `host` (`host`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `dupes`
--

SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `dupes` (
  `checksum` binary(16) NOT NULL,
  `count` mediumint(8) unsigned NOT NULL DEFAULT '1',
  `ip` int(10) unsigned NOT NULL,
  `expires` int(10) unsigned NOT NULL,
  PRIMARY KEY (`checksum`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `linkstotal`
--

SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `linkstotal` (
  `totalspam` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `totalham` mediumint(8) unsigned NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `linkstranslate`
--

SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `linkstranslate` (
  `wordh` binary(16) NOT NULL,
  `word` varchar(64) NOT NULL,
  PRIMARY KEY (`wordh`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `linkswordsh`
--

SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `linkswordsh` (
  `wordh` binary(16) NOT NULL,
  `ham` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `spam` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `added` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `flags` tinyint(3) unsigned NOT NULL,
  PRIMARY KEY (`wordh`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `linkswordsh_s`
--

SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `linkswordsh_s` (
  `wordh` binary(16) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0',
  `ham` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `spam` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `added` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`wordh`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `plonker`
--

SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `plonker` (
  `ip` int(10) unsigned NOT NULL,
  `spampoints` mediumint(8) unsigned NOT NULL,
  `added` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `flags` set('dul','nodul','wild','nowild') NOT NULL,
  PRIMARY KEY (`ip`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `posts_data`
--

SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `posts_data` (
  `id` int(10) unsigned NOT NULL,
  `content` text NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `headers` text,
  `cookies` tinyint(4) DEFAULT NULL,
  `session` tinyint(4) DEFAULT NULL,
  `host` varchar(255) DEFAULT NULL,
  `hostip` int(10) unsigned NOT NULL DEFAULT '0',
  `path` varchar(255) DEFAULT NULL,
  `post` text,
  `chcookie` varchar(255) DEFAULT NULL,
  `spamreason` mediumtext,
  `profiling` mediumtext,
  UNIQUE KEY `id` (`id`),
  KEY `host` (`host`),
  CONSTRAINT `posts_data_ibfk_1` FOREIGN KEY (`id`) REFERENCES `posts_meta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `posts_meta`
--

SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `posts_meta` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account` mediumint(8) unsigned DEFAULT NULL,
  `ip` int(10) unsigned NOT NULL DEFAULT '0',
  `timestamp` int(11) DEFAULT NULL,
  `spambayes` smallint(6) DEFAULT NULL,
  `spamscore` mediumint(9) DEFAULT NULL,
  `spamcert` mediumint(9) DEFAULT NULL,
  `worktime` int(10) unsigned DEFAULT NULL,
  `added` tinyint(3) unsigned DEFAULT NULL,
  `manualspam` tinyint(3) unsigned DEFAULT NULL,
  `serverid` varchar(64) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `manualspam` (`manualspam`,`spamscore`),
  KEY `spamscore` (`spamscore`,`spamcert`),
  KEY `account` (`account`,`spamscore`),
  KEY `ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `postsarchive`
--

SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `postsarchive` (
  `id` mediumint(9) NOT NULL,
  `spambayes` smallint(6) DEFAULT NULL,
  `spamscore` mediumint(9) DEFAULT NULL,
  `spamcert` mediumint(9) DEFAULT NULL,
  `spamreason` mediumtext,
  `manualspam` tinyint(4) DEFAULT NULL,
  `content` text NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `ip` int(10) unsigned NOT NULL,
  `timestamp` int(11) DEFAULT NULL,
  `headers` text,
  `cookies` tinyint(4) DEFAULT NULL,
  `session` tinyint(4) DEFAULT NULL,
  `host` varchar(255) DEFAULT NULL,
  `hostip` int(10) unsigned NOT NULL,
  `path` varchar(255) DEFAULT NULL,
  `submitname` varchar(255) DEFAULT NULL,
  `added` tinyint(1) unsigned DEFAULT NULL,
  `checksum` varchar(56) DEFAULT NULL,
  `surbl` tinyint(3) unsigned DEFAULT NULL,
  `post` text,
  `foolmeonce` mediumint(9) DEFAULT NULL,
  `chcookie` varchar(255) DEFAULT NULL,
  `worktime` int(10) unsigned DEFAULT NULL,
  `account` mediumint(8) unsigned DEFAULT NULL,
  `profiling` mediumtext
) ENGINE=ARCHIVE DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `trustedproxies`
--

SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `trustedproxies` (
  `host` varchar(255) NOT NULL,
  PRIMARY KEY (`host`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;
SET TIME_ZONE=@OLD_TIME_ZONE ;

SET SQL_MODE=@OLD_SQL_MODE ;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS ;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS ;
SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT ;
SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS ;
SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION ;
SET SQL_NOTES=@OLD_SQL_NOTES ;

