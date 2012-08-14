
DROP TABLE IF EXISTS cswal_session_store_table;
DROP TABLE IF EXISTS cs_session_table;

DROP TABLE IF EXISTS cswal_session_table;


CREATE TABLE cswal_session_table (
	session_id varchar(40) NOT NULL UNIQUE PRIMARY KEY,
	uid integer REFERENCES cs_authentication_table(uid),
	date_created timestamp NOT NULL DEFAULT NOW(),
	last_updated timestamp NOT NULL DEFAULT NOW(),
	session_data text
);

ALTER TABLE ONLY cswal_class_table
    ADD CONSTRAINT cswal_class_table_class_name_key UNIQUE (class_name);

ALTER TABLE ONLY cswal_category_table_table
    ADD CONSTRAINT cswal_category_table_category_name_key UNIQUE (category_name);