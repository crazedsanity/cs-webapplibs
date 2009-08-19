<?php
/*
 * Created on Jan 25, 2009
 * 
 * FILE INFORMATION:
 * 
 * $HeadURL$
 * $Id$
 * $LastChangedDate$
 * $LastChangedBy$
 * $LastChangedRevision$
 */

require_once(dirname(__FILE__) .'/../cs_authToken.class.php');

class testOfCSAuthToken extends UnitTestCase {
	
	//--------------------------------------------------------------------------
	function __construct() {
		$this->gfObj = new cs_globalFunctions;
		$this->gfObj->debugPrintOpt=1;
	}//end __construct()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	function setUp() {
		$tok = new cs_authToken($this->create_db());
		try {
			$tok->load_table();
		}
		catch(exception $e) {
		}
	}//end setUp()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	function tearDown() {
		$db = $this->create_db();
		try {
			$db->run_update('DROP TABLE cswal_auth_token_table', true);
		}
		catch(exception $e) {
		}
	}//end
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	private function create_db() {
		$dbParams = array(
			'host'		=> constant('DB_PG_HOST'),
			'dbname'	=> constant('DB_PG_DBNAME'),
			'user'		=> constant('DB_PG_DBUSER'),
			'password'	=> constant('DB_PG_DBPASS'),
			'port'		=> constant('DB_PG_PORT')
		);
		$db = new cs_phpDB(constant('DBTYPE'));
		$db->connect($dbParams);
		return($db);
	}//end create_db()
	//--------------------------------------------------------------------------
	
	
	//--------------------------------------------------------------------------
	function test_basics() {
		$db = $this->create_db();
		$tok = new authTokenTester($db);
		
		//Generic test to ensure we get the appropriate data back.
		$tokenData = $tok->create_token(1, 'test', 'abc123');
		$this->assertTrue(is_array($tokenData));
		$this->assertTrue((count($tokenData) == 2));
		$this->assertTrue(isset($tokenData['id']));
		$this->assertTrue(isset($tokenData['hash']));
		$this->assertTrue(($tokenData['id'] > 0));
		$this->assertTrue((strlen($tokenData['hash']) == 32));
		
		$this->assertEqual($tok->authenticate_token($tokenData['id'], 'test', $tokenData['hash']), 1);
		
		
		//now create a token with a maximum lifetime...
		{
			//Generic test to ensure we get the appropriate data back.
			$tokenData = $tok->create_token(1, 'test', 'abc123', '2 years');
			$this->assertTrue(is_array($tokenData));
			$this->assertTrue((count($tokenData) == 2));
			$this->assertTrue(isset($tokenData['id']));
			$this->assertTrue(isset($tokenData['hash']));
			$this->assertTrue(($tokenData['id'] > 0));
			$this->assertTrue((strlen($tokenData['hash']) == 32));
			
			$this->assertEqual($tok->authenticate_token($tokenData['id'], 'test', $tokenData['hash']), 1);
		}
		
		//create a token with only 1 available use and try to authenticate it twice.
		{
			//Generic test to ensure we get the appropriate data back.
			$tokenData = $tok->create_token(1, 'test', 'abc123', null, 1);
			$this->assertTrue(is_array($tokenData));
			$this->assertTrue((count($tokenData) == 2));
			$this->assertTrue(isset($tokenData['id']));
			$this->assertTrue(isset($tokenData['hash']));
			$this->assertTrue(($tokenData['id'] > 0));
			$this->assertTrue((strlen($tokenData['hash']) == 32));
			
			if(!$this->assertEqual($tok->authenticate_token($tokenData['id'], 'test', $tokenData['hash']), 1)) {
				$this->gfObj->debug_print($tok->tokenData($tokenData['id']),1);
			}
			if(!$this->assertTrue(($tok->authenticate_token($tokenData['id'], 'test', $tokenData['hash']) === null), "Able to authenticate twice on a token with only 1 use")) {
				$this->gfObj->debug_print($tok->tokenData($tokenData['id']));
			}
		}
	}//end test_basics()
	//--------------------------------------------------------------------------
}

class authTokenTester extends cs_authToken {
	public $isTest=true;
	
	public function tokenData($id) {
		return($this->get_token_data($id));
	}
}

?>
