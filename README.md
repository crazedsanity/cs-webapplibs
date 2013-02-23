## Web Application Libraries

(a.k.a. "CS-WebAppLibs" or "CSWAL")

__WARNING #1:__ Version 0.5.x and above utilize PDO and prepared statements. 
Applications/libraries/frameworks that were written against a prior version may 
need to be rewritten to handle the differences.  In theory, it is still fully 
backwards-compatible... but I make no guarantees.

__WARNING #2:__ If you don't read what sparse documentation there is, you 
probably won't get it.

__WARNING #3:__ This code was not written for the faint of heart. The naming 
conventions may be inconsistent. Some of these classes, such as the WebDBUpgrade 
system, is made to be transparent, so interacting with it can be difficult; 
others, such as the logging system, are meant to be used with little need to 
understand their inner-workings. 

__WARNING #4:__ Due to lack of help, the only officially-supported database is 
PostgreSQL.  Most things should be fairly well database-agnostic, though some of 
the fancier features (such as transactions within the upgrade system) may not 
work as expected: MySQL can sometimes automatically commits changes without 
warning, such as when transactions cross transactionable and transactionless 
tables.

*On to the documentation...*

This is a set of libraries for working with PHP-based web applications.  It 
builds upon the foundation of CS-Content, which can be found at 
[ http://github.com/crazedsanity/cs-content ]; it also builds upon CS-PHPXML, 
which is just an XML library, and can be found at 
[ http://github.com/crazedsanity/cs-phpxml ].

### Basic Database Interaction

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

### CS Web DB Logger

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



### CS Web DB Upgrade

This system is built to make upgrading a database-driven app seamless.  No need
to coordinate SQL or schema changes with the code updates: previously, one would 
have to take the entire website down, run the schema/SQL change, update the code, 
and (if you're lucky) check a "test" website to ensure it works before unleashing
it upon your users.... if you're unlucky, they both must be run in unison, and 
if the SQL or schema change fails, you're in for a lot of sweating and praying.

Meet your saviour!  This system adds the ability to allow your system to upgrade 
itself.  Whenever you increment the version in your VERSION file, it can run a 
custom script that will do everything you need AND keep others out until it is 
done.  Once the system is updated, the next thing to touch the code containing 
the upgrade system will cause it to run.

CAVEATS: while this system will work with MySQL, I **STRONGLY** recommend 
against it.  It was built using PostgreSQL, which has a rock solid transaction 
system: if the upgrade fails, everything rolls-back and it is up to a developer
to figure out what went wrong--but the system is left in a CONSISTENT STATE. 
With MySQL, this will only work with InnoDB tables--and only if ALL AFFECTED 
TABLES ARE InnoDB.  There are also many things that will cause an implicit 
commit, meaning the code will think its in a transaction after that point, but 
it actually isn't (which is possibly worse than not having transactional 
capabilities at all).

The first time this system is implemented, you need to be aware that it will 
look for an "INITIALVERSION" value in your upgrade.xml file.  This version 
determines where it should start, so intermediary upgrade scripts will run. It 
is important to realize, however, that this setting can cause grief in and of 
itself: if you give the wrong version, scripts might run that shouldn't.  This 
is especially important for long-running projects that are expected to be able 
to be installed at any version: subsequent releases should update this initial 
version (or remove it) as necessary.

MySQL TRANSACTION INFO::: http://dev.mysql.com/doc/refman/5.0/en/implicit-commit.html

<pre>
WORK FLOW:

 --> Is there an existing LOCK file?
	 YES::
	 	--> HALT (tell the user there's an upgrade in progress).
	 NO:::
	 	--> System checks VERSION file vs. version in database
	 	--> Does version file's version match database version?
	 		YES:::
	 			-- good to go. 
	 			-- CONTINUE
	 		NO:::
	 			--> CREATE LOCK FILE
	 			--> find named script in upgrade.xml file
	 			--> include script, create new class, call method (in upgrade.xml file)
	 			--> did upgrade succeed?
	 				YES:::
	 					--> remove LOCK file
	 					--> CONTINUE
	 				NO:::
	 					--> stop upgrade process.
	 					--> HALT
 --> (continues as before)
</pre>

### Lock File

This class is intended to avoid having multiple instances of a certain process (like an upgrade, such as with cs_webdbupgrade) from "tripping" over each other.  Create a lock file somewhere on the system (which is readable + writable), and remove it when the operation completes.  The file should stay if there's a problem that keeps the operation from completing (because trying again would probably fail, or would make things worse).

```php
$lock = new cs_lock(constant('UPGRADE_LOCK'));

if(!$lock->is_lockfile_present()) {
	$lock->create_lockfile($upgradeWording);
	
	// ... do some stuff...
	// Only delete the lockfile if it all succeeded
	$lock->delete_lockfile();
}
else {
	throw new exception($lock->read_lockfile());
}

```

### NOTE REGARDING OTHER CLASSES

There are other classes implemented.  As they're tested (and I have time), more 
documentation will be added here.  For more (or less) up-to-date information, 
take a look at the "Developer's Corner" on CrazedSanity.com: 
[http://www.crazedsanity.com/content/devCorner/cs_webapplibs]
