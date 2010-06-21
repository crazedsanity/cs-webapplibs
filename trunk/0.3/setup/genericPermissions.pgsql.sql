BEGIN;

--
-- Group table
-- Enumerates a list of permissions for a specific group: i.e. for "blog", this could list "create", "edit", and "delete" (among others).
--
CREATE TABLE cswal_group_table (
	group_id serial NOT NULL PRIMARY KEY,
	group_name text NOT NULL UNIQUE,
	group_admin integer NOT NULL REFERENCES cs_authtentication_table(uid)
);

--
-- User + Group table
-- Assigns a user to one or more groups.
-- NOTE::: the "user_id" table should be updated to match your database schema.
--
CREATE TABLE cswal_user_group_table (
	user_group_id serial NOT NULL PRIMARY KEY,
	user_id integer NOT NULL REFERENCES cs_authentication_table(uid),
	group_id integer NOT NULL REFERENCES cswal_group_table(group_id)
);


--
-- Object table
-- Contains unique list of objects along with the owner, default group, & user/group/other permissions (like *nix filesystem permissions)
-- The permissions for user/group/other could be converted to octal (i.e. "rwxrwxrwx" == "777"), but it isn't as straightforward to read.
-- NOTE::: the "user_id" table should be updated to match your database schema.
--
CREATE TABLE cswal_object_table (
	object_id serial NOT NULL PRIMARY KEY,
	object_name text NOT NULL UNIQUE,
	user_id integer NOT NULL REFERENCES cs_authentication_table(uid),
	group_id integer NOT NULL REFERENCES cswal_group_table(group_id),
	u_r boolean NOT NULL DEFAULT TRUE,
	u_w boolean NOT NULL DEFAULT TRUE,
	u_x boolean NOT NULL DEFAULT FALSE,
	g_r boolean NOT NULL DEFAULT TRUE,
	g_w boolean NOT NULL DEFAULT FALSE,
	g_x boolean NOT NULL DEFAULT FALSE,
	o_r boolean NOT NULL DEFAULT TRUE,
	o_w boolean NOT NULL DEFAULT FALSE,
	o_x boolean NOT NULL DEFAULT FALSE
);


INSERT INTO cswal_group_table (group_name) VALUES ('www');
INSERT INTO cswal_group_table (group_name) VALUES ('blogs');
INSERT INTO cswal_group_table (group_name) VALUES ('admin');

INSERT INTO cswal_object_table 
	(object_name,user_id, group_id)
	VALUES
	('/',        101,     1);

INSERT INTO cswal_object_table
	(object_name, user_id, group_id, g_r,  g_w)
	VALUES 
	('/member', 101,       2,        true, true);
