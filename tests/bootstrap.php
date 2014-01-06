<?php

echo "RUNNING (". __FILE__ .")!!!!\n";
require_once(dirname(__FILE__) .'/../AutoLoader.class.php');

// set a constant for testing...
define('UNITTEST__LOCKFILE', dirname(__FILE__) .'/files/rw/');
define('cs_lockfile-RWDIR', constant('UNITTEST__LOCKFILE'));

AutoLoader::registerDirectory(dirname(__FILE__) .'/../');
