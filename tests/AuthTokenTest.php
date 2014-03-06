<?php
/*
 * Created on Jan 25, 2009
 */

class testOfCSAuthToken extends testDbAbstract {
	
	//--------------------------------------------------------------------------
	function __construct() {
		$this->gfObj = new cs_globalFunctions;
		$this->gfObj->debugPrintOpt=1;
		parent::__construct();
	}//end __construct()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	function setUp() {
		$this->gfObj = new cs_globalFunctions;
		$this->gfObj->debugPrintOpt=1;
		parent::setUp();
		$this->assertTrue($this->reset_db(dirname(__FILE__) .'/../setup/schema.pgsql.sql'), "Failed to reset database");
	}//end setUp()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function tearDown() {
		parent::tearDown();
	}//end tearDown()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function test_creation() {
		$x = new cs_authToken($this->dbObj);
//		$this->assertTrue(is_object($x));
		
		$name = '_ToKen';
		$value = __METHOD__;
		$pass = 'foo@bar.com';
		
		$this->assertEquals($name, $x->create_token($pass, $value, $name));
		
		
		$res = $x->remove_expired_tokens($name);
		
		$this->assertEquals($res['type'], $x::EXPIRE_SINGLE);
		$this->assertEquals($res['num'], 0);
		
		$expectedResult = array(
			'result'		=> true,
			'reason'		=> $x::RESULT_SUCCESS,
			'stored_value'	=> $value,
		);
		$actualResult = $x->authenticate_token($name, $pass);
		
		foreach($expectedResult as $k=>$v) {
			$this->assertTrue(isset($actualResult[$k]));
			$this->assertEquals($v, $actualResult[$k], "Value mismatch for '". $k ."', ACTUAL::: ". cs_global::debug_print($actualResult,0));
		}
	}
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	function XOLD_test_token_basics() {
		$dbObj = $this->dbObj;
		
		#$dbObj->beginTrans();
		$tok = new cs_authToken($dbObj);
//		if($this->assertTrue($this->reset_db(dirname(__FILE__) .'/../setup/schema.pgsql.sql'), "Failed to reset database")) {
			#$tok->load_schema($type, $dbObj);
			$tok = new cs_authToken($dbObj);
	
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
				$tokenData = $tok->create_token(2, 'test', 'This is a big old TEST', '-3 days');
				if($this->assertTrue(is_array($tokenData))) {
					$this->do_tokenTest($tokenData, 2, 'test');
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
//		}
		
	}//end test_token_basics()
	//--------------------------------------------------------------------------
	
	
}

?>
