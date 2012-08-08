<?php
/*
 * Created on Jan 25, 2009
 * 
 * This should be in a "public" folder (probably secured by the server) so it 
 * can be accessed in a web browser.
 * 
 */


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
?>
