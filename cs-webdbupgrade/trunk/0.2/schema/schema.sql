--
-- SVN INFORMATION:::
-- ---------------
--	SVN Signature::::::: $Id$
--	Last Author::::::::: $Author$
--	Current Revision:::: $Revision$
--	Repository Location: $HeadURL$
--	Last Updated:::::::: $Date$
--
-- This table create statement MUST work in PostgreSQL v8.2.x+ AND MySQL v5.0.x+: 
-- otherwise separate schema files have to be created and the code will have to 
-- do extra checking...
-- 
-- The "{tableName}" portion will be replaced with the value of the configured 
-- "DB_TABLE" setting.
CREATE TABLE {tableName} (
	{primaryKey} serial NOT NULL PRIMARY KEY,
	project_name varchar(30) NOT NULL UNIQUE,
	version_string varchar(50) NOT NULL,
	version_major integer NOT NULL,
	version_minor integer NOT NULL,
	version_maintenance integer NOT NULL,
	version_suffix varchar(20) NOT NULL
);