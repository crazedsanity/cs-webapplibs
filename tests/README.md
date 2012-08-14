TESTING
==========


Why Unit Tests?
--------

If you're a developer and you're asking this question... GET OUT.

Seriously, though, unit tests are a way of testing small parts of the whole so 
everything works the way it's intended to.  When changes are made, the tests 
will confirm that (for the most part) everything seems to be working as 
expected.

PDO Rewrite and Tests
-------

It might seem like a lot of extra work to write all these tests while changing 
all this code to use PDO.  And it is.  But if there isn't a definition of what 
is normal, how do we know if we're conforming?  The tests show how things are 
supposed to be, so when it's all done, there's confirmation that it's still 
doing the right thing.  And maybe some bugs will get quashed in the process.

Why PostgreSQL?
-------

Postgres supports full transactions: schema changes + data changes can be done 
in a single transaction, and the whole thing can be rolled back in the event of 
a failure.  MySQL will silently commit parts of a transaction, causing a 
rollback (abort) to leave the database in an inconsistent state.

Getting Started...
-------

Get a copy of SimpleTest or something compatible, then run all the tests, like 
what is in the "example_test.php" file (in this directory).  Creating a lockfile 
will ensure that tests running against the database (or filesystem) don't throw 
misleading errors due to another batch of tests running simultaneously (or if 
the browser page is refreshed too quickly).

Changes (or new code) should *always* be tested; all tests should pass before 
they get accepted into the master branch to maximize stability (generally, I'll 
handle that).

To perform database tests, create a database with the proper owner.  For the 
example, I'll assume the database name is "unittester" and the role name is the 
same ("unittester"):
<pre>
	CREATE DATABASE unittester with owner unittester;
	drop schema public cascade; create schema public authorization unittester;
</pre>