<?php
/*
 * Created on June 21, 2010
 * 
 * FILE INFORMATION:
 * 
 * $HeadURL$
 * $Id$
 * $LastChangedDate$
 * $LastChangedBy$
 * $LastChangedRevision$
 */

class testOfCSGenericPermissions extends UnitTestCase {
	
	//--------------------------------------------------------------------------
	function setUp() {
		$this->gfObj = new cs_globalFunctions;
		$this->gfObj->debugPrintOpt=1;
		if(!defined('CS_UNITTEST')) {
			throw new exception(__METHOD__ .": FATAL: constant 'CS_UNITTEST' not set, can't do testing safely");
		}
		$this->get_valid_users();
		$perm = new _gpTester($this->create_dbconn());
		$perm->do_schema();
	}//end setUp()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function tearDown() {
		$this->remove_tables();
	}
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	private function create_dbconn() {
		$dbParams = array(
			'host'		=> constant('cs_webapplibs-DB_CONNECT_HOST'),
			'dbname'	=> constant('cs_webapplibs-DB_CONNECT_DBNAME'),
			'user'		=> constant('cs_webapplibs-DB_CONNECT_USER'),
			'password'	=> constant('cs_webapplibs-DB_CONNECT_PASSWORD'),
			'port'		=> constant('cs_webapplibs-DB_CONNECT_PORT')
		);
		$db = new cs_phpDB(constant('DBTYPE'));
		$db->connect($dbParams);
		return($db);
	}//end create_dbconn()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	private function remove_tables() {
		$tableList = array(
			'cswal_gdl_object_table', 'cswal_gdl_attribute_table', 'cswal_gdl_path_table',
			'cswal_object_table', 'cswal_user_group_table', 'cswal_group_table'
		);
		
		$db = $this->create_dbconn();
		foreach($tableList as $name) {
			try {
				$db->run_update("DROP TABLE ". $name ." CASCADE", true);
			}
			catch(exception $e) {
				//force an error.
				//$this->assertTrue(false, "Error while dropping (". $name .")::: ". $e->getMessage());
			}
		}
	}//end remove_tables()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	/**
	 * Just like the schema, this SQL will need to change to match your database in order to work.
	 */
	private function get_valid_users() {
		$sql = "SELECT uid,username FROM cs_authentication_table ORDER BY uid";
		try {
			$db = $this->create_dbconn();
			$this->validUsers = $db->run_query($sql);
		}
		catch(Exception $e) {
			cs_debug_backtrace(1);
			throw new exception(__METHOD__ .":: failed to retrieve any records (". $db->numRows() ."), DB OBJECT::: ". $this->gfObj->debug_print($db,0));
		}
	}//end get_valid_users()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function test_userGroups() {
		$perm = new _gpTester($this->create_dbconn());
		
		//make sure there are groups available.
		{
			$groupList = $perm->get_all_groups();
			$keys = array_keys($groupList);
			$myKey = $keys[0];
			
			$this->assertTrue(is_array($groupList));
			$this->assertTrue(count($groupList) > 0);
			
			$this->assertTrue(isset($groupList[$myKey]));
			$this->assertTrue(isset($groupList[$myKey]['group_name']));
			$this->assertTrue(isset($groupList[$myKey]['group_id']));
		}
		
		//create some groups.
		{
			$newGroupId = $perm->create_group(__METHOD__);
			$this->assertTrue(is_numeric($newGroupId));
			
			$groupList = $perm->get_all_groups();
			
			foreach($groupList as $groupData) {
				$this->assertEqual($perm->get_group_by_id($groupData['group_id']), $groupData);
				$this->assertEqual($perm->get_group($groupData['group_name']), $groupData);
			}
		}
		
		//create & test user_group relationships.
		{
			$newId = $perm->create_user_group($this->validUsers[$myKey]['uid'],1);
			$this->assertTrue(is_numeric($newId));
			$this->assertTrue($perm->is_group_member($this->validUsers[$myKey]['uid'],1));
			
			$ugList = $perm->get_user_groups($this->validUsers[$myKey]['uid']);
			$this->assertTrue(is_array($ugList));
			$this->assertTrue(count($ugList) > 0);
			$this->assertFalse(isset($ugList['group_name']));
		}
	}//end test_userGroups
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function test_permissions() {
		
		$perm = new _gpTester($this->create_dbconn());
		
		//Test permission string parsing.
		{
			$myPermArr = array(
				'u_r'	=> 'x',
				'u_w'	=> 'x',
				'u_x'	=> 'r',
				'g_r'	=> '-',
				'g_w'	=> '-',
				'g_x'	=> '',
				'o_r'	=> '',
				'o_w'	=> '-',
				'o_x'	=> 'x'
			);
			$permByType = array(
				'u'	=> array(
						'r'	=> true,
						'w'	=> true,
						'x'	=> true
					),
				'g'	=> array(
						'r'	=> false,
						'w'	=> false,
						'x'	=> false
					),
				'o' => array(
						'r'	=> false,
						'w'	=> false,
						'x'	=> true
					)
			);
			$permString = 'rwx-----x';
			
			$this->assertEqual(strlen($perm->make_perm_string($myPermArr)),9);
			$this->assertEqual(count($perm->parse_perm_string($permString)), 9);
			$this->assertEqual($perm->make_perm_string($myPermArr), $permString);
			$this->assertEqual(array_keys($perm->parse_perm_string($permString)), array_keys($myPermArr));
			$this->assertEqual($perm->make_perm_string($perm->parse_perm_string($permString)), $permString);
			$this->assertEqual(array_keys($perm->parse_perm_string($perm->make_perm_string($myPermArr))), array_keys($myPermArr));
			
			$this->assertEqual($perm->get_perm_list($myPermArr,'u'), $permByType['u']);
			$this->assertEqual($perm->get_perm_list($myPermArr,'g'), $permByType['g']);
			$this->assertEqual($perm->get_perm_list($myPermArr,'o'), $permByType['o']);
		}
		
		//create some permissions.
		{
			$userKeys = array_keys($this->validUsers);
			$myUser = $this->validUsers[$userKeys[0]];
			$myUid = $myUser['uid'];
			
			$usePermString = 'rwxrw-r--';
			$usePermName = __METHOD__ .'/test1';
			$this->assertFalse($perm->permission_exists($usePermName));
			$permId = $perm->create_permission($usePermName, $myUid, 1, $usePermString);
			$this->assertTrue($perm->permission_exists($usePermName));
			$this->assertTrue(is_numeric($permId));
			
			//the method 'build_permissions_string()' should disregard extra indices in the array & build the string.
			$this->assertEqual($perm->make_perm_string($perm->get_permission_by_id($permId)), $usePermString);
			$this->assertEqual($perm->make_perm_string($perm->get_object_by_id($permId)), $usePermString);
			$this->assertEqual($perm->make_perm_string($perm->get_permission($usePermName)), $usePermString);
			$this->assertEqual($perm->make_perm_string($perm->get_object($usePermName)), $usePermString);
			
			//check to make sure individual permission requests work as expected.
			$this->assertTrue($perm->has_read_permission($myUid, $usePermName));
			$this->assertTrue($perm->has_write_permission($myUid, $usePermName));
			$this->assertTrue($perm->has_execute_permission($myUid, $usePermName));
			
			//make sure "anonymous" permissions are correct.
			$this->assertTrue($perm->has_read_permission(0,$usePermName));
			$this->assertFalse($perm->has_write_permission(0,$usePermName));
			$this->assertFalse($perm->has_execute_permission(0,$usePermName));
			
			//put a second user into the proper user_group, then test group permissions.
			$secondUser = $this->validUsers[$userKeys[1]]['uid'];
			$this->assertTrue(is_numeric($perm->create_user_group($secondUser, 1)));
			$this->assertTrue($perm->has_read_permission($secondUser, $usePermName));
			$this->assertTrue($perm->has_write_permission($secondUser, $usePermName));
			$this->assertFalse($perm->has_execute_permission($secondUser, $usePermName));
			
			//test a THIRD user (non-zero uid), make sure they are subject to the same permissions as anonymous (uid=0)
			$thirdUser = $this->validUsers[$userKeys[2]]['uid'];
			$this->assertEqual($perm->has_read_permission(0,$usePermName), $perm->has_read_permission($thirdUser,$usePermName));
			$this->assertEqual($perm->has_write_permission(0,$usePermName), $perm->has_write_permission($thirdUser,$usePermName));
			$this->assertEqual($perm->has_execute_permission(0,$usePermName), $perm->has_execute_permission($thirdUser,$usePermName));
		}
	}//end test_permissions
	//--------------------------------------------------------------------------
	
}

class _gpTester extends cs_genericPermission {
	public function __construct($db) {
		parent::__construct($db);
	}
	
	public function do_schema() {
		$this->build_schema();
	}
	
	public function make_perm_string(array $perms) {
		return($this->build_permission_string($perms));
	}
	
	public function parse_perm_string($string) {
		return($this->parse_permission_string($string));
	}
	
	public function get_perm_list(array $permData, $type) {
		return($this->get_permission_list($permData, $type));
	}
}
?>
