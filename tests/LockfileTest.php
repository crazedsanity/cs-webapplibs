<?php

class LockfileTest extends PHPUnit_Framework_TestCase {
	public $fs;
	public $dir;
	
	//-------------------------------------------------------------------------
	public function __construct() {
		$this->dir = dirname(__FILE__) .'/files/rw';
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function setUp() {
	}//end setUp()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function tearDown() {
	}//end tearDown()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * @expectedException InvalidArgumentException
	 */
	public function test_noArgument() {
		new _test_csLockfile_noArg();
	}
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function test_basics() {
		$this->assertTrue(is_dir($this->dir));
		
		
		//tests based on using a specified lockfile
		{
			$myFile = __CLASS__ .'-test.lock';
			$myTestContents = '('. __FILE__ .') '. __METHOD__ .': line #'. __LINE__ .': This is a test... '. microtime(true);

			$lf = new _test_csLockfile($this->dir, $myFile);
			$this->assertEquals($myFile, $lf->lockFile);
			$this->assertFalse(file_exists($myFile));

			$lf->create_lockfile($myTestContents);
			$this->assertEquals($this->dir .'/'. $myFile, $lf->get_lockfile(), cs_global::debug_print($lf,0));
			$this->assertTrue(file_exists($myFile));
			$this->assertEquals(file_get_contents($myFile), $myTestContents);
			$this->assertEquals(file_get_contents($myFile), $lf->read_lockfile());
			$this->assertNotEquals(file_get_contents($myFile), $myTestContents .' ');


			$lf->delete_lockfile();
			$this->assertFalse(file_exists($myFile));
		}
		
		// test concurrent lockfiles
		{
			$numLocks = 10;
			
			$locks = array();
			for($x=0; $x< $numLocks; $x++) {
				$locks[$x] = new _test_csLockfile($this->dir, $x .'.lock');
			}
			
			$this->assertEquals(count($locks), $numLocks);
			
			
			foreach($locks as $i => $lock) {
				$this->assertFalse(file_exists($this->dir .'/'. $i .'.lock'));
				$this->assertTrue((bool)$lock->create_lockfile('test... '. $i));
				
				
				$this->assertEquals(
						basename($this->dir .'/'. $i .'.lock'), 
						basename($lock->get_lockfile())
					);
				
				$this->assertTrue($lock->delete_lockfile());
				$this->assertTrue((bool)strlen($lock->get_lockfile()));
				$this->assertFalse(file_exists($lock->get_lockfile()));
			}
		}
	}//end test_basics()
	//-------------------------------------------------------------------------
	
	
	//-------------------------------------------------------------------------
	/**
	 * @expectedException ErrorException
	 */
	public function xtest_badRwDir() {
		$lock = new _test_csLockfile2();
		$lock->rwDir = '/__bad__/__path__';
		$lock->get_rwdir();
	}//end test_badRwDir()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function xtest_lockExists() {
		$dir = dirname(__FILE__) .'/files/rw/';
		$file = __FUNCTION__ .'.lock';
		$firstLock = new cs_lockfile($dir, $file);
		$secondLock = new cs_lockfile($dir, $file);
		
		$this->assertFalse(file_exists($file));
		$this->assertFalse($firstLock->is_lockfile_present());
		$this->assertFalse($secondLock->is_lockfile_present());
		$this->assertTrue((bool)$firstLock->create_lockfile());
		$this->assertTrue(file_exists($file));
		$this->assertTrue($firstLock->is_lockfile_present());
		$this->assertTrue($secondLock->is_lockfile_present());
		
		$this->assertTrue($secondLock->delete_lockfile());
		$this->assertFalse($firstLock->delete_lockfile());
	}//end test_lockExists()
	//-------------------------------------------------------------------------
	
	
	
	
	//-------------------------------------------------------------------------
	public function xtest_rwDir() {
		$rwDir = dirname(__FILE__) .'/files/rw';
		$x = new _test_csLockfile($rwDir, 'test.lock');
		
		$this->assertEquals($rwDir, $x->rwDir);
		
		$newRwDir = dirname(__FILE__) .'/files';
		$newX = new _test_csLockfile($newRwDir, 'test2.lock');
		
		$this->assertEquals($newRwDir, $newX->rwDir);
	}
	//-------------------------------------------------------------------------
}


class _test_csLockfile extends cs_lockfile {
	public function __construct($path, $lockFile='aaaaaa.lock') {
		parent::__construct($path, $lockFile);
	}
	
	public function __get($name) {
		return($this->$name);
	}
	public function __set($name, $value) {
		$this->$name = $value;
	}
}

class _test_csLockfile2 extends _test_csLockfile {
	public function __construct() {
		//don't call the parent!
	}
}

class _test_csLockfile_noArg extends cs_lockfile {
	public function __construct($path=null, $file=null) {
		parent::__construct(null, null);
	}
}
