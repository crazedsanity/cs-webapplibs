
begin;

--
-- uses "bitwise" operations, with "CRUD" (create read update delete):
-- C=1
-- R=2
-- U=4
-- D=8
-- e.g. ("crud" == 15; "cru-" == 7; "-r--" == 2; etc)

CREATE TABLE cswal_group_table (
	group_id serial NOT NULL PRIMARY KEY,
	group_name varchar(32) NOT NULL UNIQUE,
	group_description text
);

CREATE TABLE cswal_permission_table (
	permission_id serial NOT NULL PRIMARY KEY,
	location text NOT NULL UNIQUE,
	default_permissions smallint NOT NULL DEFAULT 2
);

CREATE TABLE cswal_group_permission_table (
	group_permission_id serial NOT NULL PRIMARY KEY,
	group_id integer NOT NULL REFERENCES cswal_group_table(group_id),
	permission_id integer NOT NULL REFERENCES cswal_permission_table(permission_id),
	permissions smallint NOT NULL DEFAULT 2
);

CREATE TABLE cswal_user_group_table (
	user_group_id serial NOT NULL PRIMARY KEY,
	user_id integer NOT NULL REFERENCES cs_authentication_table(uid),
	group_id integer NOT NULL REFERENCES cswal_group_table(group_id)
);

CREATE TABLE cswal_user_permission_table (
	user_permission_id serial NOT NULL PRIMARY KEY,
	user_id integer NOT NULL REFERENCES cs_authentication_table(uid),
	permission_id integer NOT NULL REFERENCES cswal_permission_table(permission_id),
	permissions smallint NOT NULL DEFAULT 2
);
