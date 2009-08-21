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
			'cswal_auth_token_table', 'cswal_version_table', 'cswal_attribute_table', 
			'cswal_category_table', 'cswal_class_table', 'cswal_event_table', 
			'cswal_log_attribute_table', 'cswal_log_table', 'cswal_session_store_table'
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
		{
			$tokenData = $tok->create_token(1, 'test', 'abc123');
			$this->do_tokenTest($tokenData, 1, 'test');
			
			$this->assertEqual($tok->authenticate_token($tokenData['id'], 'test', $tokenData['hash']), 1);
			$this->assertFalse($tok->authenticate_token($tokenData['id'], 'testx', $tokenData['hash']));
			$this->assertFalse($tok->authenticate_token($tokenData['id'], 'test', 'abcdefg'));
			$this->assertFalse($tok->authenticate_token($tokenData['id'], 'test', '12345678901234567890123456789012'));
			$this->assertFalse($tok->authenticate_token(99999, 'test', '12345678901234567890123456789012'));
			
			//check to make sure the data within this token shows only ONE attempt.
			$checkData = $tok->tokenData($tokenData['id']);
			$this->assertEqual($checkData['auth_token_id'], $tokenData['id']);
			$this->assertEqual($checkData['total_uses'], 1);
		}
		
		//create a token with only 1 available use and try to authenticate it twice.
		{
			//Generic test to ensure we get the appropriate data back.
			$tokenData = $tok->create_token(1, 'test', 'abc123', null, 1);
			$this->do_tokenTest($tokenData, 1, 'test');
			
			$this->assertEqual($tok->authenticate_token($tokenData['id'], 'test', $tokenData['hash']), 1);
			$this->assertTrue(($tok->authenticate_token($tokenData['id'], 'test', $tokenData['hash']) === null), 
					"Able to authenticate twice on a token with only 1 use");
			$this->assertFalse($tok->tokenData($tokenData['id'], true));
			$this->assertFalse($tok->tokenData($tokenData['id'], false));
		}
		
		
		//now create a token with a maximum lifetime (make sure we can call it a ton of times)
		{
			//Generic test to ensure we get the appropriate data back.
			$tokenData = $tok->create_token(1, 'test', 'abc123', '2 years');
			$this->do_tokenTest($tokenData, 1, 'test');
			
			$this->assertEqual($tok->authenticate_token($tokenData['id'], 'test', $tokenData['hash']), 1);
			$checkAttempts = 100;
			$successAttempts = 0;
			for($i=0; $i < 100; $i++) {
				$id = $tok->authenticate_token($tokenData['id'], 'test', $tokenData['hash']);
				if($this->assertEqual($tok->authenticate_token($tokenData['id'], 'test', $tokenData['hash']), 1)) {
					$successAttempts++;
				}
			}
			$this->assertEqual($checkAttempts, $successAttempts);
		}
		
		//try to create a token with max_uses of 0.
		{
			$tokenData = $tok->create_token(2, 'test', 'xxxxyyyyyxxxx', null, 0);
			$this->do_tokenTest($tokenData, 2, 'test');
			$checkData = $tok->tokenData($tokenData['id']);
			
			$this->assertTrue(is_array($checkData));
			$this->assertEqual($tokenData['id'], $checkData['auth_token_id']);
			$this->assertEqual($checkData['max_uses'], null);
		}
		
		//try creating a token that is purposely expired, make sure it exists, then make sure authentication fails.
		{
			$tokenData = $tok->create_token(88, 'test', 'This is a big old TEST', '-3 days');
			if($this->assertTrue(is_array($tokenData))) {
				$this->do_tokenTest($tokenData, 88, 'test');
				$this->assertFalse($tok->authenticate_token($tokenData['id'], 'test', $tokenData['hash']));
			}
		}
		
		//make sure we don't get the same hash when creating multiple tokens with the same data.
		//NOTE: this pushes the number of tests up pretty high, but I think it is required to help ensure hash uniqueness.
		{
			$uid=rand(1,999999);
			$checksum = 'multiple ToKEN check';
			$hashThis = "Lorem ipsum dolor sit amet. ";
			
			$numTests = 30;
			$numPass = 0;
			$tokenList = array();
			for($i=0;$i<$numTests;$i++) {
				$tokenList[$i] = $tok->create_token($uid, $checksum, $hashThis);
			}
			$lastItem = ($numTests -1);
			for($i=0;$i<$numTests;$i++) {
				$checkHash = $tokenList[$i]['hash'];
				$uniq=0;
				foreach($tokenList as $k=>$a) {
					//check against everything BUT itself.
					if($i != $k && $this->assertNotEqual($checkHash, $a['hash'])) {
						$uniq++;
					}
				}
				$this->assertEqual($uniq, ($numTests -1));
			}
		}
		
		//make sure the hash string isn't guessable, even if they can access our super-secret encryption algorithm. ;)
		{
			$uid = rand(1,99999);
			$checksum = "my birfday";
			$hashThis = "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Quisque ut.";
			
			$tokenData = $tok->create_token($uid, $checksum, $hashThis);
			$this->do_tokenTest($tokenData, $uid, $checksum);
			
			$this->assertNotEqual($tokenData['hash'], $tok->doHash($tokenData['id'], $uid, $checksum, $hashThis), 
					"hash is guessable");
		}
		
		//test expiring tokens...
		{
			//create a token that is immediately expired.
			$tokenData = $tok->create_token(22, 'token expiration test', 'Lorem ipsum dolor sit amet, consectetur.', '-5 days');
			$this->do_tokenTest($tokenData, 22, 'token expiration test');
			
			$this->assertFalse(is_array($tok->tokenData($tokenData['id'], true)));
			$this->assertTrue(is_array($tok->tokenData($tokenData['id'], false)));
			$this->assertTrue(count($tok->tokenData($tokenData['id'],false)) == 9);
			
			//REMEMBER: we've created other tokens that will now expire...
			$removedTokens = $tok->remove_expired_tokens();
			$this->assertEqual(2, $removedTokens);
		}
		
	}//end test_token_basics()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	private function do_tokenTest(array $tokenData, $uid, $checksum) {
		
		if($this->assertTrue(is_array($tokenData)) && $this->assertTrue(is_numeric($uid)) && $this->assertTrue(strlen($checksum))) {
			
			$this->assertTrue(is_array($tokenData));
			$this->assertTrue((count($tokenData) == 2));
			$this->assertTrue(isset($tokenData['id']));
			$this->assertTrue(isset($tokenData['hash']));
			$this->assertTrue(($tokenData['id'] > 0));
			$this->assertTrue((strlen($tokenData['hash']) == 40));
		}
		
	}//end do_tokenTest()
	//--------------------------------------------------------------------------
}


class authTokenTester extends cs_authToken {
	public $isTest=true;
	
	public function tokenData($id, $onlyNonExpired=true) {
		return($this->get_token_data($id, $onlyNonExpired));
	}
	public function doHash($tokenId, $uid, $checksum, $hash) {
		return($this->create_hash_string($tokenId, $uid, $checksum, $hash));
	}
}
?>
