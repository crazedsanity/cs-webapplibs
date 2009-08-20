

--
-- Store session data in here.
-- Idea originally from: http://www.developertutorials.com/tutorials/php/saving-php-session-data-database-050711
--

CREATE TABLE cs_session_store_table (
	session_store_id serial NOT NULL PRIMARY KEY,
	session_id varchar(32) NOT NULL DEFAULT '' UNIQUE,
	user_id varchar(16),
	date_created timestamp NOT NULL DEFAULT NOW(),
	last_updated timestamp NOT NULL DEFAULT NOW(),
	session_data text
);

