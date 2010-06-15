
--
-- Permission table
-- Specific permissions: these are words used by the code to determine if the user has the appropriate permission.
--
CREATE TABLE cswal_permission_table (
	permission_id serial NOT NULL PRIMARY KEY,
	permission_name text NOT NULL UNIQUE
);


--
-- Group table
-- Enumerates a list of permissions for a specific group: i.e. for "blog", this could list "create", "edit", and "delete" (among others).
--
CREATE TABLE cswal_group_table (
	group_id serial NOT NULL PRIMARY KEY,
	group_name text NOT NULL UNIQUE
);

--
-- Permission + Group table
-- Enumerates permissions for a given group: any permissions not specifically entered are denied.
--
CREATE TABLE cswal_permission_group_table (
	permission_group_id serial NOT NULL PRIMARY KEY,
	permission_id integer NOT NULL REFERENCES cswal_permission_table(permission_id),
	group_id integer NOT NULL REFERENCES cswal_group_table(group_id),
	allowed boolean NOT NULL DEFAULT false,
	description text
);

--
-- User + Group table
-- Assigns a user to one or more groups.
-- NOTE::: the "user_id" column should be (manually) foreign-keyed to an existing user table.
--
CREATE TABLE cswal_user_group_table (
	user_group_id serial NOT NULL PRIMARY KEY,
	user_id integer NOT NULL,
	group_id integer NOT NULL REFERENCES cswal_group_table(group_id)
);


--
-- User + Permission table
-- Give users specific permissions, overriding default and/or assigned group permissions.
--
CREATE TABLE cswal_user_permission_table (
	user_permission_id serial NOT NULL PRIMARY KEY,
	user_id integer NOT NULL,
	group_id integer NOT NULL REFERENCES cswal_group_table(group_id),
	permission_id integer NOT NULL REFERENCES cswal_permission_table(permission_id),
	allowed boolean NOT NULL DEFAULT false
);



