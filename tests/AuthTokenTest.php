<?php

/*
 * Created on Jan 25, 2009
 */

class testOfCSAuthToken extends testDbAbstract {

	function __construct() {
		$this->gfObj = new cs_globalFunctions;
		$this->gfObj->debugPrintOpt = 1;
		parent::__construct();
	}
	
	
	
	function setUp() {
		$this->gfObj = new cs_globalFunctions;
		$this->gfObj->debugPrintOpt = 1;
		parent::setUp();
		$this->assertTrue($this->reset_db(dirname(__FILE__) . '/../setup/schema.pgsql.sql'), "Failed to reset database");
	}
	
	
	
	public function tearDown() {
		parent::tearDown();
	}
	
	
	
	public function test_creation() {
		$x = new cs_authToken($this->dbObj);

		$name = '_ToKen';
		$value = __METHOD__;
		$pass = 'foo@bar.com';

		$this->assertEquals($name, $x->create_token($pass, $value, $name));


		$res = $x->remove_expired_tokens($name);

		$this->assertEquals($res['type'], $x::EXPIRE_SINGLE);
		$this->assertEquals($res['num'], 0);

		$expectedResult = array(
			'result' => true,
			'reason' => $x::RESULT_SUCCESS,
			'stored_value' => $value,
		);
		$actualResult = $x->authenticate_token($name, $pass);

		foreach ($expectedResult as $k => $v) {
			$this->assertTrue(isset($actualResult[$k]));
			$this->assertEquals($v, $actualResult[$k], "Value mismatch for '" . $k . "', ACTUAL::: " . cs_global::debug_print($actualResult, 0));
		}
	}
	
	
	
	public function test_single_use_with_second_auth_attempt() {
		$x = new cs_authToken($this->dbObj);
		$pass = 'test123';
		
		$id = $x->create_token('test123', null, null, null, 1);
		
		$this->assertTrue(is_string($id));
		$this->assertTrue(strlen($id) == 40);
		
		$return = $x->authenticate_token($id, $pass);
		
		$this->assertTrue(is_array($return));
		$this->assertTrue($return['result']);
		
		
		$secondReturn = $x->authenticate_token($id, $pass);
		$this->assertTrue(is_array($secondReturn));
		$this->assertEquals(false, $secondReturn['result']);
		
		$this->assertEquals($x::RESULT_FAIL, $secondReturn['reason']);
	}
	
	
	
	public function test_single_use_with_retrieval() {
		$x = new cs_authToken($this->dbObj);
		
		$id = $x->create_token('test123', null, null, null, 1);
		$firstData = $x->get_token_data($id);
		$this->assertEquals(0, $firstData['total_uses']);
		$this->assertEquals(1, $firstData['max_uses']);
		
		$res = $x->authenticate_token($id, 'test123');
		$this->assertEquals(true, $res['result']);
		$this->assertEquals($x::RESULT_SUCCESS, $res['reason']);
		
		$this->assertEquals(false, $x->get_token_data($id));
		
		$secondRes = $x->authenticate_token($id, 'test123');
		$this->assertEquals($x::RESULT_FAIL, $secondRes['reason']);
		$this->assertEquals(false, $secondRes['result']);
	}
	
	
	
