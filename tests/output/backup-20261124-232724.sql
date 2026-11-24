/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.16-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: event_registration_db
-- ------------------------------------------------------
-- Server version	10.11.16-MariaDB

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
-- Current Database: `event_registration_db`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `event_registration_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `event_registration_db`;

--
-- Table structure for table `administrators`
--

DROP TABLE IF EXISTS `administrators`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `administrators` (
  `admin_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `uq_administrators_username` (`username`),
  CONSTRAINT `chk_administrators_username` CHECK (char_length(`username`) >= 3),
  CONSTRAINT `chk_administrators_hash` CHECK (char_length(`password_hash`) >= 60)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `administrators`
--

LOCK TABLES `administrators` WRITE;
/*!40000 ALTER TABLE `administrators` DISABLE KEYS */;
/*!40000 ALTER TABLE `administrators` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `events`
--

DROP TABLE IF EXISTS `events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `events` (
  `event_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `event_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` date NOT NULL,
  `event_time` time NOT NULL,
  `venue` varchar(150) NOT NULL,
  `capacity` smallint(5) unsigned NOT NULL DEFAULT 50,
  `status` enum('open','closed','cancelled','completed') NOT NULL DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`event_id`),
  KEY `idx_events_status_date` (`status`,`event_date`),
  KEY `idx_events_date` (`event_date`),
  CONSTRAINT `chk_events_capacity` CHECK (`capacity` between 1 and 5000),
  CONSTRAINT `chk_events_status` CHECK (`status` in ('open','closed','cancelled','completed')),
  CONSTRAINT `chk_events_name` CHECK (char_length(trim(`event_name`)) > 0)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `events`
--

LOCK TABLES `events` WRITE;
/*!40000 ALTER TABLE `events` DISABLE KEYS */;
INSERT INTO `events` VALUES
(1,'Web Development Workshop','Hands-on session on building responsive websites with HTML, CSS and Bootstrap 5. Bring your own laptop.','2026-12-10','09:00:00','Computer Laboratory 1, IT Building',40,'open','2026-11-24 14:49:37'),
(2,'PHP & MySQL Bootcamp','Two-hour bootcamp on building a CRUD application with PHP 8, PDO and prepared statements.','2026-12-15','13:00:00','Computer Laboratory 2, IT Building',30,'open','2026-11-24 14:49:37'),
(3,'Christmas Charity Fun Run 2026','5K fun run around the campus. Proceeds go to the community feeding program. Race kit included.','2026-12-20','05:30:00','University Oval',300,'open','2026-11-24 14:49:37'),
(4,'Tech Career Talk: Landing Your First IT Job','Industry speakers share tips on resumes, portfolios and technical interviews. Q&A after the talk.','2027-01-20','10:00:00','University Auditorium',250,'open','2026-11-24 14:49:37'),
(5,'Intro to Data Analytics with Excel','Learn pivot tables, charts and basic data cleaning. Certificates will be given to attendees.','2027-02-05','14:00:00','Room 305, Business Building',35,'open','2026-11-24 14:49:37'),
(6,'Leadership Summit for Student Officers','Full-day summit for student organization officers. Registration closed — venue is fully booked.','2026-12-05','08:00:00','Grand Ballroom, City Convention Center',120,'closed','2026-11-24 14:49:37'),
(7,'Cybersecurity Awareness Seminar','Basics of password security, phishing and keeping web applications safe.','2026-11-05','14:00:00','Room 210, Engineering Building',60,'completed','2026-11-24 14:49:37'),
(8,'Mobile App Design Hackathon','24-hour hackathon. Cancelled due to venue renovation; will be rescheduled next semester.','2026-12-12','08:00:00','Innovation Hub',80,'cancelled','2026-11-24 14:49:37');
/*!40000 ALTER TABLE `events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_attempts` (
  `attempt_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `username` varchar(50) NOT NULL,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`attempt_id`),
  KEY `idx_login_attempts_ip_time` (`ip_address`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `registrations`
--

DROP TABLE IF EXISTS `registrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `registrations` (
  `registration_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int(10) unsigned NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `registration_date` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
  `reference_code` varchar(20) NOT NULL,
  PRIMARY KEY (`registration_id`),
  UNIQUE KEY `uq_registrations_reference` (`reference_code`),
  UNIQUE KEY `uq_registrations_event_email` (`event_id`,`email`),
  KEY `idx_registrations_email` (`email`),
  KEY `idx_registrations_status` (`status`),
  CONSTRAINT `fk_registrations_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_registrations_status` CHECK (`status` in ('pending','confirmed','cancelled')),
  CONSTRAINT `chk_registrations_email` CHECK (`email` like '%_@_%._%'),
  CONSTRAINT `chk_registrations_name` CHECK (char_length(trim(`full_name`)) > 0),
  CONSTRAINT `chk_registrations_reference` CHECK (char_length(`reference_code`) >= 6)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `registrations`
--

LOCK TABLES `registrations` WRITE;
/*!40000 ALTER TABLE `registrations` DISABLE KEYS */;
INSERT INTO `registrations` VALUES
(1,1,'Juan Dela Cruz','juan.delacruz@example.com','09171234567','2026-11-18 10:15:00','confirmed','ERS-2026-A1B2C3'),
(2,1,'Maria Santos','maria.santos@example.com','09181234567','2026-11-19 14:02:00','pending','ERS-2026-D4E5F6'),
(3,2,'Juan Dela Cruz','juan.delacruz@example.com','09171234567','2026-11-20 09:30:00','confirmed','ERS-2026-G7H8J9'),
(4,3,'Angela Reyes','angela.reyes@example.com',NULL,'2026-11-21 16:45:00','pending','ERS-2026-K1L2M3'),
(5,4,'Mark Anthony Lim','mark.lim@example.com','09201234567','2026-11-22 11:20:00','cancelled','ERS-2026-N4P5Q6'),
(6,7,'Kristine Villanueva','kristine.v@example.com','09391234567','2026-10-28 08:10:00','confirmed','ERS-2026-R7S8T9');
/*!40000 ALTER TABLE `registrations` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-24 23:27:24
