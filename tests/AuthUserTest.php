<?php


class AuthUserTest extends testDbAbstract {
	
	public $userInfo = array(
		'username'	=> 'test',
		'uid'		=> 1,
	);
	
	public function __construct() {
		parent::__construct();
	}//end __construct()
	
	public function setUp() {
		parent::setUp();
		$this->reset_db(dirname(__FILE__) .'/../setup/schema.pgsql.sql');
	}
	public function tearDown(){
		parent::tearDown();
		unset($_SESSION);
	}
	
	/**
	 * Ensuring these functions are enabled is really the job of the 
	 * application... the "bootstrap.php" script handles this for unit testing.
	 */
	public function test_passwordCompat() {
		$funcList = array(
			'password_hash', 'password_get_info', 
			'password_needs_rehash', 'password_verify', 
		);
		
		foreach($funcList as $func) {
			$this->assertTrue(function_exists($func), "Required function '". 
					$func ."' is missing, use composer to install the ".
					"'ircmaxell/password-compat' library or upgrade to ".
					"PHP 5.5+");
		}
	}
	
	
	public function test_collisionOfConstants() {
		$x = new _empty_testAuthUser();
		$myConstants = array(
			'md5'			=> $x::HASH_MD5,
			'sha1'			=> $x::HASH_SHA1,
			'sha256'		=> $x::HASH_SHA256,
			'sha512'		=> $x::HASH_SHA512,
			'PHP-bcrypt'	=> constant('PASSWORD_BCRYPT'),
//			'PHP-default'	=> constant('PASSWORD_DEFAULT'),	// the default is PHP-bcrypt... 
		);
		
		foreach($myConstants as $yName => $yVal) {
			foreach($myConstants as $zName => $zVal) {
				if($zName == $yName) {
					$this->assertEquals($zVal, $yVal, "constants do not match for '". $zName ."' and '". $yName ."'... but they should");
				}
				else {
					$this->assertNotEquals($zVal, $yVal, "collision detected: '". $zName ."' matches value for '". $yName ."' (". $zVal ."=". $yVal .")");
				}
			}
		}
		
		$this->assertEquals($x::HASH_PHPDEFAULT, PASSWORD_DEFAULT);
		$this->assertEquals($x::HASH_PHPBCRYPT, PASSWORD_BCRYPT);
	}
	
	
	public function test_validBaseData() {
		//$x = new cs_authUser($this->dbObj);
		$x = new _test_authUser($this->dbObj);

		$this->assertFalse($x->is_authenticated());

		$numRows = $this->dbObj->run_query('SELECT * FROM cs_authentication_table');
		$this->assertTrue($numRows > 0);
		$data = $this->dbObj->farray_fieldnames();

		$this->assertEquals(0, $data[0]['uid']);
		$this->assertEquals('anonymous', $data[0]['username']);
		$this->assertEquals(0, strlen($data[0]['passwd']));

		$this->assertEquals(1, $data[1]['uid']);
		$this->assertEquals('test', $data[1]['username']);
		$this->assertEquals(40, strlen($data[1]['passwd']));

		$this->assertEquals(2, $data[2]['uid']);
		$this->assertEquals('administrator', $data[2]['username']);
		$this->assertEquals(40, strlen($data[2]['passwd']));
	}
	
	
	public function test_sessionStuff() {
		$myAuthData = array(
			'uid'	=> 999,
			'user'	=> 999,
			'stuff'	=> "dsfadsfadsfads"
		);
		
		$this->assertFalse(isset($_SESSION));
		$x = new _test_authUser($this->dbObj, false);
		$this->assertFalse(isset($_SESSION));
		$_SESSION = array();
		$this->assertTrue(isset($_SESSION));
		$this->assertEquals(array(), $_SESSION);
		
		$x->update_auth_data($myAuthData);
		$this->assertEquals($myAuthData, $_SESSION['auth']['userInfo']);
		$this->assertEquals(999, $_SESSION['uid']);
	}
	
	
	public function test_simpleAuthentication() {
		$this->assertFalse(isset($_SESSION));
		
		$_SESSION = array();
		$this->assertTrue(isset($_SESSION));
		$this->assertEquals(array(), $_SESSION);
		
		$_SESSION['uid'] = 0;
		
		$x = new _test_authUser($this->dbObj, false);
		$this->assertFalse(isset($_SESSION['auth']));
		
		//test that our first attempt doesn't work, because we don't know the password.
		$originalUserData = $x->get_user_data(1,true);
		$this->assertEquals('75eba0f69d185ef816d0cee43ad44d4b2240de02', $originalUserData['passwd']);
		$this->assertTrue(is_array($originalUserData));
//		$oldPassHash = $originalUserData['passwd'];
//		$this->assertNotEquals($originalUserData['passwd'], $x->getPasswordHash(array('test', 'test'), strlen($originalUserData['passwd'])));
		$this->assertFalse((bool)$x->login('test', 'test'));
		
		//Change test's password...
		$passwd = '_unitTe5t3r';
		$user = 'test';
		
//		$newHash = $x->getPasswordHash(array('username'=>$user, 'passwd'=>$passwd), $x::HASH_SHA1);
//		$this->assertEquals(
//				$newHash,
//				sha1(implode('-', array($user, $passwd)))
//		);
		$uid = 1;
//		$userInfo = array(
//			'uid'		=> 1,
//			'username'	=> $user,
//		);
		$this->assertEquals(1, $x->update_passwd($uid, $passwd), 'Failed to set password');
		
		$updatedUserInfo = $x->get_user_data(1);
		$this->assertNotEquals($originalUserData['passwd'], $updatedUserInfo['passwd']);
		
		$compareOriginal = $originalUserData;
		$compareUpdated = $updatedUserInfo;
		$this->assertEquals(cs_authUser::STATUS_ENABLED, $updatedUserInfo['user_status_id']);
		unset($compareOriginal['passwd'], $compareUpdated['passwd']);
		$this->assertEquals($compareOriginal, $compareUpdated);
		
		
		$this->assertFalse($x->is_authenticated());
		
		$_SESSION = array();
		$this->assertEquals(count($_SESSION), 0);
		
		$this->assertFalse($x->is_authenticated());
		$this->assertTrue($x->update_passwd($uid, $passwd), "Failed to update password");
		$this->assertFalse($x->is_authenticated());
		$this->assertFalse($x->check_sid());
		
		$this->assertFalse((bool)$x->login(ucfirst($user), $passwd), "Username accepted with incorrect casing");
		$this->assertFalse($x->is_authenticated());
		$this->assertFalse($x->check_sid());
		
		$this->assertTrue((bool)$x->login($user, $passwd), "Failed to login");
		$this->assertTrue($x->is_authenticated());
		$this->assertTrue($x->check_sid());
		
		$this->assertTrue((bool)$x->logout_sid());
		$this->assertFalse($x->is_authenticated());
		$this->assertFalse($x->check_sid());
		
	}
	
	
	public function test_update_passwd_with_null() {
		$x = new cs_authUser($this->dbObj);
		
		try {
			$this->assertFalse($x->update_passwd(array('username'=>'test'), null));
			$this->fail("password allowed to be null");
		}
		catch(Exception $ex) {
			if(!preg_match('/update_passwd: invalid ID or password/', $ex->getMessage())) {
				$this->fail("malformed or unexpected content in exception: ". $ex->getMessage());
			}
		}
		
	}
	
	
	public function test_update_password_with_invalid_username() {
		$x = new cs_authUser($this->dbObj);
		try {
			$x->update_passwd(99999999, "");
			$this->fail("updating password for non-existent user worked");
		} catch (Exception $ex) {
			if(!preg_match('/update_user_info:.+failed to update user information/', $ex->getMessage())) {
				$this->fail("unexpected exception data: ". $ex->getMessage());
			}
		}
	}
	
	
	public function test_get_user_data() {
		$x = new cs_authUser($this->dbObj);
		
		$data = $x->get_user_data('test', $x::STATUS_ENABLED);
		$this->assertTrue(is_array($data), cs_global::debug_print($data));
		
		$this->assertTrue(isset($data['uid']));
		$this->assertTrue(isset($data['username']));
		$this->assertTrue(isset($data['passwd']));
//		$this->assertTrue(isset($data['date_created']));
//		$this->assertTrue(isset($data['last_login']));
//		$this->assertTrue(isset($data['email']));
		$this->assertTrue(isset($data['user_status_id']));
		
		
		// Ensure retrieval with any other status fails.
		{
			try {
				$this->assertFalse(is_array($x->get_user_data('test', $x::STATUS_DISABLED)));
			} catch (Exception $ex) {
				if(!preg_match('/failed to retrieve a single user \(0\)/', $ex->getMessage())) {
					$this->fail("unexpected or invalid exception message: ". $ex->getMessage());
				}
			}
			try {
				$this->assertFalse(is_array($x->get_user_data('test', $x::STATUS_PENDING)));
			} catch (Exception $ex) {
				if(!preg_match('/failed to retrieve a single user \(0\)/', $ex->getMessage())) {
					$this->fail("unexpected or invalid exception message: ". $ex->getMessage());
				}
			}
			try {
				$this->assertFalse(is_array($x->get_user_data('test', 99999)));
			} catch (Exception $ex) {
				if(!preg_match('/failed to retrieve a single user \(0\)/', $ex->getMessage())) {
					$this->fail("unexpected or invalid exception message: ". $ex->getMessage());
				}
			}
		}
	}
	
	
	public function test_update_user_data() {
		$x = new cs_authUser($this->dbObj);
		
		$data = $x->get_user_data('test', $x::STATUS_ENABLED);
		
		$this->assertTrue(is_array($data));
		$this->assertTrue(isset($data['uid']));
		$this->assertTrue(is_numeric($data['uid']));
		$this->assertEquals($data['user_status_id'], $x::STATUS_ENABLED);
		
		try {
			$x->update_user_info(array(), $data['uid']);
			$this->fail("Successfully updated... nothing");
		}
		catch(Exception $ex) {
			if(!preg_match("/invalid user info/", $ex->getMessage())) {
				$this->fail("unexpected or invalid exception message: ". $ex->getMessage());
			}
		}
		
		try {
			$x->update_user_info(array(''=>"test"), $data['uid']);
			$this->fail("Successfully updated without specifying a field");
		} catch (Exception $ex) {
			if(!preg_match("/invalid parameter number/i", $ex->getMessage())) {
				$this->fail("unexpected or invalid exception message: ". $ex->getMessage());
			}
		}
		
		$updateResult = $x->update_user_info(array('email'=> 'Poop@user.com'), $data['uid']);
		
		$this->assertEquals(1, $updateResult);
		
		$newData = $x->get_user_data('test', $x::STATUS_ENABLED);
		$this->assertEquals($newData['uid'], $data['uid']);
		$this->assertNotEquals($data, $newData);
		
		
		$disableResult = $x->update_user_info(array('user_status_id'=>$x::STATUS_DISABLED), $data['uid']);
		$this->assertEquals(1, $disableResult);
		
		try {
			$x->get_user_data('test', $x::STATUS_ENABLED);
			$this->fail("Found user enabled even after disabling them");
		} catch (Exception $ex) {
			if(!preg_match("/failed to retrieve a single user \(0\)/", $ex->getMessage())) {
				$this->fail("unexpected or invalid exception message: ". $ex->getMessage());
			}
		}
//		$this->assertEquals(array(), $x->get_user_data('test', $x::STATUS_ENABLED));
	}
}

class _test_authUser extends cs_authUser {
	public function __construct($db){
		parent::__construct($db, false);
	}
	
	
	public function update_auth_data(array $data) {
		return parent::update_auth_data($data);
	}
	
	
	public function get_user_data($uid, $onlyStatus=self::STATUS_ENABLED) {
		return parent::get_user_data($uid, $onlyStatus);
	}
}

class _empty_testAuthUser extends cs_authUser {
	public function __construct($db=null) {
	}
}