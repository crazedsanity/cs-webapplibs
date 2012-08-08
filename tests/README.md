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

Get a copy of SimpleTest or something compatible, then run all the tests, like:

<pre>
	<?php
	print "<pre>";
	$GLOBALS['DEBUGPRINTOPT'] = 1;
	define('DEBUGPRINTOPT', 1);	


	if (defined('SIMPLE_TEST')) {
		require_once(SIMPLE_TEST . 'unit_tester.php');
		require_once(SIMPLE_TEST . 'reporter.php');
	print "</pre>";

		$lockObj = new cs_lockfile();
		cs_lockfile::$lockfile = "unittester.lock";
		if(!$lockObj->is_lockfile_present()) {
			$lockObj->create_lockfile("Full suite of tests, started from ". __FILE__);

			require_once(constant('LIBDIR') .'/cs-phpxml/tests/testOfA2P.php');
			require_once(constant('LIBDIR') .'/cs-content/tests/testOfCSGlobalFunctions.php');
			require_once(constant('LIBDIR') .'/cs-content/tests/testOfCSContent.php');
			require_once(constant('LIBDIR') .'/cs-content/tests/testOfCSVersionAbstract.php');
			require_once(constant('LIBDIR') .'/cs-phpxml/tests/testOfCSPHPXML.php');
			#require_once(constant('LIBDIR') .'/cs-rssdb/tests/testOfCSRSSDB.php');

			require_once(constant('LIBDIR') .'/cs-webapplibs/tests/testOfCSPHPDB.php');
			require_once(constant('LIBDIR') .'/cs-webapplibs/tests/testOfCSWebDBUpgrade.php');
			require_once(constant('LIBDIR') .'/cs-webapplibs/tests/testOfCSSessionDB.php');
			#require_once(constant('LIBDIR') .'/cs-webapplibs/tests/testOfCSAuthToken.php');
			#require_once(constant('LIBDIR') .'/cs-webapplibs/tests/testOfCSGenericPermissions.php');
			#require_once(constant('LIBDIR') .'/cs-webapplibs/tests/testOfCSGenericChat.php');
			#require_once(constant('LIBDIR') .'/cs-blogger/tests/testOfCSBlogger.php');
			#require_once(constant('LIBDIR') .'/cs-battletrack/tests/testOfCSBattleTrack.php');

			$test = new TestSuite('All Unit Tests');

			$test->addTestCase(new testOfA2p());
			$test->addTestCase(new testOfCSGlobalFunctions());
			#$test->addTestCase(new testOfCSContent());
			$test->addTestCase(new testOfCSPHPXML());
			#$test->addTestCase(new testOfCSRSSDB());
			$test->addTestCase(new testOfCSPHPDB());
			$test->addTestCase(new testOfCSWebDbUpgrade());
			$test->addTestCase(new testOfCSSessionDB());
			#$test->addTestCase(new testOfCSBlogger());
			#$test->addTestCase(new testOfCSVersionAbstract());
			#$test->addTestCase(new testOfCSAuthToken());
			#$test->addTestCase(new testOfCSGenericPermissions());
			#$test->addTestCase(new testOfCSGenericChat());
			#$test->addTestCase(new testOfCSBattleTrack());

			$test->run(new HtmlReporter());

			$lockObj->delete_lockfile();
		}
		else {
			exit("Another test is running, remove lockfile (". $lockObj->get_lockfile());
		}
	}
	else {
		exit("SITE NOT CONFIGURED TO PERFORM TESTING. No tests performed.");
	}
</pre>