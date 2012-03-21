
-- 
-- Table structure for table `cswdbl_log_attribute_table`
-- 

CREATE TABLE `cswdbl_log_attribute_table` (
  `log_attribute_id` int(11) NOT NULL auto_increment,
  `log_id` int(11) NOT NULL,
  `attribute_id` int(11) NOT NULL,
  `value_text` text NOT NULL,
  PRIMARY KEY  (`log_attribute_id`),
  KEY `cswdbl_log_attribute_table_log_id_fkey` (`log_id`),
  KEY `cswdbl_log_attribute_table_attribute_id_fkey` (`attribute_id`)
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
-- Constraints for table `cswdbl_log_attribute_table`
-- 
ALTER TABLE `cswdbl_log_attribute_table`
  ADD CONSTRAINT `cswdbl_log_attribute_table_attribute_id_fkey` FOREIGN KEY (`attribute_id`) REFERENCES `cswdbl_attribute_table` (`attribute_id`),
  ADD CONSTRAINT `cswdbl_log_attribute_table_log_id_fkey` FOREIGN KEY (`log_id`) REFERENCES `cswdbl_log_table` (`log_id`);
