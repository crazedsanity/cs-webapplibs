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

class testOfCSWebAppLibs extends UnitTestCase {
	
	//--------------------------------------------------------------------------
	function __construct() {
		$this->gfObj = new cs_globalFunctions;
		$this->gfObj->debugPrintOpt=1;
	}//end __construct()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	private function create_dbconn() {
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
	private function remove_tables() {
		$tableList = array(
			'cswal_auth_token_table', 'cswal_version_table', 'cswdbl_attribute_table', 
			'cswdbl_category_table', 'cswdbl_class_table', 'cswdbl_event_table', 
			'cswdbl_log_attribute_table', 'cswdbl_log_table', 
		);
		
		$db = $this->create_dbconn();
		foreach($tableList as $name) {
			try {
				$db->run_update("DROP TABLE ". $name ." CASCADE", true);
			}
			catch(exception $e) {
				//force an error.
				$this->assertTrue(false, "Error while dropping (". $name .")::: ". $e->getMessage());
			}
		}
	}//end remove_tables()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	function test_token_basics() {
		$db = $this->create_dbconn();
		$this->remove_tables();
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
		
		//create a token with only 1 available use and try to authenticate it twice.
		{
			//Generic test to ensure we get the appropriate data back.
			$tokenData = $tok->create_token(1, 'test', 'abc123', null, 1);
			$this->basic_token_tests($tokenData, 1, 'test');
			
			if(!$this->assertEqual($tok->authenticate_token($tokenData['id'], 'test', $tokenData['hash']), 1)) {
				$this->gfObj->debug_print($tok->tokenData($tokenData['id']),1);
			}
			if(!$this->assertTrue(($tok->authenticate_token($tokenData['id'], 'test', $tokenData['hash']) === null), "Able to authenticate twice on a token with only 1 use")) {
				$this->gfObj->debug_print($tok->tokenData($tokenData['id']));
			}
		}
		
		
		//now create a token with a maximum lifetime...
		{
			//Generic test to ensure we get the appropriate data back.
			$tokenData = $tok->create_token(1, 'test', 'abc123', '2 years');
			$this->basic_token_tests($tokenData, 1, 'test');
			
			$this->assertEqual($tok->authenticate_token($tokenData['id'], 'test', $tokenData['hash']), 1);
		}
		
		//try to create a token with max_uses of 0.
		{
			$tokenData = $tok->create_token(2, 'test', 'xxxxyyyyyxxxx', null, 0);
			$this->basic_token_tests($tokenData, 2, 'test');
			$checkData = $tok->tokenData($tokenData['id']);
			$checkData = $checkData[$tokenData['id']];
			
			$this->assertTrue(is_array($checkData));
			$this->assertEqual($tokenData['id'], $checkData['auth_token_id']);
			$this->assertEqual($checkData['max_uses'], null);
		}
		
		//try creating a token that is purposely expired, make sure it exists, then make sure authentication fails.
		{
			$tokenData = $tok->create_token(88, 'test', 'This is a big old TEST', '-3 days');
			if($this->assertTrue(is_array($tokenData))) {
				$this->basic_token_tests($tokenData, 88, 'test');
				$this->assertFalse($tok->authenticate_token($tokenData['id'], 'test', $tokenData['hash']));
			}
		}
	}//end test_token_basics()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	private function basic_token_tests(array $tokenData, $uid, $checksum) {
		
		if($this->assertTrue(is_array($tokenData)) && $this->assertTrue(is_numeric($uid)) && $this->assertTrue(strlen($checksum))) {
			
			$this->assertTrue(is_array($tokenData));
			$this->assertTrue((count($tokenData) == 2));
			$this->assertTrue(isset($tokenData['id']));
			$this->assertTrue(isset($tokenData['hash']));
			$this->assertTrue(($tokenData['id'] > 0));
			$this->assertTrue((strlen($tokenData['hash']) == 32));
		}
		
	}//end basic_token_tests()
	//--------------------------------------------------------------------------
}


class authTokenTester extends cs_authToken {
	public $isTest=true;
	
	public function tokenData($id) {
		return($this->get_token_data($id));
	}
}
?>
