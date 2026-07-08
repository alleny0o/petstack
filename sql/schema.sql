/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.18-MariaDB, for Linux (x86_64)
--
-- Host: bute    Database: petorders
-- ------------------------------------------------------
-- Server version	10.11.18-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `admins` (
  `user_id` int(11) NOT NULL,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_admins_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admins`
--

LOCK TABLES `admins` WRITE;
/*!40000 ALTER TABLE `admins` DISABLE KEYS */;
/*!40000 ALTER TABLE `admins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `category_name` varchar(30) NOT NULL,
  PRIMARY KEY (`category_name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `compounds`
--

DROP TABLE IF EXISTS `compounds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `compounds` (
  `compound_id` int(11) NOT NULL AUTO_INCREMENT,
  `isotope_name` varchar(30) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(30) NOT NULL,
  `standard_cost` decimal(10,2) NOT NULL,
  `min_lead_time_hours` decimal(6,1) NOT NULL DEFAULT 0.0,
  `order_type` char(1) DEFAULT NULL,
  `ACTIVE` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`compound_id`),
  KEY `fk_compound_isotope` (`isotope_name`),
  KEY `fk_compound_category` (`category`),
  CONSTRAINT `fk_compound_category` FOREIGN KEY (`category`) REFERENCES `categories` (`category_name`),
  CONSTRAINT `fk_compound_isotope` FOREIGN KEY (`isotope_name`) REFERENCES `isotopes` (`isotope_name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `compounds`
--

LOCK TABLES `compounds` WRITE;
/*!40000 ALTER TABLE `compounds` DISABLE KEYS */;
/*!40000 ALTER TABLE `compounds` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `customers` (
  `user_id` int(11) NOT NULL,
  `institute` varchar(255) DEFAULT NULL,
  `lab_id` int(11) DEFAULT NULL,
  `supervisor_id` int(11) DEFAULT NULL,
  `registration_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `fk_customers_pi` (`supervisor_id`),
  KEY `fk_customers_lab` (`lab_id`),
  KEY `fk_customers_institute` (`institute`),
  CONSTRAINT `fk_customers_institute` FOREIGN KEY (`institute`) REFERENCES `institutes` (`name`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_customers_lab` FOREIGN KEY (`lab_id`) REFERENCES `labs` (`lab_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_customers_pi` FOREIGN KEY (`supervisor_id`) REFERENCES `pis` (`pi_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_customers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `institutes`
--

DROP TABLE IF EXISTS `institutes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `institutes` (
  `name` varchar(255) NOT NULL,
  `shorthand_name` varchar(10) DEFAULT NULL,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `institutes`
--

LOCK TABLES `institutes` WRITE;
/*!40000 ALTER TABLE `institutes` DISABLE KEYS */;
/*!40000 ALTER TABLE `institutes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `isotopes`
--

DROP TABLE IF EXISTS `isotopes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `isotopes` (
  `isotope_name` varchar(30) NOT NULL,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`isotope_name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `isotopes`
--

LOCK TABLES `isotopes` WRITE;
/*!40000 ALTER TABLE `isotopes` DISABLE KEYS */;
/*!40000 ALTER TABLE `isotopes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lab_pis`
--

DROP TABLE IF EXISTS `lab_pis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lab_pis` (
  `lab_id` int(11) NOT NULL,
  `pi_id` int(11) NOT NULL,
  KEY `fk_lab` (`lab_id`),
  KEY `fk_pi` (`pi_id`),
  CONSTRAINT `fk_lab` FOREIGN KEY (`lab_id`) REFERENCES `labs` (`lab_id`),
  CONSTRAINT `fk_pi` FOREIGN KEY (`pi_id`) REFERENCES `pis` (`pi_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lab_pis`
--

LOCK TABLES `lab_pis` WRITE;
/*!40000 ALTER TABLE `lab_pis` DISABLE KEYS */;
/*!40000 ALTER TABLE `lab_pis` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `labs`
--

DROP TABLE IF EXISTS `labs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `labs` (
  `lab_id` int(11) NOT NULL AUTO_INCREMENT,
  `lab_name` varchar(50) NOT NULL,
  `institute` varchar(255) DEFAULT NULL,
  `building` varchar(50) DEFAULT NULL,
  `room` varchar(20) DEFAULT NULL,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`lab_id`),
  KEY `fk_institute_lab` (`institute`),
  CONSTRAINT `fk_institute_lab` FOREIGN KEY (`institute`) REFERENCES `institutes` (`name`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `labs`
--

LOCK TABLES `labs` WRITE;
/*!40000 ALTER TABLE `labs` DISABLE KEYS */;
/*!40000 ALTER TABLE `labs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_audit_log`
--

DROP TABLE IF EXISTS `order_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_audit_log` (
  `log_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `changed_by` int(11) NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `fk_log_order` (`order_id`),
  KEY `fk_log_changed_by` (`changed_by`),
  CONSTRAINT `fk_log_changed_by` FOREIGN KEY (`changed_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_log_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_audit_log`
--

LOCK TABLES `order_audit_log` WRITE;
/*!40000 ALTER TABLE `order_audit_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `order_audit_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_dose`
--

DROP TABLE IF EXISTS `order_dose`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_dose` (
  `order_id` int(11) NOT NULL,
  `dose_number` int(11) NOT NULL DEFAULT 1,
  `delivery_time` timestamp NOT NULL,
  KEY `fk_dose_order_id` (`order_id`),
  CONSTRAINT `fk_dose_order_id` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_dose`
--

LOCK TABLES `order_dose` WRITE;
/*!40000 ALTER TABLE `order_dose` DISABLE KEYS */;
/*!40000 ALTER TABLE `order_dose` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_internal_notes`
--

DROP TABLE IF EXISTS `order_internal_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_internal_notes` (
  `note_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT NULL,
  `author_id` int(11) DEFAULT NULL,
  `body` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`note_id`),
  KEY `fk_note_order` (`order_id`),
  KEY `fk_note_author` (`author_id`),
  CONSTRAINT `fk_note_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_note_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_internal_notes`
--

LOCK TABLES `order_internal_notes` WRITE;
/*!40000 ALTER TABLE `order_internal_notes` DISABLE KEYS */;
/*!40000 ALTER TABLE `order_internal_notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `compound_id` int(11) NOT NULL,
  `isotope` varchar(30) NOT NULL,
  `activity_mci` decimal(10,1) DEFAULT NULL,
  `status` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL,
  `delivery_option` varchar(20) NOT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cost_snapshot` decimal(10,2) NOT NULL,
  `last_modified_at` timestamp NULL DEFAULT current_timestamp(),
  `last_modified_by` int(11) NOT NULL,
  `special_instructions` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`order_id`),
  KEY `fk_customer` (`customer_id`),
  KEY `fk_compound_id` (`compound_id`),
  KEY `fk_isotope_name` (`isotope`),
  KEY `fk_processed_by` (`processed_by`),
  KEY `fk_modified_by` (`last_modified_by`),
  CONSTRAINT `fk_compound_id` FOREIGN KEY (`compound_id`) REFERENCES `compounds` (`compound_id`),
  CONSTRAINT `fk_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`user_id`),
  CONSTRAINT `fk_isotope_name` FOREIGN KEY (`isotope`) REFERENCES `isotopes` (`isotope_name`),
  CONSTRAINT `fk_modified_by` FOREIGN KEY (`last_modified_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pis`
--

DROP TABLE IF EXISTS `pis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pis` (
  `pi_id` int(11) NOT NULL AUTO_INCREMENT,
  `pi_name` varchar(50) NOT NULL,
  `email` varchar(254) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`pi_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pis`
--

LOCK TABLES `pis` WRITE;
/*!40000 ALTER TABLE `pis` DISABLE KEYS */;
/*!40000 ALTER TABLE `pis` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff`
--

DROP TABLE IF EXISTS `staff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff` (
  `user_id` int(11) NOT NULL,
  `category` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  KEY `fk_staffs_category` (`category`),
  CONSTRAINT `fk_staffs_category` FOREIGN KEY (`category`) REFERENCES `categories` (`category_name`),
  CONSTRAINT `fk_staffs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff`
--

LOCK TABLES `staff` WRITE;
/*!40000 ALTER TABLE `staff` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `username` varchar(30) NOT NULL,
  `must_change_pass` int(11) NOT NULL DEFAULT 1,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  `failed_login_count` int(11) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(254) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-08 13:02:17
