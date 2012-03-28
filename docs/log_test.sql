select 
	rca.category_name as category, 
	rcl.class_name as class, 
	le.description 
from 
	cswal_event_table AS le 
	INNER JOIN cswal_class_table AS rcl USING (class_id) 
	INNER JOIN cswal_category_table AS rca USING (category_id) 
limit 5;
