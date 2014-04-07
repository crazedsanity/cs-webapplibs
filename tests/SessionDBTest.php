<?php

class testOfCSSessionDB extends testDbAbstract {
	
	public function __construct() {
		parent::__construct();
	}
	
	
	public function setUp() {
		$this->reset_db(dirname(__FILE__) .'/../setup/schema.pgsql.sql');
		parent::setUp();
	}
	
	
	public function tearDown() {
		//TODO: figure out why the schema schema disappears when this is around...
//		parent::tearDown();
	}
	
	public function test_basics() {
		$x = new sessionTester($this->dbObj);
		
		$this->assertTrue($x->sessdb_table_exists());
		
		//make sure that "sessdb_open()" doesn't actually do anything.
		{
			$copy = clone $x;
			$this->assertEquals($x, $copy);
			$copy->lastException = "This should NOT affect the original";
			$this->assertNotEquals($x, $copy);
			$copy->lastException = null;
			
			$_SESSION = array();

			$sessionCopy = $_SESSION;
			$this->assertTrue($x->sessdb_open());
			$this->assertEquals($x, $copy);
			$this->assertEquals($_SESSION, $sessionCopy);
			$this->assertTrue($x->sessdb_open('stuff'));
			$this->assertEquals($x, $copy);
			$this->assertEquals($_SESSION, $sessionCopy);
			$this->assertTrue($x->sessdb_open(null, 'stuff'));
			$this->assertEquals($x, $copy);
			$this->assertEquals($_SESSION, $sessionCopy);
			$this->assertTrue($x->sessdb_open('stuff', 'stuf2'));
			$this->assertEquals($x, $copy);
			$this->assertEquals($_SESSION, $sessionCopy);
			$this->assertTrue($x->sessdb_open(null, null));
			$this->assertEquals($x, $copy);
			$this->assertEquals($_SESSION, $sessionCopy);
		}
		
		$testSessions = array(
			'__TEST_SESSDB__'	=> array('the first session'),
			__FUNCTION__		=> array('another', 'x'=>'y'),
			md5(__FUNCTION__)	=> array('uses md5 as the name'),
			sha1(__FUNCTION__)	=> array('fourth, uses sha1'),
		);
		
		foreach($testSessions as $sid=>$data) {
			$this->assertEquals(null, $x->lastException);
			
			$this->assertEquals($sid, $x->doInsert($sid, $data), $x->lastException);
			$this->assertEquals(null, $x->lastException);
			
			$this->assertEquals($data, unserialize($x->sessdb_read($sid)));
			$this->assertEquals(null, $x->lastException);
			
			$this->assertEquals(0, $x->sessdb_gc(), $x->lastException);
			$this->assertEquals(0, $x->sessdb_close(), $x->lastException);
			
			$this->assertTrue($x->is_valid_sid($sid));
			
			//make sure that $_SESSION remains unchanged.
			$this->assertEquals($_SESSION, $sessionCopy);
			
			$data['update'] = $sid;
			$this->assertEquals(1, $x->doUpdate($sid, serialize($data)));
			$this->assertEquals($data, unserialize($x->sessdb_read($sid)));
			$testSessions[$sid] = $data;
			
			//do an update without any data, and make sure the session's data didn't get wiped.
			$this->assertEquals(1, $x->doUpdate($sid, null));
			$this->assertEquals($data, unserialize($x->sessdb_read($sid)));
		}
		
		$this->assertEquals(null, $x->lastException);
		$this->assertEquals(count($testSessions), $x->get_recently_active_sessions());
		$this->assertEquals(null, $x->lastException);
		
	}
}


class sessionTester extends cs_sessionDB {
	public function __get($name) {
		return ($this->$name);
	}
	
	public function __set($name, $value) {
		$this->$name = $value;
	}
	
	public function __construct(cs_phpdb $db) {
		$this->db = $db;
		parent::__construct(false, $db);
	}
	
	public function connectDb($dsn=null,$user=null,$password=null) {
		return(parent::connectDb($dsn,$user,$password));
	}
	
	
	public function is_valid_sid($sid) {
		return(parent::is_valid_sid($sid));
	}
	
	public function doInsert($sid, $data, $uid=NULL) {
		return(parent::doInsert($sid, $data, $uid));
	}
	
	public function doUpdate($sid, $data, $uid=NULL) {
		return(parent::doUpdate($sid, $data, $uid));
	}
}
