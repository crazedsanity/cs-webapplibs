
begin;

CREATE TABLE cswal_token_type_table (
	token_type_id serial NOT NULL PRIMARY KEY,
	token_type varchar(30) NOT NULL UNIQUE,
	token_desc text
);
INSERT INTO cswal_token_type_table VALUES (0, 'unknown', 'Unknown token type');
INSERT INTO cswal_token_type_table (token_type, token_desc) VALUES ('lost_password', 'Lost password system');

ALTER TABLE cswal_auth_token_table 
	ADD COLUMN uid integer 
	references cs_authentication_table(uid) NOT NULL DEFAULT 0;
UPDATE cswal_auth_token_table SET uid=0;

ALTER TABLE cswal_auth_token_table 
	ADD COLUMN token_type_id integer 
	references cswal_token_type_table(token_type_id) NOT NULL DEFAULT 0;
UPDATE cswal_auth_token_table SET token_type_id=0;