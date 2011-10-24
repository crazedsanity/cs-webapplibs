
$Id$

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




=== CS Web DB Upgrade ===


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


=== CS Generic Permissions === 

This permissions system is built to be flexible enough to be used in virtually any application for any purpose.  The "permissions" are stored in a way that basically mimics *nix filesystem permissions.  The code must know what the object is for which the user is asking permission.  That object has the following traits:
	* Object Name: the name of the item that is being assigned permissions.
		-- Examples:
			++ A URL (i.e. "/authenticated" would only be accessible to the owner + group members)
			++ A Blog (i.e. "/blog/harryjohnson" would be readable to everyone, but only writeable by user "harryjohnson")
			++ A File (i.e. "/{WEBROOT}/files/hiddenData.sqlite" might only be allowed access by a certain user)
			++ Executing a special script: (i.e. "/bin/importFiles.pl", run script using a web interface)
	* User ID: indicates what user owns this object.
	* Group ID: indicates a group that users must be part of (if not owner) to be assigned these permissions
	* Permission Bits:
		-- Each permission is a true/false value.  The name is in the form "{x}_{y}"
			++ "{x}":  u/g/o (User/Group/Owner)
			++ "{y}":  r/w/x (Read/Write/eXecute)
		-- Full Explanation:
			++ "u_r":  User's read permission; indicates if the owner can "read" (view) it.
			++ "u_w":  User's write permission; indicates if the owner can write (create/update) the object.
			++ "u_x":  User's execute permission; this rarely applies, and usage would vary greatly depending upon the object & associated code.
			++ "g_r":  Group read permission; users assigned to the associated group can/cannot "read" (view) it.
			++ "g_w":  Group write permission; users assigned to the associated group can/cannot write (create/update) the object.
			++ "g_x":  Group execute permission; users assigned to the associated group are bound by this value (usage depends on code).
			++ "o_r":  Other read permission; users that are not owners or members of the group can/cannot "read" (view) it
			++ "o_w":  Other write permission; users that are not owners or members of the group can/cannot write (create/update) the object.
			++ "o_x":  Other execute permission; users that are... you get the idea.

