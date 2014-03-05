<?php

require_once(dirname(__FILE__) .'/../AutoLoader.class.php');
require_once(dirname(__FILE__) .'/../debugFunctions.php');

// Handle password compatibility (using "ircmaxell/password-compat")
{
	ob_start();
	if(!include_once(dirname(__FILE__) .'/../../../ircmaxell/password-compat/version-test.php')) {
		ob_end_flush();
		die("You must set up the project dependencies, run the following commands:\n
			\twget http://getcomposer.org/composer.phar
			\tphp composer.phar install");
	}
	else {
		$output = ob_get_contents();
		ob_end_clean();
		
		if(preg_match('/Pass/', $output)) {
			require_once(dirname(__FILE__) .'/../../../ircmaxell/password-compat/lib/password.php');
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
