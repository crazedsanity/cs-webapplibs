--
-- SVN INFORMATION:::
-- ---------------
--	SVN Signature::::::: $Id$
--	Last Author::::::::: $Author$
--	Current Revision:::: $Revision$
--	Repository Location: $HeadURL$
--	Last Updated:::::::: $Date$
--

CREATE TABLE cswal_auth_token_table (
	auth_token_id serial NOT NULL PRIMARY KEY,
	uid integer NOT NULL DEFAULT 0,
	checksum text NOT NULL,
	token text NOT NULL,
	creation date NOT NULL DEFAULT NOW(),
	duration interval NOT NULL DEFAULT '1 day'::interval
);