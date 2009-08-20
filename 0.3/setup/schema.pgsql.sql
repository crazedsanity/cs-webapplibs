--
-- SVN INFORMATION:::
-- ---------------
--	SVN Signature::::::: $Id$
--	Last Author::::::::: $Author$
--	Current Revision:::: $Revision$
--	Repository Location: $HeadURL$
--	Last Updated:::::::: $Date$
--


--
-- The category is the high-level view of the affected system.  If this were 
--	a project management system with projects and issues, then there would 
--	be a category for "project" and for "issue".
--
CREATE TABLE cswdbl_category_table (
	category_id serial NOT NULL PRIMARY KEY,
	category_name text NOT NULL
);


--
-- The class is an action performed on a category.  So, if there is a project 
--	that was created, "project" would be the category (see above) and the 
--	class would then be "create".
--
CREATE TABLE cswdbl_class_table (
	class_id serial NOT NULL PRIMARY KEY,
	class_name text NOT NULL
);


--
-- Events are where the categories and rather generic events come together. 
--	This explains what the actual action was (via the description). Once the 
--	code starts creating these events and logging for a while, admins can go 
--	and make the description for that event more useful, especially if the 
--	logs are going to be displayed in any sort of useful manner.
--
CREATE TABLE cswdbl_event_table (
	event_id serial NOT NULL PRIMARY KEY,
	class_id integer NOT NULL REFERENCES cswdbl_class_table(class_id),
	category_id integer NOT NULL REFERENCES cswdbl_category_table(category_id),
	description text NOT NULL
);


--
-- This is the meat of the system, where all the other tables converge to make 
--	a useful entry indicating some sort of event that happened on the system, 
--	along with any pertinent details.  The "uid" and "affected_uid" columns 
--	are for matching the actions up with a user; I like to create a uid of 0 
--	(zero) for logging non-authenticated things, and a 1 (one) for activities 
--	performed by the system itself.
--
CREATE TABLE cswdbl_log_table (
	log_id serial NOT NULL PRIMARY KEY,
	creation timestamp NOT NULL DEFAULT NOW(),
	event_id integer NOT NULL REFERENCES cswdbl_event_table(event_id),
	uid integer NOT NULL,
	affected_uid integer NOT NULL,
	details text NOT NULL
);


--
-- List of distinct attribute names.
--
CREATE TABLE cswdbl_attribute_table (
	attribute_id serial NOT NULL PRIMARY KEY,
	attribute_name text NOT NULL UNIQUE
);

--
-- Linkage for attributes to logs.
--
CREATE TABLE cswdbl_log_attribute_table (
	log_attribute_id serial NOT NULL PRIMARY KEY,
	log_id int NOT NULL REFERENCES cswdbl_log_table(log_id),
	attribute_id int NOT NULL REFERENCES cswdbl_attribute_table(attribute_id),
	value_text text
);

-- This table create statement MUST work in PostgreSQL v8.2.x+ AND MySQL v5.0.x+: 
-- otherwise separate schema files have to be created and the code will have to 
-- do extra checking...
CREATE TABLE cswal_version_table (
	version_id serial NOT NULL PRIMARY KEY,
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
	last_updated timestamp,
	expiration timestamp NOT NULL
);

