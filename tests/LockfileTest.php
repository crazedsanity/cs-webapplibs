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
	public function test_basics() {
		$this->assertTrue(is_dir($this->dir));
		
		//test basics (no lockfile specified)
		{
			$this->assertEquals('upgrade.lock', cs_lockfile::defaultLockfile);
			$this->assertFalse(file_exists($this->dir .'/upgrade.lock'));
			
			$defaultLf = new _test_csLockfile();
			$this->assertEquals(
					file_exists($this->dir .'/'. cs_lockfile::defaultLockfile),
					$defaultLf->is_lockfile_present()
			);
			
			$this->assertEquals($this->dir .'/', $defaultLf->fsObj->realcwd);
			
			$this->assertTrue((bool)$defaultLf->create_lockfile(__CLASS__));
			$this->assertEquals(__CLASS__, $defaultLf->read_lockfile());
			$this->assertTrue($defaultLf->delete_lockfile());
			$this->assertEquals(
					file_exists($this->dir .'/'. cs_lockfile::defaultLockfile),
					$defaultLf->is_lockfile_present()
			);
			
			$defaultLf->delete_lockfile();
		}
		
		
		//tests based on using a specified lockfile
		{
			$myFile = __CLASS__ .'-test.lock';
			$myTestContents = 'This is a test... '. microtime(true);

			$lf = new _test_csLockfile($myFile);
			$this->assertFalse(file_exists($myFile));

			$lf->create_lockfile($myTestContents);
			$this->assertEquals(basename($myFile), basename($lf->get_lockfile()));
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
				$locks[$x] = new _test_csLockfile($this->dir .'/'. $x .'.lock');
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
	public function test_badRwDir() {
		$lock = new _test_csLockfile2();
		$lock->rwDir = '/__bad__/__path__';
		$lock->get_rwdir();
	}//end test_badRwDir()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function test_lockExists() {
		$file = dirname(__FILE__) .'/files/rw/'. __FUNCTION__ .'.lock';
		$firstLock = new cs_lockfile($file);
		$secondLock = new cs_lockfile($file);
		
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
}


class _test_csLockfile extends cs_lockfile {
	public function __construct($lockFile=null) {
		parent::__construct($lockFile);
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
