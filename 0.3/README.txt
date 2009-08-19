
=== CS Web DB Logger ===
Once the appropriate schema has been built, code can be updated easily to start logging:

//Create the class...
$this->log = new cs_webdblogger($dbObj, 'Generic Activity');

//Now call the logger.
$this->log->log_by_class('User viewed page', 'info', $this->userId);



UNDERSTANDING THE DATABASE SCHEMA:::
I understand things best from real data, so here goes::::

live_cs_project=# select rca.name as category, rcl.name as class, le.description from log_event_table AS le INNER JOIN log_class_table AS rcl USING (log_class_id) INNER JOIN log_category_table AS rca USING (log_category_id) limit 5;
 category | class  |       description
----------+--------+--------------------------
 Project  | Create | Project: created record
 Project  | Delete | Project: deleted record
 Project  | Update | Project: updated record
 Project  | Error  | Project: ERROR
 Helpdesk | Create | Helpdesk: Created record
(5 rows)

The category indicates what system it is attached to, and class is a more 
generic way of indicating what type of action it is. 


$Id$ 