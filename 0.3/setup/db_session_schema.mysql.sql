

--
-- Store session data in here.
-- Idea originally from: http://www.developertutorials.com/tutorials/php/saving-php-session-data-database-050711
--

CREATE TABLE `cswal_session_store_table` (
  `session_store_id` int  NOT NULL AUTO_INCREMENT,
  `session_id` varchar(32)  NOT NULL,
  `user_id` varchar(16)  NOT NULL,
  `date_created` datetime  NOT NULL,
  `last_updated` datetime  NOT NULL,
  `session_data` LONGTEXT  NOT NULL,
  PRIMARY KEY (`session_store_id`)
)
ENGINE = InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;