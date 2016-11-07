<?php


class RegisterUserTest extends crazedsanity\database\TestDbAbstract {
	
	//--------------------------------------------------------------------------
	function __construct() {
		parent::__construct();
	}//end __construct()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	function setUp() {
		$this->reset_db(dirname(__FILE__) .'/../setup/schema.pgsql.sql');
		parent::setUp();
	}//end setUp()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function tearDown() {
		parent::tearDown();
	}//end tearDown()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function testRegistration() {
		$regUser = new cs_registerUser($this->dbObj);
		$this->assertTrue(is_object($regUser));
//		$this->assertEquals($this->dbObj, $regUser->dbObj);
		
	}
	//--------------------------------------------------------------------------

}
