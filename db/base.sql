-- MySQL dump 10.13  Distrib 5.7.9, for linux-glibc2.5 (x86_64)
--
-- Host: 127.0.0.1    Database: wallets
-- ------------------------------------------------------
-- Server version	5.6.30-0ubuntu0.14.04.1-log
--
-- This schema includes all migrations up to 00005-decimal-to-int.sql

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
-- Table structure for table `wallet`
--

DROP TABLE IF EXISTS `wallet`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wallet` (
  `walletid` int(11) NOT NULL AUTO_INCREMENT,
  `wallettypeid` varchar(20) COLLATE utf8_bin NOT NULL,
  `userid` varchar(50) DEFAULT NULL,
  `balance` BIGINT DEFAULT 0,
  `reserved` BIGINT DEFAULT 0,
  `available` BIGINT DEFAULT 0,
  `scale` BIGINT NOT NULL DEFAULT 2,
  `extra` text COLLATE utf8_bin,
  `minvalue` BIGINT NOT NULL DEFAULT 0,
  `last_uuid` binary(16) DEFAULT NULL,
  `entrydate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`walletid`),
  UNIQUE KEY `unique_userid_type` (`userid`,`wallettypeid`),
  KEY `fk_wallet_wallettype_idx` (`wallettypeid`),
  CONSTRAINT `fk_wallet_wallettype` FOREIGN KEY (`wallettypeid`) REFERENCES `wallettype` (`wallettypeid`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `wallettype`
--

DROP TABLE IF EXISTS `wallettype`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wallettype` (
  `wallettypeid` varchar(20) COLLATE utf8_bin NOT NULL,
  `name` varchar(45) COLLATE utf8_bin NOT NULL,
  PRIMARY KEY (`wallettypeid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `transaction`
--

DROP TABLE IF EXISTS `transaction`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transaction` (
  `transactionid` int(11) NOT NULL AUTO_INCREMENT,
  `walletid` int(11) NOT NULL,
  `wallettypeid` varchar(20) COLLATE utf8_bin NOT NULL,
  `typeid` enum('B','D','W','DB','WB','R') COLLATE utf8_bin NOT NULL COMMENT 'B: Balance - Inicia um novo valor desprezando os antigos\nD: Deposit: Adiciona um valor imediatamente ao banco\nW: Withdrawal\nR: Reject\nWD: Withdrawal (blocked, reserved)\n',
  `amount` BIGINT NOT NULL,
  `scale` BIGINT DEFAULT 2,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `balance` BIGINT DEFAULT NULL,
  `reserved` BIGINT DEFAULT NULL,
  `available` BIGINT DEFAULT NULL,
  `code` CHAR(10) DEFAULT NULL,
  `description` varchar(255) COLLATE utf8_bin DEFAULT NULL,
  `transactionparentid` int(11) DEFAULT NULL,
  `referenceid` varchar(100) COLLATE utf8_bin DEFAULT NULL,
  `referencesource` VARCHAR(50) DEFAULT NULL,
  `uuid` binary(16) DEFAULT NULL,
  `previousuuid` binary(16) DEFAULT NULL,
  PRIMARY KEY (`transactionid`),
  UNIQUE KEY `idx_transaction_uuid` (`uuid`),
  KEY `idx_transaction_previous_uuid` (`previousuuid`),
  KEY `fk_transaction_wallet1_idx` (`walletid`),
  KEY `fk_transaction_transaction1_idx` (`transactionparentid`),
  KEY `idx_transaction_typeid_date` (`typeid`,`date`) USING BTREE COMMENT 'Índice para filtros com tipo e ordenação por data decrescente',
  KEY `fk_transaction_wallettype_idx` (`wallettypeid`),
  KEY `fk_transaction_referenceid_idx` (`referenceid`),
  KEY `fk_transaction_referencesource_idx` (`referencesource`),
  CONSTRAINT `fk_transaction_wallettype` FOREIGN KEY (`wallettypeid`) REFERENCES `wallettype` (`wallettypeid`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_transaction_wallet1` FOREIGN KEY (`walletid`) REFERENCES `wallet` (`walletid`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `fk_transaction_transaction1` FOREIGN KEY (`transactionparentid`) REFERENCES `transaction` (`transactionid`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping events for database 'wallets'
--

--
-- Dumping routines for database 'wallets'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Schema updated to include all migrations through 00005-decimal-to-int
-- Financial columns now use BIGINT to store values in smallest unit (cents)
-- Default scale is 2
-- Column naming: balance (total), reserved (blocked), available (balance - reserved)
