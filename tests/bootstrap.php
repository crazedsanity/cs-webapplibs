<?php

require_once(dirname(__FILE__) .'/../AutoLoader.class.php');
require_once(dirname(__FILE__) .'/../debugFunctions.php');

// set a constant for testing...
define('UNITTEST__LOCKFILE', dirname(__FILE__) .'/files/rw/');
define('cs_lockfile-RWDIR', constant('UNITTEST__LOCKFILE'));
define('RWDIR', constant('UNITTEST__LOCKFILE'));
define('LIBDIR', dirname(__FILE__) .'/..');
define('SITE_ROOT', dirname(__FILE__) .'/..');
define('UNITTEST_ACTIVE', 1);

// set the timezone to avoid spurious errors from PHP
date_default_timezone_set("America/Chicago");

AutoLoader::registerDirectory(dirname(__FILE__) .'/../');
