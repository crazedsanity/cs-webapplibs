<?php


class AuthUserTest extends testDbAbstract {
	
	
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
	
	
	public function test_sesionStuff() {
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
		$this->assertNotEquals($originalUserData['passwd'], $x->getPasswordHash(array('test', 'test'), strlen($originalUserData['passwd'])));
		$this->assertFalse($x->login('test', 'test'));
		
		//Change test's password...
		$passwd = '_unitTe5t3r';
		$newHash = $x->getPasswordHash(array('username'=>'test', 'passwd'=>$passwd));
		$this->assertEquals(
				$newHash,
				sha1(implode('-', array('test', $passwd)))
		);
		$userInfo = array(
			'uid'		=> 1,
			'username'	=> 'test',
		);
		$this->assertEquals(1, $x->update_passwd($userInfo, $passwd, $x::HASH_SHA1), 'Failed to set password');
		
		$updatedUserInfo = $x->get_user_data(1);
		$this->assertNotEquals($originalUserData['passwd'], $updatedUserInfo['passwd']);
		$this->assertEquals($newHash, $updatedUserInfo['passwd']);
		
		$compareOriginal = $originalUserData;
		$compareUpdated = $updatedUserInfo;
		$this->assertEquals(cs_authUser::STATUS_ENABLED, $updatedUserInfo['user_status_id']);
		unset($compareOriginal['passwd'], $compareUpdated['passwd']);
		$this->assertEquals($compareOriginal, $compareUpdated);
		
	
		
		// try authenticating using all the different algorithms...
		{
			$types = array(
				'md5'		=> 32,
				'sha1'		=> 40,
				'sha256'	=> 64,
				'sha512'	=> 128
			);
			
			$this->assertEquals($x::HASH_MD5, $types['md5']);
			$this->assertEquals($x::HASH_SHA1, $types['sha1']);
			$this->assertEquals($x::HASH_SHA256, $types['sha256']);
			$this->assertEquals($x::HASH_SHA512, $types['sha512']);
		}
		
		$this->assertTrue(!$x->is_authenticated());
		
		foreach($types as $hashName => $hashType) {
			$this->assertFalse($x->is_authenticated());
			
			$this->assertTrue($x->update_passwd($userInfo, $passwd, $hashType), "Failed to update password using '". $hashName ."' (". $hashType .")");
			
			$this->assertFalse($x->is_authenticated());
			$this->assertTrue($x->login('test', $passwd));
			$this->assertTrue($x->is_authenticated());
			
			$this->assertTrue((bool)$x->logout_sid());
			$this->assertFalse($x->is_authenticated());
		}
		
//		//now, let's authenticate.
//		$_SESSION['uid'] = 1;
//		$this->assertFalse($x->is_authenticated());
//		$this->assertTrue($x->login('test', $passwd), 'Failed to login with updated password');
//		$this->assertTrue($x->is_authenticated());
//		
//		// change the password, and try to re-authenticate.
//		unset($_SESSION['uid']);
//		$this->assertFalse($x->is_authenticated());
//		$this->assertTrue($x->update_passwd($userInfo, $passwd, $x::HASH_MD5), 'Failed to update password');
//		$this->assertTrue($x->login('test', $passwd), 'Failed to login with new password');
//		$this->assertTrue($x->is_authenticated());
//		$this->assertTrue((bool)$x->logout_sid());
//		$this->assertFalse($x->is_authenticated());
	}
}

class _test_authUser extends cs_authUser {
	public function __construct($db){
		parent::__construct($db, false);
	}
	
	
	public function get_user_data($uid) {
		return parent::get_user_data($uid);
	}
	
	
	public function update_auth_data(array $data) {
		parent::update_auth_data($data);
	}
}