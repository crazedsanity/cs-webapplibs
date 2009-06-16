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
-- Generation Time: Jun 16, 2009 at 05:44 PM
-- Server version: 5.0.22
-- PHP Version: 5.1.6

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

-- 
-- Database: `resourcetool`
-- 

-- --------------------------------------------------------

-- 
-- Table structure for table `log_category_table`
-- 
-- Creation: Jun 16, 2009 at 05:09 PM
-- 

CREATE TABLE `log_category_table` (
  `log_category_id` int(11) NOT NULL auto_increment,
  `name` text NOT NULL,
  PRIMARY KEY  (`log_category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

-- 
-- Table structure for table `log_class_table`
-- 
-- Creation: Jun 16, 2009 at 05:09 PM
-- 

CREATE TABLE `log_class_table` (
  `log_class_id` int(11) NOT NULL auto_increment,
  `name` text NOT NULL,
  PRIMARY KEY  (`log_class_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

-- 
-- Table structure for table `log_event_table`
-- 
-- Creation: Jun 16, 2009 at 05:12 PM
-- 

CREATE TABLE `log_event_table` (
  `log_event_id` int(11) NOT NULL auto_increment,
  `log_class_id` int(11) NOT NULL,
  `log_category_id` int(11) NOT NULL,
  `description` text NOT NULL,
  PRIMARY KEY  (`log_event_id`),
  KEY `log_event_table_log_class_id_fkey` (`log_class_id`),
  KEY `log_event_table_log_category_id_fkey` (`log_category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

-- 
-- RELATIONS FOR TABLE `log_event_table`:
--   `log_class_id`
--       `log_class_table` -> `log_class_id`
--   `log_category_id`
--       `log_category_table` -> `log_category_id`
-- 

-- --------------------------------------------------------

-- 
-- Table structure for table `log_table`
-- 
-- Creation: Jun 16, 2009 at 05:14 PM
-- 

CREATE TABLE `log_table` (
  `log_id` int(11) NOT NULL auto_increment,
  `creation` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `log_event_id` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `affected_uid` int(11) NOT NULL,
  `details` text NOT NULL,
  PRIMARY KEY  (`log_id`),
  KEY `log_table_log_event_id_fkey` (`log_event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

-- 
-- RELATIONS FOR TABLE `log_table`:
--   `log_event_id`
--       `log_event_table` -> `log_event_id`
-- 

-- 
-- Constraints for dumped tables
-- 

-- 
-- Constraints for table `log_event_table`
-- 
ALTER TABLE `log_event_table`
  ADD CONSTRAINT `log_event_table_log_class_id_fkey` FOREIGN KEY (`log_class_id`) REFERENCES `log_class_table` (`log_class_id`),
  ADD CONSTRAINT `log_event_table_log_category_id_fkey` FOREIGN KEY (`log_category_id`) REFERENCES `log_category_table` (`log_category_id`);

-- 
-- Constraints for table `log_table`
-- 
ALTER TABLE `log_table`
  ADD CONSTRAINT `log_table_log_event_id_fkey` FOREIGN KEY (`log_event_id`) REFERENCES `log_event_table` (`log_event_id`);
