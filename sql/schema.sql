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
-- Table structure for table `compound_isotopes`
--

DROP TABLE IF EXISTS `compound_isotopes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `compound_isotopes` (
  `isotope_name` varchar(30) NOT NULL,
  `compound_id` int(11) NOT NULL,
  `category` varchar(30) NOT NULL,
  PRIMARY KEY (`isotope_name`,`compound_id`),
  KEY `fk_compounds` (`compound_id`),
  KEY `fk_compound_isotope_category` (`category`),
  CONSTRAINT `fk_compound_isotope_category` FOREIGN KEY (`category`) REFERENCES `categories` (`category_name`),
  CONSTRAINT `fk_compounds` FOREIGN KEY (`compound_id`) REFERENCES `compounds` (`compound_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_isotopes` FOREIGN KEY (`isotope_name`) REFERENCES `isotopes` (`isotope_name`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `compounds`
--

DROP TABLE IF EXISTS `compounds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `compounds` (
  `compound_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `standard_cost` decimal(10,0) NOT NULL,
  `min_lead_time_hours` decimal(10,0) NOT NULL DEFAULT 0,
  `order_type` char(1) DEFAULT NULL,
  `active` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`compound_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `customers` (
  `user_id` int(11) NOT NULL,
  `institute` varchar(255) DEFAULT NULL,
  `supervisor_id` int(11) DEFAULT NULL,
  `approved_by` int(11) NOT NULL,
  `approved_at` datetime NOT NULL,
  `lab_id` int(11) DEFAULT NULL,
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
-- Table structure for table `cyclotron_deliveries`
--

DROP TABLE IF EXISTS `cyclotron_deliveries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cyclotron_deliveries` (
  `order_id` int(11) NOT NULL,
  `mode` varchar(20) DEFAULT NULL,
  `bean_current` int(11) NOT NULL,
  `bombardment_minutes` int(11) NOT NULL,
  `eob_activity_mci` decimal(10,0) NOT NULL,
  `eob_date_time` timestamp NOT NULL,
  `destination` varchar(50) NOT NULL,
  PRIMARY KEY (`order_id`),
  CONSTRAINT `fk_delivery_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `deliveries`
--

DROP TABLE IF EXISTS `deliveries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `deliveries` (
  `compound_id` int(11) DEFAULT NULL,
  `isotope_name` varchar(30) DEFAULT NULL,
  `delivery_option` varchar(20) DEFAULT NULL,
  KEY `fk_compound_isotope` (`isotope_name`,`compound_id`),
  KEY `fk_delivery_option` (`delivery_option`),
  CONSTRAINT `fk_compound_isotope` FOREIGN KEY (`isotope_name`, `compound_id`) REFERENCES `compound_isotopes` (`isotope_name`, `compound_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_delivery_option` FOREIGN KEY (`delivery_option`) REFERENCES `delivery_options` (`name`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `delivery_options`
--

DROP TABLE IF EXISTS `delivery_options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_options` (
  `name` varchar(20) NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `institutes`
--

DROP TABLE IF EXISTS `institutes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `institutes` (
  `name` varchar(255) NOT NULL,
  `shorthand_name` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `isotopes`
--

DROP TABLE IF EXISTS `isotopes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `isotopes` (
  `isotope_name` varchar(30) NOT NULL,
  `active` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`isotope_name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
-- Table structure for table `labs`
--

DROP TABLE IF EXISTS `labs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `labs` (
  `lab_id` int(11) NOT NULL AUTO_INCREMENT,
  `lab_name` varchar(50) DEFAULT NULL,
  `building` varchar(50) DEFAULT NULL,
  `room` varchar(20) DEFAULT NULL,
  `active` int(11) NOT NULL DEFAULT 1,
  `institute` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`lab_id`),
  KEY `fk_institute_lab` (`institute`),
  CONSTRAINT `fk_institute_lab` FOREIGN KEY (`institute`) REFERENCES `institutes` (`name`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `messages` (
  `msg_id` int(11) NOT NULL AUTO_INCREMENT,
  `sent_by` int(11) DEFAULT NULL,
  `sent_to` int(11) DEFAULT NULL,
  `msg` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`msg_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `order_notes`
--

DROP TABLE IF EXISTS `order_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_notes` (
  `note_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT NULL,
  `author_id` int(11) DEFAULT NULL,
  `text` varchar(500) DEFAULT NULL,
  `time` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`note_id`),
  KEY `fk_note_order` (`order_id`),
  KEY `fk_note_author` (`author_id`),
  CONSTRAINT `fk_note_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_note_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
  `status` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL,
  `delivery_option` varchar(20) NOT NULL,
  `processed_by` int(11) NOT NULL,
  `processed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cost_snapshot` decimal(10,0) NOT NULL,
  `last_modified_at` timestamp NULL DEFAULT current_timestamp(),
  `last_modified_by` int(11) NOT NULL,
  `notes` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`order_id`),
  KEY `fk_customer` (`customer_id`),
  KEY `fk_compound_id` (`compound_id`),
  KEY `fk_isotope_name` (`isotope`),
  KEY `fk_processed_by` (`processed_by`),
  KEY `fk_modified_by` (`last_modified_by`),
  CONSTRAINT `fk_compound_id` FOREIGN KEY (`compound_id`) REFERENCES `compounds` (`compound_id`),
  CONSTRAINT `fk_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`user_id`),
  CONSTRAINT `fk_isotope_name` FOREIGN KEY (`isotope`) REFERENCES `isotopes` (`isotope_name`),
  CONSTRAINT `fk_modified_by` FOREIGN KEY (`last_modified_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
  `active` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`pi_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `radiotracer_doses`
--

DROP TABLE IF EXISTS `radiotracer_doses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `radiotracer_doses` (
  `order_id` int(11) NOT NULL,
  `unit_amt` decimal(10,0) NOT NULL,
  `activity_mci` decimal(10,0) NOT NULL DEFAULT 0,
  `requested_datetime` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`order_id`),
  CONSTRAINT `fk_parent_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(30) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `must_change_pass` int(11) NOT NULL DEFAULT 1,
  `active` int(11) NOT NULL DEFAULT 1,
  `failed_login_count` int(11) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(254) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-06  9:47:22
