--
-- The assumption is that cs-webdblogger{} already created the new tables: this simply copies data into 
--	those new tables & then drops the old ones.


INSERT INTO cswdbl_category_table (category_id,     category_name) 
                            SELECT log_category_id, name          FROM log_category_table 
                            WHERE log_category_id NOT IN (SELECT category_id FROM cswdbl_category_table)
                            ORDER BY log_category_id;
INSERT INTO cswdbl_class_table (class_id,    class_name) 
        				 SELECT log_class_id, name 
        				 FROM log_class_table 
        				 WHERE log_class_id NOT IN (SELECT class_id FROM cswdbl_class_table)
        				 ORDER BY log_class_id;
INSERT INTO cswdbl_event_table (event_id,     category_id,     class_id,     description) 
                         SELECT log_event_id, log_category_id, log_class_id, description 
                         FROM log_event_table 
                         WHERE log_event_id NOT IN (SELECT event_id FROM cswdbl_event_table)
                         ORDER BY log_event_id;
INSERT INTO cswdbl_log_table (creation, event_id,     uid, affected_uid, details) 
                       SELECT creation, log_event_id, uid, affected_uid, details FROM log_table;
                       

SELECT setval('cswdbl_category_table_category_id_seq', (SELECT max(category_id) FROM cswdbl_category_table));
SELECT setval('cswdbl_class_table_class_id_seq', (SELECT max(class_id) FROM cswdbl_class_table));
SELECT setval('cswdbl_event_table_event_id_seq', (SELECT max(event_id) FROM cswdbl_event_table));
SELECT setval('cswdbl_log_table_log_id_seq', (SELECT max(log_id) FROM cswdbl_log_table));


DROP TABLE log_category_table CASCADE;
DROP TABLE log_class_table CASCADE;
DROP TABLE log_event_table CASCADE;
DROP TABLE log_table CASCADE;