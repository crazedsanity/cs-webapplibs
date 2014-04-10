
ALTER TABLE cs_authentication_table ALTER COLUMN passwd TYPE text;


-- NOTE: the schema changes are pretty big; it might be easier to simply drop 
--	and recreate it based on the definition in the schema file.
ALTER TABLE cswal_auth_token_table ALTER COLUMN auth_token_id TYPE text;
ALTER TABLE cswal_auth_token_table DROP COLUMN uid;
ALTER TABLE cswal_auth_token_table RENAME checksum TO passwd;
ALTER TABLE cswal_auth_token_table ALTER COLUMN max_uses SET NOT NULL;
ALTER TABLE cswal_auth_token_table ALTER COLUMN max_uses SET DEFAULT 1;
ALTER TABLE cswal_auth_token_table DROP COLUMN last_updated;
ALTER TABLE cswal_auth_token_table ADD COLUMN stored_value TEXT;
ALTER TABLE cswal_auth_token_table ALTER COLUMN stored_value SET DEFAULT NULL;
ALTER TABLE cswal_auth_token_table DROP COLUMN token;
ALTER TABLE cswal_auth_token_table ALTER COLUMN expiration DROP NOT NULL;

DROP SEQUENCE IF EXISTS cswal_auth_token_table_auth_token_id_seq1 cascade;
