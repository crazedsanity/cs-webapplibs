# CS Session DB (cs_sessionDB)

Session DB is used to store session information in a database instead of using files, and is an extension of [CS-Content](https://github.com/crazedsanity/cs-content).  Using a database allows for simpler sharing of sessions across multiple servers (or at least a different way of doing so).  It also allows for easy determination of how many sessions are anonymous versus logged-in.

## How It Works

If your web application is already using CS-Content, then integration is actually quite seamless: by adding a few constants to your web application code, Session DB will automatically begin storing session information into the database.  In fact, it will even create the appropriate tables in the database (provided the database is PostgreSQL).

### Using Constants

Assuming your website is already setup with CS-Content, there's just a few steps.  In your main includes file (e.g. "lib/includes.php", or just somewhere that is always run prior to calling "new contentSystem()"), just add a couple of lines:

```php
define('SESSION_DBSAVE', 1);
define('SESSION_DB_DSN', "pgsql:host=localhost;dbname=$dbname;port=$port");
define('SESSION_DB_USER', $dbUsername);
define('SESSION_DB_PASS', $dbPassword);
```

### Using Site Configuration File

Using the [Site Configuration system](README_siteConfig.md), just add a few lines in your XML configuration file:

```xml
<main>
....
	<cs-content>
		...
		<SESSION_DB_DSN setconstant="1">{WEBSITE/DB_DSN}</SESSION_DB_DSN>
        <SESSION_DB_USER setconstant="1">{WEBSITE/DB_PG_DBUSER}</SESSION_DB_USER>
        <SESSION_DB_PASSWORD setconstant="1">{WEBSITE/DB_PG_DBPASS}</SESSION_DB_PASSWORD>
	</cs-content>
...
</main>
```
*NOTE: this assumes there's a "WEBSITE" section with "DB_DSN", "DB_PG_DBUSER", and "DB_PG_DBPASS" tags.*

## More Info...

This system depends on the [included schema](../setup/schema.pgsql.sql).  This schema includes a user authentication table (for storing usernames, passwords, etc), logging info, and other required systems.  Change the schema at your own peril.  Note that currently, the only database that is technically supported is PostgreSQL.

