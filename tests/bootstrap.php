<?php

require_once(dirname(__FILE__) .'/../AutoLoader.class.php');
require_once(dirname(__FILE__) .'/../debugFunctions.php');

// Handle password compatibility (using "ircmaxell/password-compat")
{
	//handle differences in paths...
	$compatPath = '/../../ircmaxell/password-compat/version-test.php';
	$usePath = dirname(__FILE__) . $compatPath;
	if(!file_exists($usePath)) {
		$compatPath = '/..'. $compatPath;
		$usePath = dirname(__FILE__) . $compatPath;
	}
	
	ob_start();
	if(!include_once($usePath)) {
		ob_end_flush();
		die("You must set up the project dependencies, run the following commands:\n
			\twget http://getcomposer.org/composer.phar
			\tphp composer.phar install ircmaxell/password-compat\n");
	}
	else {
		$output = ob_get_contents();
		ob_end_clean();
		
		if(preg_match('/Pass/', $output)) {
			require_once(dirname($usePath) .'/lib/password.php');
		}
	}
}

// set a constant for testing...
define('UNITTEST__LOCKFILE', dirname(__FILE__) .'/files/rw/');
define('cs_lockfile-RWDIR', constant('UNITTEST__LOCKFILE'));
define('RWDIR', constant('UNITTEST__LOCKFILE'));
define('LIBDIR', dirname(__FILE__) .'/..');
define('UNITTEST_ACTIVE', 1);

// set the timezone to avoid spurious errors from PHP
date_default_timezone_set("America/Chicago");

AutoLoader::registerDirectory(dirname(__FILE__) .'/../');





error_reporting(E_ALL);
