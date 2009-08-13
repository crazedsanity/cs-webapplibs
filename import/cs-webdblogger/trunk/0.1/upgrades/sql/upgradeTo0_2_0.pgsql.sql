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
INSERT INTO cswdbl_log_table (log_id, creation, event_id,     uid, affected_uid, details) 
                       SELECT log_id, creation, log_event_id, uid, affected_uid, details FROM log_table;
                       