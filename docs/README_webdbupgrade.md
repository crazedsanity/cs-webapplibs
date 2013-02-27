## Web Application Libraries

*For info about upgrades, check the "upgrades" folder*

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

For more information on the Lockfile system used with this, check out [cs_lockfile](README_lockfile.md).
