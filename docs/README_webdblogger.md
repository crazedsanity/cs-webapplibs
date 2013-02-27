

# CS Web DB Logger (cs_webdblogger)

This system is made to handle logging events from a web application.  It's built to be very simple to use, and to avoid the necessity of remembering hard-coded ID's for logging with the proper category, while maintaining a semi-normalized database structure.

## How It works...

Once the appropriate schema has been built, code can be updated easily to start 
logging:

```php
	//Create the class...
	$this->log = new cs_webdblogger($dbObj, 'Generic Activity');
	
	//Now call the logger.
	$this->log->log_by_class('User viewed page', 'info', $this->userId);
```



UNDERSTANDING THE DATABASE SCHEMA:::
I understand things best from real data, so here goes::::

```
	user@localhost:~/cs-webapplibs$ cat docs/log_test.sql 
	select 
		rca.category_name as category, 
		rcl.class_name as class, 
		le.description 
	from 
		cswal_event_table AS le 
		INNER JOIN cswal_class_table AS rcl USING (class_id) 
		INNER JOIN cswal_category_table AS rca USING (category_id) 
	limit 5;
	user@localhost:~/cs-webapplibs$ psql -U postgres cs__test
	psql (9.1.3)
	Type "help" for help.

	live_cs_project=# \i docs/log_test.sql
	 category | class  |       description
	----------+--------+--------------------------
	 Project  | Create | Project: created record
	 Project  | Delete | Project: deleted record
	 Project  | Update | Project: updated record
	 Project  | Error  | Project: ERROR
	 Helpdesk | Create | Helpdesk: Created record
	(5 rows)
```

The category indicates what system it is attached to, and class is a more 
generic way of indicating what type of action it is. 