	public function xtest_limited_uses() {
		$x = new cs_authToken($this->dbObj);
		
		$pass = 'test1234';
		$maxUses = 10;
		
		
		$id = $x->create_token($pass, __METHOD__, null, null, $maxUses);
		
		for($i=1; $i <= $maxUses; $i++) {
			$data = $x->get_token_data($id);
			
			$this->assertEquals(($i - 1), $data['total_uses'], "total uses was not updated, expected $i, found (". $data['total_uses'] .")");
			$this->assertEquals($id, $data['auth_token_id']);
			$this->assertEquals(null, $data['expiration']);
			
			
			$res = $x->authenticate_token($id, $pass);
			
			$this->assertEquals(true, $res['result']);
			$this->assertEquals($x::RESULT_SUCCESS, $res['reason']);
			$this->assertEquals(__METHOD__, $res['stored_value']);
			$this->assertEquals(true, $res['result']);
		}
		
		$finalData = $x->get_token_data($id);
		$this->assertFalse(is_array($finalData));
		$this->assertEquals(false, $finalData);
		
		$badAuth = $x->authenticate_token($id, $pass);
		
		$this->assertEquals(false, $badAuth['result']);
		$this->assertEquals($x::RESULT_FAIL, $badAuth['reason']);
		$this->assertFalse(isset($badAuth['stored_value']));
	}
	
	
	public function test_expiration() {
		$x = new cs_authToken($this->dbObj);
		
		$id = $x->create_token('test123', __METHOD__, null, '-1 day');
		
		$res = $x->remove_expired_tokens($id);
		
		$this->assertEquals(false, $x->get_token_data($id));
		$this->assertEquals($x::EXPIRE_SINGLE, $res['type']);
		$this->assertEquals(1, $res['num']);
		
		$xRes = $x->authenticate_token($id, 'test123');
		
		$this->assertEquals(false, $xRes['result']);
		$this->assertEquals($x::RESULT_FAIL, $xRes['reason']);
		$this->assertFalse(isset($xRes['stored_value']));
	}
	
	
	
	public function test_pre_expired() {
		$x = new cs_authToken($this->dbObj);
		
		$pass = 'test21123#@)$!';
		$id = $x->create_token($pass, __METHOD__, null, '-1 second');
		
		$tokenData = $x->get_token_data($id);
		$this->assertTrue(strlen($tokenData['expiration']) > 0, "invalid expiration (". $tokenData['expiration'] .")");
		$this->assertTrue(is_array($tokenData));
		
		$res = $x->authenticate_token($id, $pass);
		$this->assertEquals(true, is_array($res));
		$this->assertEquals($x::RESULT_EXPIRED, $res['reason']);
	}
	
	
	
	public function test_unlimited_uses() {
		$x = new cs_authToken($this->dbObj);
		
		$pass = 'test123';
		$id = $x->create_token($pass, __METHOD__, null, null, 0);
		
		$data = $x->get_token_data($id);
		
		$this->assertEquals(0, $data['max_uses']);
		$this->assertEquals(null, $data['expiration']);
		
		for($i=1; $i <= 10; $i++) {
			$auth = $x->authenticate_token($id, $pass);
			
			$this->assertEquals(true, $auth['result']);
			$this->assertEquals(__METHOD__, $auth['stored_value']);
			$this->assertEquals($x::RESULT_SUCCESS, $auth['reason']);
			
			$data = $x->get_token_data($id);
			
			$this->assertEquals($i, $data['total_uses']);
			$this->assertEquals(0, $data['max_uses']);
			$this->assertEquals(null, $data['expiration']);
			
		}
	}
	
	
	
	public function test_destroy_token() {
		$x = new cs_authToken($this->dbObj);
		
		$id = $x->create_token('test123');
		
		$data = $x->get_token_data($id);
		
		$this->assertEquals(1, $data['max_uses']);
		$this->assertEquals(null, $data['expiration']);
		
		$this->assertEquals(1, $x->destroy_token($id));
		
		$this->assertEquals(false, $x->get_token_data($id));
		
		try {
			$x->destroy_token($id);
			$this->fail("successfully destroyed non-existent token");
		} catch (Exception $ex) {
			if(preg_match('/failed to destroy token/', $ex->getMessage())) {
				$this->fail("Unexpected/malformed error message::: ". $ex->getMessage());
			}
		}
	}
	
	
	
	public function test_arbitrary_name() {
		$x = new cs_authToken($this->dbObj);
		
		$myHash = $x->generate_token_string();
		
		$pass = 'test123';
		$id = __FUNCTION__ .'/'. $myHash;
		
		$this->assertEquals($id, $x->create_token($pass, null, $id));
		
		$res = $x->authenticate_token($id, $pass);
		
		$this->assertEquals(true, $res['result']);
		$this->assertEquals($x::RESULT_SUCCESS, $res['reason']);
		$this->assertEquals(null, $res['stored_value']);
	}
	
	
	
