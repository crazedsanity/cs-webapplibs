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