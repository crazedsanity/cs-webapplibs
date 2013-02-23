# Changes in Version 0.6+

The most important change in version 0.6.0 and later was the usage of parameterized queries: prior to that, queries were created as text and attempts were made to manually clean the SQL.  A few other changes were also made to make the code a little more sane, but also broke backwards-compatibility.

## Hints for Converting Code

### Parameterized Queries

Create the SQL statement with placeholders that start with a colon (e.g. :username), then create an associative array for all the parameters.  Like this:

```php
$sql = 'SELECT x,y,z FROM tablename WHERE x=:first, y LIKE :second ORDER BY z';
$params = array(
	'first'	  => "Stuff",
	'second'  => "%more"
);
```

### Performing Inserts

You can use "run_query()" to perform the insert, which should work just fine.  To make your code quicker, try this:

```php
$insertedId = $phpDbObj->run_insert($sql, $params, $sequenceName);
```

### Quirks

 * "farray_fieldnames()" will now *always* return the same format, even if there's only one record: try get_single_record() instead	
