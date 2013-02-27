# Database Abstraction Layer (cs_phpDB)

This system provides a uniform way of interacting with databases.  Since the formal integration of PDO into PHP, it is perhaps not quite as important as it used to be, but still provides a good deal of useful bits.

## Basic Database Interaction

Interacting with the database is fairly straightforward: cs_phpDB is basically 
just a wrapper for PDO (if you're not familiar, go read it 
[http://php.net/manual/en/book.pdo.php]).  First, create an object to work with:

```php
	$dsn = "psql:host=localhost;dbname=test";
	$username = "username";
	$password = "myP@ssw0rd";
	$db = new cs_phpDB($dsn, $username, $password);
```

Performing a basic query is simple.  This example runs the query and returns an 
array of records, indexed on the value of the "user_id" column:

```php
	$numRows = $db->run_query("SELECT * FROM users WHERE user_status <> :uid", array('uid'=>0));
	echo "got ". $numRows ." back!";
	$myArray = $db->farray_fieldnames();
```

So now there's an array of records.

```php
	foreach($myArray as $key=>$subArray) {
		print "KEY: ". $key;
		foreach($subArray as $sKey => $val) {
			print " [". $sKey ."]=(". $val .")";
		}
		print "<br />\n";
	}
	/* EXAMPLE OUTPUT::::
	 * KEY: 1 [username]=(john@doe.com) [user_status]=(1)
	 * KEY: 2 [username]=(bob@dole.com) [user_status]=(1)
	 * KEY: 2 [username]=(jake@dole.com) [user_status]=(3)
	 */
```

You should be off and running with that! For some great examples, look at the 
code in "abstract/cs_singleTableHandler.abstract.class.php".  That class deals 
with pretty much everything regarding a single database table.  There are some 
tests that hopefully provide some insight.  Dig into the other class files, as 
most of them deal with database manipulation of some sort. 

