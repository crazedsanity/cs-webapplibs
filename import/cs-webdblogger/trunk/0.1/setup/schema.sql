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
-- TODO: add prefix to these tables...
-- 

--
-- The category is the high-level view of the affected system.  If this were 
--	a project management system with projects and issues, then there would 
--	be a category for "project" and for "issue".
--
CREATE TABLE log_category_table (
	log_category_id serial NOT NULL PRIMARY KEY,
	name text NOT NULL
);


--
-- The class is an action performed on a category.  So, if there is a project 
--	that was created, "project" would be the category (see above) and the 
--	class would then be "create".
--
CREATE TABLE log_class_table (
	log_class_id serial NOT NULL PRIMARY KEY,
	name text NOT NULL
);


--
-- Events are where the categories and rather generic events come together. 
--	This explains what the actual action was (via the description). Once the 
--	code starts creating these events and logging for a while, admins can go 
--	and make the description for that event more useful, especially if the 
--	logs are going to be displayed in any sort of useful manner.
--
CREATE TABLE log_event_table (
	log_event_id serial NOT NULL PRIMARY KEY,
	log_class_id integer NOT NULL REFERENCES log_class_table(log_class_id),
	log_category_id integer NOT NULL REFERENCES log_category_table(log_category_id),
	description text NOT NULL
)


--
-- This is the meat of the system, where all the other tables converge to make 
--	a useful entry indicating some sort of event that happened on the system, 
--	along with any pertinent details.  The "uid" and "affected_uid" columns 
--	are for matching the actions up with a user; I like to create a uid of 0 
--	(zero) for logging non-authenticated things, and a 1 (one) for activities 
--	performed by the system itself.
--
CREATE TABLE log_table (
	log_id serial NOT NULL PRIMARY KEY,
	creation timestamp NOT NULL DEFAULT NOW(),
	log_event_id integer NOT NULL REFERENCES log_event_table(log_event_id),
	uid integer NOT NULL,
	affected_uid integer NOT NULL,
	details text NOT NULL
);

