
--
-- The user status table is a list of statuses indicating what state a user's
--	account is in.
-- THESE VALUES MUST MATCH THE CODE.
--
CREATE TABLE cs_user_status_table (
    user_status_id integer NOT NULL PRIMARY KEY,
    description text NOT NULL,
    is_active boolean DEFAULT true NOT NULL
);
INSERT INTO cs_user_status_table (user_status_id, description, is_active)
	VALUES 
		(0, 'Disabled User', false),
		(1, 'Active User', true),
		(2, 'Registration Pending', false);


--
-- The authentication table is where usernames & passwords are stored.
-- The "passwd" column is created like this (on a Linux system): 
--		echo "administrator-changeMe" | sha1sum
-- 
CREATE TABLE cs_authentication_table (
    uid serial NOT NULL PRIMARY KEY,
    username text NOT NULL UNIQUE,
    passwd text,
    date_created date DEFAULT now() NOT NULL,
    last_login timestamp with time zone,
    email text,
    user_status_id integer NOT NULL DEFAULT 0 
		REFERENCES cs_user_status_table(user_status_id)
);
INSERT INTO cs_authentication_table (uid,username, user_status_id) 
	VALUES (0, 'anonymous', 0);
INSERT INTO cs_authentication_table (username, passwd, user_status_id)
	VALUES	('test', '75eba0f69d185ef816d0cee43ad44d4b2240de02', 1),			-- "letMeIn"
			('administrator', 'c2fc1fdc72ef8b92cf3d98bd1a60725cafdebdaa', 1);	-- "changeMe"


--
-- The category is the high-level view of the affected system.  If this were 
--	a project management system with projects and issues, then there would 
--	be a category for "project" and for "issue".
--
CREATE TABLE cswal_category_table (
	category_id serial NOT NULL PRIMARY KEY,
	category_name text NOT NULL UNIQUE
);


--
-- The class is an action performed on a category.  So, if there is a project 
--	that was created, "project" would be the category (see above) and the 
--	class would then be "create".
--
CREATE TABLE cswal_class_table (
	class_id serial NOT NULL PRIMARY KEY,
	class_name text NOT NULL UNIQUE
);

	
--
-- Events are where the categories and rather generic events come together. 
--	This explains what the actual action was (via the description). Once the 
--	code starts creating these events and logging for a while, admins can go 
--	and make the description for that event more useful, especially if the 
--	logs are going to be displayed in any sort of useful manner.
--
CREATE TABLE cswal_event_table (
	event_id serial NOT NULL PRIMARY KEY,
	class_id integer NOT NULL REFERENCES cswal_class_table(class_id),
	category_id integer NOT NULL REFERENCES cswal_category_table(category_id),
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
CREATE TABLE cswal_log_table (
	log_id serial NOT NULL PRIMARY KEY,
	creation timestamp NOT NULL DEFAULT NOW(),
	event_id integer NOT NULL REFERENCES cswal_event_table(event_id),
	uid integer NOT NULL REFERENCES cs_authentication_table(uid),
	affected_uid integer NOT NULL,
	details text NOT NULL
);


--
-- List of distinct attribute names.
--
CREATE TABLE cswal_attribute_table (
	attribute_id serial NOT NULL PRIMARY KEY,
	attribute_name text NOT NULL UNIQUE
);

--
-- Linkage for attributes to logs.
--
CREATE TABLE cswal_log_attribute_table (
	log_attribute_id serial NOT NULL PRIMARY KEY,
	log_id int NOT NULL REFERENCES cswal_log_table(log_id),
	attribute_id int NOT NULL UNIQUE REFERENCES cswal_attribute_table(attribute_id),
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


CREATE TABLE cswal_token_type_table (
	token_type_id serial NOT NULL PRIMARY KEY,
	token_type varchar(30) NOT NULL UNIQUE,
	token_desc text
);
INSERT INTO cswal_token_type_table VALUES (0, 'unknown', 'Unknown token type');
INSERT INTO cswal_token_type_table (token_type, token_desc) VALUES ('lost_password', 'Lost password system');


CREATE TABLE cswal_auth_token_table (
	auth_token_id text NOT NULL UNIQUE PRIMARY KEY,
	token_type_id integer NOT NULL REFERENCES cswal_token_type_table(token_type_id) DEFAULT 0,
	uid integer NOT NULL REFERENCES cs_authentication_table(uid) DEFAULT 0,
	passwd text NOT NULL,
	max_uses integer NOT NULL DEFAULT 1,
	total_uses integer NOT NULL DEFAULT 0,
	creation timestamp NOT NULL DEFAULT NOW(),
	expiration timestamp DEFAULT NULL,
	stored_value text DEFAULT NULL
);


--
-- Store session data in here.
-- Idea originally from: http://www.developertutorials.com/tutorials/php/saving-php-session-data-database-050711
--

CREATE TABLE cswal_session_table (
	session_id varchar(40) NOT NULL UNIQUE PRIMARY KEY,
	uid integer REFERENCES cs_authentication_table(uid),
	date_created timestamp NOT NULL DEFAULT NOW(),
	last_updated timestamp NOT NULL DEFAULT NOW(),
	num_checkins integer NOT NULL DEFAULT 0,
	session_data text
);
