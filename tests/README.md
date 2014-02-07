# TESTING


## Why Unit Tests?

If you're a developer and you're asking this question... GET OUT (that's just a 
joke, we all have to start somewhere).

Seriously, though, unit tests are a way of testing small parts of the whole so 
everything works the way it's intended to.  When a massive refactoring happens, 
the tests will ensure that everything will continue to work the way it used to, 
so that backwards-compatibility is maintained.


## PDO Rewrite and Tests


It might seem like a lot of extra work to write all these tests while changing 
all this code to use PDO.  And it is.  But if there isn't a definition of what 
is normal, how do we know if we're conforming?  The tests show how things are 
supposed to be, so when it's all done, there's confirmation that it's still 
doing the right thing.  And maybe some bugs will get quashed in the process.

## Why PostgreSQL?

Postgres supports full transactions: schema changes + data changes can be done 
in a single transaction, and the whole thing can be rolled back in the event of 
a failure.  MySQL will silently commit parts of a transaction, causing a 
rollback (abort) to leave the database in an inconsistent state.

Did you know that even "transactional tables" (InnoDB tables) aren't all that 
safe?  The next ID isn't guaranteed after restarting MySQL because (at least 
some versions of MySQL) will reset that ID to be the max ID of that column. It's 
yet another unexpected thing that might bite you (or your application) down the 
road.


## Getting Started...

Get a copy of [PHPUnit](http://phpunit.de).  In Debian-based systems, this 
requires little more than running ```sudo apt-get install phpunit``` on the 
command line.  This is the system that all tests are built upon.

Get a copy of [Composer](http://getcomposer.org).  Assuming it is installed so 
that ```composer``` works on the command line, go into the main Web App Libs 
directory and run ```composer install```.  This will install the necessary 
dependencies.

## Making Changes

Changes (or new code) should *always* be tested; all tests should pass before 
they get accepted into the master branch to maximize stability (generally, I'll 
handle that).

To perform database tests, create a database with the proper owner.  Because of 
the integration with [Travis-CI](http://travis-ci.org), the following settings 
are used:
 * _user_: postgres
 * _pass_: (none)
 * _database_: \_unittest\_
 * _host_: localhost
 * _port_: (default)

Please _DO NOT_ use an existing database that has data in it.  The scripts will 
destroy that data automatically.