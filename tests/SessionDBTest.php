<?php

class testOfCSSessionDB extends testDbAbstract {
	
	public function __construct() {
		parent::__construct();
	}
	
	
	public function setUp() {
		parent::setUp();
//		$this->dbObj->beginTrans();
		
	}
	
	
	public function tearDown() {
		parent::tearDown();
	}
	
	//TODO: umm... actually perform tests...?
	/**
	 * @covers cs_sessionDB::doInsert
	 * @covers cs_sessionDB::__construct
	 * @covers cs_sessionDB::connectDb
	 * @covers cs_sessionDb::exception_handler
	 */
	public function test_basics() {
		$this->reset_db(dirname(__FILE__) .'/../setup/schema.pgsql.sql');
		#$this->assertTrue(defined('SESSION_DB_DSN'), 'Missing DSN for SessionDB');
		#$this->assertTrue(defined('SESSION_DB_USER'), 'Missing user for SessionDB');
		#$this->assertTrue(defined('SESSION_DB_PASSWORD'), 'Missing password for SessionDB');
//		$this->assertTrue(is_object($this->dbObj));
//		
		$sessDB = new sessionTester($this->dbObj);
//		
		$mySid = '__TEST_SESSDB__';
		$sessDB->doInsert($mySid, array());
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
		#$this->db = $this->connectDb($db);
		$this->db = $db;
	}
	
	public function connectDb($dsn=null,$user=null,$password=null) {
		return(parent::connectDb($dsn,$user,$password));
	}
	
	
	public function is_valid_sid($sid) {
		return(parent::is_valid_sid($sid));
	}
	
	public function doInsert($sid, $data) {
		return(parent::doInsert($sid, $data));
	}
	
	public function doUpdate($sid, $data) {
		return(parent::doUpdate($sid, $data));
	}
}
