
--
-- Group table
-- Enumerates a list of permissions for a specific group: e.g. for "blog", this could list "create", "edit", and "delete" (among others).
--
CREATE TABLE cswal_group_table (
	group_id serial NOT NULL PRIMARY KEY,
	group_name text NOT NULL UNIQUE,
	group_admin integer NOT NULL REFERENCES cs_authentication_table(uid),
	created TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

--
-- User + Group table
-- Assigns a user to one or more groups.
-- NOTE::: the "user_id" table should be updated to match your database schema.
--
CREATE TABLE cswal_user_group_table (
	user_group_id serial NOT NULL PRIMARY KEY,
	user_id integer NOT NULL REFERENCES cs_authentication_table(uid),
	group_id integer NOT NULL REFERENCES cswal_group_table(group_id),
	created TIMESTAMPTZ NOT NULL DEFAULT NOW()
);


-- 
-- System Table
-- Allows types of permissions to be separated (i.e. URL-based permissions from action-based permissions)
-- NOTE::: setting "use_default_deny" to TRUE means requests to objects within the given system are automatically denied, a setting
--	of FALSE means those requests are automatically granted (USE WITH CAUTION).
-- 
CREATE TABLE cswal_system_table (
	system_id serial NOT NULL PRIMARY KEY,
	system_name text NOT NULL UNIQUE,
	use_default_deny boolean NOT NULL DEFAULT TRUE,
	created TIMESTAMPTZ NOT NULL DEFAULT NOW()
);


--
-- Permission table
-- Contains paths along with the owner, default group, & user/group/other 
--	permissions (like *nix filesystem permissions) The permissions for 
--	user/group/other could be converted to octal (i.e. "rwxrwxrwx" == "777"), 
--	but it isn't as straightforward to read.
-- NOTE::: the "user_id" table should be updated to match your database schema.
--
CREATE TABLE cswal_permission_table (
	permission_id serial NOT NULL PRIMARY KEY,
	system_name integer NOT NULL DEFAULT 0 REFERENCES cswal_system_table(system_id),
	path text NOT NULL,
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
	o_x boolean NOT NULL DEFAULT FALSE,
	created TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

INSERT INTO cswal_system_table (system_id, system_name) VALUES (0, 'DEFAULT');

ALTER TABLE ONLY cswal_permission_table
	ADD CONSTRAINT cswal_permission_table_system_path_key UNIQUE (system_name, path);

