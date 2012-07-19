TESTING
==========


Why Unit Tests?
--------

If you're a developer and you're asking this question... GET OUT.

Seriously, though, unit tests are a way of testing small parts of the whole so everything works the way it's intended to.  When changes are made, the tests will confirm that (for the most part) everything seems to be working as expected.

PDO Rewrite and Tests
-------

It might seem like a lot of extra work to write all these tests while changing all this code to use PDO.  And it is.  But if there isn't a definition of what is normal, how do we know if we're conforming?  The tests show how things are supposed to be, so when it's all done, there's confirmation that it's still doing the right thing.  And maybe some bugs will get quashed in the process.
