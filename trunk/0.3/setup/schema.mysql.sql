--
-- SVN INFORMATION:::
-- ---------------
--	SVN Signature::::::: $Id$
--	Last Author::::::::: $Author$
--	Current Revision:::: $Revision$
--	Repository Location: $HeadURL$
--	Last Updated:::::::: $Date$
--

-- phpMyAdmin SQL Dump
-- version 2.10.3
-- http://www.phpmyadmin.net
-- 
-- Generation Time: Aug 10, 2009 at 11:01 AM
-- Server version: 5.0.22
-- PHP Version: 5.1.6

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";


-- --------------------------------------------------------

-- 
-- Table structure for table `cswdbl_category_table`
-- 

CREATE TABLE `cswdbl_category_table` (
  `category_id` int(11) NOT NULL auto_increment,
  `category_name` text NOT NULL,
  PRIMARY KEY  (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

-- 
-- Table structure for table `cswdbl_class_table`
-- 

CREATE TABLE `cswdbl_class_table` (
  `class_id` int(11) NOT NULL auto_increment,
  `class_name` text NOT NULL,
  PRIMARY KEY  (`class_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

-- 
-- Table structure for table `cswdbl_event_table`
-- 

CREATE TABLE `cswdbl_event_table` (
  `event_id` int(11) NOT NULL auto_increment,
  `class_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `description` text NOT NULL,
  PRIMARY KEY  (`event_id`),
  KEY `cswdbl_event_table_class_id_fkey` (`class_id`),
  KEY `cswdbl_event_table_category_id_fkey` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

-- 
-- Table structure for table `cswdbl_log_table`
-- 

CREATE TABLE `cswdbl_log_table` (
  `log_id` int(11) NOT NULL auto_increment,
  `creation` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `event_id` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `affected_uid` int(11) NOT NULL,
  `details` text NOT NULL,
  PRIMARY KEY  (`log_id`),
  KEY `cswdbl_log_table_event_id_fkey` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

-- 
-- Constraints for dumped tables
-- 

-- 
-- Constraints for table `cswdbl_event_table`
-- 
ALTER TABLE `cswdbl_event_table`
  ADD CONSTRAINT `cswdbl_event_table_class_id_fkey` FOREIGN KEY (`class_id`) REFERENCES `cswdbl_class_table` (`class_id`),
  ADD CONSTRAINT `cswdbl_event_table_category_id_fkey` FOREIGN KEY (`category_id`) REFERENCES `cswdbl_category_table` (`category_id`);

-- 
-- Constraints for table `cswdbl_log_table`
-- 
ALTER TABLE `cswdbl_log_table`
  ADD CONSTRAINT `cswdbl_log_table_event_id_fkey` FOREIGN KEY (`event_id`) REFERENCES `cswdbl_event_table` (`event_id`);
  
  
-- This table create statement MUST work in PostgreSQL v8.2.x+ AND MySQL v5.0.x+: 
-- otherwise separate schema files have to be created and the code will have to 
-- do extra checking...
-- 
CREATE TABLE cswal_version_table (
	version_id int NOT NULL PRIMARY KEY,
	project_name varchar(30) NOT NULL UNIQUE,
	version_string varchar(50) NOT NULL
);

CREATE TABLE cswal_auth_token_table (
	auth_token_id serial NOT NULL PRIMARY KEY,
	uid integer NOT NULL DEFAULT 0,
	checksum text NOT NULL,
	token text NOT NULL,
	max_uses integer DEFAULT NULL,
	total_uses integer NOT NULL DEFAULT 0,
	creation timestamp NOT NULL DEFAULT NOW(),
	expiration timestamp NOT NULL
);