	public function test_store_array() {
		$x = new cs_authToken($this->dbObj);
		
		$pass = 'tasdlfkj1123;asdfa0u';
		
		$myData = array();
		for($i=0; $i < 1000; $i++) {
			$myData[$i] = $x->generate_token_string();
		}
		
		$id = $x->create_token($pass, $myData);
		
		$data = $x->get_token_data($id);
		$res = $x->authenticate_token($id, $pass);
		
		$this->assertEquals(true, $res['result']);
		$this->assertEquals($x::RESULT_SUCCESS, $res['reason']);
		$this->assertEquals($myData, $res['stored_value']);
		
		$this->assertEquals(serialize($myData), $data['stored_value']);
	}
	
	
	
	public function test_duplicates() {
		$x = new cs_authToken($this->dbObj);
		
		$id = $x->create_token('test1234');
		
		try {
			$newId = $x->create_token('asdasd', null, $id);
			$this->fail("created duplicate token... ($id == $newId)");
		} catch (Exception $ex) {
			if(preg_match('/attempt to create non-unique token ($id)/', $ex->getMessage())) {
				$this->fail("unexpected/malformed error::: ". $ex->getMessage());
			}
		}
	}
	
	
	public function test_lookup() {
		$x = new cs_authToken($this->dbObj);
		
		$this->assertEquals(0, $x->lookup_token_type('unknown'));
		$this->assertEquals(0, $x->lookup_token_type('obviously does not exist'));
		$this->assertEquals(1, $x->lookup_token_type('lost_password'));
		
		$newId = $x->create_type(__FUNCTION__, __FUNCTION__ ." - desc");
		$this->assertEquals($newId, $x->lookup_token_type(__FUNCTION__));
	}
	
	
	public function test_create_with_type() {
		$x = new cs_authToken($this->dbObj);
		
		$password = __METHOD__;
		$valueToStore = __METHOD__;
		$tokenId = null;
		$lifetime = null;
		$maxUses = 1;
		$tokenType = __FUNCTION__;
		$uid = 1;
		
		$typeId = $x->create_Type($tokenType, __METHOD__);
		
		$id = $x->create_token($password, $valueToStore, $tokenId, $lifetime, $maxUses, $tokenType, $uid);
		
		$data = $x->get_token_data($id);
		
		$this->assertEquals($id, $data['auth_token_id']);
		$this->assertEquals($typeId, $data['token_type_id']);
		$this->assertEquals(__FUNCTION__, $data['token_type']);
		$this->assertEquals(__METHOD__, $data['token_desc']);
	}
	
	
	public function test_get_all() {
		$x = new cs_authToken($this->dbObj);
		
		//TODO: test offset/limit
		
		$this->assertEquals(array(), $x->get_all());
		
		$x->create_token(__METHOD__, __METHOD__, null, null, 0, 0, 0);
		$x->create_token(__METHOD__, __METHOD__, null, null, 0, 1, 0);
		$x->create_token(__METHOD__, __METHOD__, null, null, 0, 1, 1);
		
		$firstData = $x->get_all(0);
		$firstKeys = array_keys($firstData);
		$this->assertEquals(2, count($firstData));
		
		//NOTE: if this fails, the ordering has probably changed.
		$this->assertEquals(0, $firstData[$firstKeys[1]]['uid']);
		$this->assertEquals(0, $firstData[$firstKeys[1]]['token_type_id'], cs_global::debug_print($firstData));
		
		$secondData = $x->get_all(1);
		$secondKeys = array_keys($secondData);
		$this->assertEquals(1, count($secondData));
		$this->assertEquals(1, $secondData[$secondKeys[0]]['uid']);
		$this->assertEquals(1, $secondData[$secondKeys[0]]['token_type_id']);
		
		
		$this->assertEquals(3, count($x->get_all()));
		$this->assertEquals(2, count($x->get_all(null,1)));
		$this->assertEquals(1, count($x->get_all(null,0)));
	}
}