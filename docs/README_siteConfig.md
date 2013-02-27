# XML-Based Site Configuration (cs_siteConfig)

_NOTE:_ this documentation is a work in progress, so take it as more guideline than rule.  If you find something wrong with it, fix it and let me know through a pull request or however you'd like... or tell me about it.  Or deal with it... whatever you prefer.

## How It Works

All you have to do to start using cs_siteConfig is build an instance of it:

```php
$siteConfig = new cs_siteConfig(dirname(__FILE__) .'/conf/site.xml');
```

An example of how that file *might* look:

```xml
<main>
	<website>
		<DB_PG_HOST setconstant="1">hostname.of.postgres.server.domain.com</DB_PG_HOST>
		<DB_PG_PORT setconstant="1">5432</DB_PG_PORT>
		<DB_PG_DBNAME setconstant="1">live_website</DB_PG_DBNAME>
		<DB_PG_DBUSER setconstant="1">www<DB_PG_DBUSER>
		<DB_PG_DBPASS setconstant="1">******</DB_PG_DBPASS>
		<SITE_ROOT setconstant="1" setglobal="1">{_CONFIGFILE_}/../</SITE_ROOT>
	</website>
	<cs-webupgradedb>
		<db_table setconstant="1" setconstantprefix="cs_webdbupgrade">cs_version_table</db_table>
		<db_primarykey setconstant="1" setconstantprefix="cs_webdbupgrade">project_id</db_primarykey>
		<db_sequence setconstant="1" setconstantprefix="cs_webdbupgrade">{DB_TABLE}_{DB_PRIMARYKEY}_seq</db_sequence>
		<db_connect_host setconstant="1">{/WEBSITE/DB_PG_HOST}</db_connect_host>
		<db_connect_port setconstant="1">{/WEBSITE/DB_PG_PORT}</db_connect_port>
		<db_connect_dbname setconstant="1">{WEBSITE/DB_PG_DBNAME}</db_connect_dbname>
		<db_connect_user setconstant="1">{WEBSITE/DB_PG_DBUSER}</db_connect_user>
		<db_connect_password setconstant="1">{WEBSITE/DB_PG_DBPASS}</db_connect_password>
		<CONFIG_FILE_LOCATION setconstant="1">{_CONFIGFILE_}</CONFIG_FILE_LOCATION>
		<UPGRADE_CONFIG_FILE setconstant="1">{SITE_ROOT}/upgrade/upgrade.xml</UPGRADE_CONFIG_FILE>
		<RWDIR setconstant="1">{SITE_ROOT}/rw</RWDIR>
	</cs-webupgradedb>
</main>
```

Did you notice some of those "template var" things in there (like "{SITE_ROOT}" or "{/WEBSITE/DB_PG_HOST}")?  Those are references to other things values: config files can get pretty big, so this is meant to help avoid having to duplicate values all over the place.

So, some things to remember:
 * VARIABLES:
 	* written as {variable} (generic variable with curly braces, don't use spaces)
 	* back-referenced variables:
 		* in specific form: {/PATH_TO/VARIABLE}
 		* generic (non-specific) form: {VARIABLE}
 	* should be in same case as originally written (e.g. "{/website/DB_PG_HOST}")
 	* add the "setconstant" property with value of 1 if it should be turned into a constant
 	* add the "setglobal" property with a value of 1 to make a global variable
 * BUILT-IN VARIABLES (they begin and end with an underscore, might be obscured by markdown):
 	* _DIRNAMEOFFILE_: directory of the configuration file
 	* _CONFIGFILE_: path to configuration file
 	* _THISFILE_: same as _CONFIGFILE_
 	* _APPURL_: the application URL... basically "$\_SERVER['SCRIPT\_NAME']"
 * properties
 	* "setconstant" causes it to become a constant value
 	* "setglobal" adds it to the globals array
 	* "setconstantprefix" is the prefix value... so if the variable is called "VARIABLE" and the prefix is "PREFIX", the constant will be "PREFIX-VARIABLE"


## Using In Code

```php
//should be run FIRST
$siteConfig = new cs_siteConfig(dirname(__FILE__) .'/../conf/site.xml');

print "My upgrade config file location: ". constant("UPGRADE_CONFIG_FILE") ."\n";

print "Location of the site configuration file: ". constant('CONFIG_FILE_LOCATION') ."\n";
```

That should print something like:::
```
user@server:~/web/site$ php5 -f testfile.php
My upgrade config file location: /home/user/web/site/upgrade/upgrade.xml
Location of the site configuration file: /home/user/web/site/conf/site.xml
```

