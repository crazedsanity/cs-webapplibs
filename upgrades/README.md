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

Using "run_query()" to perform inserts should work just fine.  To make the code quicker (if the sequence name is known), try this:

```php
$insertedId = $phpDbObj->run_insert($sql, $params, $sequenceName);
```

### Retrieving Records: farray_fieldnames()

 * use get_single_record() in place of farray_fieldnames() when only one record should be returned
 * calls that pass more than one argument are almost certainly broken

### Quirks

 * run_insert() requires a sequence name as the 3rd argument: passing a blank string or an invalid sequence name will generate an error
 * farray_nvp() returns an array of name -> value pairs... it's very useful.
