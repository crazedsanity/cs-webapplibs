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
		$this->assertTrue(is_array($originalUserData));
$this->gfObj->debug_print($originalUserData,1);
		$oldPassHash = $originalUserData['passwd'];
		$this->assertNotEquals($originalUserData['passwd'], $x->getPasswordHash(array('test', 'test')));
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
		$this->assertEquals(1, $x->update_passwd($userInfo, $passwd), 'Failed to set password');
		
		$updatedUserInfo = $x->get_user_data(1);
		$this->assertNotEquals($originalUserData['passwd'], $updatedUserInfo['passwd']);
$x->gfObj->debug_print("NEW HASH: ". $newHash,1);
$x->gfObj->debug_print($updatedUserInfo,1);
		$this->assertEquals($newHash, $updatedUserInfo['passwd']);
		
		$compareOriginal = $originalUserData;
		$compareUpdated = $updatedUserInfo;
		unset($compareOriginal['passwd'], $compareUpdated['passwd']);
		$this->assertEquals($compareOriginal, $compareUpdated);
		
		
		//now, let's authenticate.
		$_SESSION['uid'] = 1;
		$this->assertFalse($x->is_authenticated());
		$this->assertTrue($x->login('test', $passwd), 'Failed to login with updated password');
		$this->assertTrue($x->is_authenticated());
	}
}

class _test_authUser extends cs_authUser {
	public function __construct($db){
		parent::__construct($db, false);
	}
	
	
	public function getPasswordHash(array $info) {
		return parent::getPasswordHash($info);
	}
	
	
	public function get_user_data($uid) {
		return parent::get_user_data($uid);
	}
}