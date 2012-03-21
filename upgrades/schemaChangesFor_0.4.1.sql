insert into cs_authentication_table (uid, username, passwd, is_active, user_status_id) VALUES (0, 'anonymous', '__DISABLED__', false, 0);
ALTER TABLE ONLY cswal_log_table ADD CONSTRAINT cswal_log_table_uid_fkey FOREIGN KEY (uid) REFERENCES cs_authentication_table(uid);
ALTER TABLE ONLY cswal_session_store_table DROP COLUMN user_id;
ALTER TABLE ONLY cswal_session_store_table ADD COLUMN uid integer;
