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

class testOfCSGenericPermissions extends testDbAbstract {
	
	
	//--------------------------------------------------------------------------
	public function __construct() {
	}//end __construct()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	function setUp() {
		$this->gfObj = new cs_globalFunctions;
		$this->gfObj->debugPrintOpt=1;
		parent::__construct('postgres','', 'localhost', '5432');
		$this->permObj = new _gpTester($this->db);
		$this->permObj->do_schema();
		$this->get_valid_users();
		
		$this->defaultGroupId = null;
		
		//create some groups.
		{
			$myGroupList = array('www', 'blogs', 'admin', __METHOD__);
			#$newGroupId = $this->permObj->create_group(__METHOD__);
			$userKeys = array_keys($this->validUsers);
			foreach($myGroupList as $tName) {
				$newGroupId = $this->permObj->create_group($tName, $this->chosenOneUid);
				if(is_null($this->defaultGroupId)) {
					$this->defaultGroupId = $newGroupId;
				}
				$this->assertTrue(is_numeric($newGroupId));
			}
			
			$groupList = $this->permObj->get_all_groups();
			
			foreach($groupList as $groupId=>$groupData) {
				$this->assertEqual($this->permObj->get_group_by_id($groupId), $groupData, "failed to get group (". $groupData['group_name'] .") by ID (". $groupId .")");
				$this->assertEqual($this->permObj->get_group($groupData['group_name']), $groupData, "failed to get group (". $groupData['group_name'] .") by name");
			}
		}
		
		//create some objects...
		{
		$requiredItems = array('{APPURL}', 'member');
			foreach($requiredItems as $name) {
				$newId = $this->permObj->create_object($name);
				$this->assertTrue(is_numeric($newId));
			}
		}

	}//end setUp()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function tearDown() {
		if(isset($GLOBALS['keepDb'])) {
			unset($GLOBALS['keepDb']);
		}
		else {
			$this->destroy_db();
		}
	}
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	/**
	 * Just like the schema, this SQL will need to change to match your database in order to work.
	 */
	private function get_valid_users() {
		$sql = "SELECT uid,username FROM cs_authentication_table ORDER BY uid";
		try {
			$this->validUsers = $this->db->run_query($sql);
			
			$userKeys = array_keys($this->validUsers);
			$myUser = $this->validUsers[$userKeys[0]];
			$this->chosenOneUid = $myUser['uid'];
			$this->defaultUid = $this->chosenOneUid;
		}
		catch(Exception $e) {
			cs_debug_backtrace(1);
			throw new exception(__METHOD__ .":: failed to retrieve any records (". $db->numRows() ."), DB OBJECT::: ". $this->gfObj->debug_print($db,0));
		}
	}//end get_valid_users()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function test_userGroups() {
		#$GLOBALS['keepDb'] = true;
		//make sure there are groups available.
		{
			$groupList = $this->permObj->get_all_groups();
			$keys = array_keys($groupList);
			$myKey = $keys[0];
			
			$this->assertTrue(is_array($groupList));
			$this->assertTrue(count($groupList) > 0);
			
			$this->assertTrue(isset($groupList[$myKey]));
			$this->assertTrue(isset($groupList[$myKey]['group_name']));
			$this->assertTrue(isset($groupList[$myKey]['group_id']));
		}
		
		//create & test user_group relationships.
		{
			$newId = $this->permObj->create_user_group($this->validUsers[$myKey]['uid'],$this->defaultGroupId);
			$this->assertTrue(is_numeric($newId));
			$this->assertTrue($this->permObj->is_group_member($this->validUsers[$myKey]['uid'],$this->defaultGroupId), "user (". 
					$this->validUsers[$myKey]['uid'] .") isn't member of group (". $this->defaultGroupId .") after being added to it... ");
			
			$ugList = $this->permObj->get_user_groups($this->validUsers[$myKey]['uid']);
			$this->assertTrue(is_array($ugList));
			$this->assertTrue(count($ugList) > 0);
			$this->assertFalse(isset($ugList['group_name']));
		}
	}//end test_userGroups
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function test_object_paths() {
		//basic functionality test for ID Path creation.
		{
			$expectThis = ':5::30::2::18::5:';
			
			//make the expected string into something that be broken into an array of numbers.
			$chunktify = preg_replace('/^:(.*):$/', '$1', $expectThis);
			$chunktify = preg_replace('/:{2,}/', ':', $chunktify);
			$bits = explode(':', $chunktify);
			
			$this->assertEqual($bits, $this->permObj->explode_id_path($expectThis));
			
			$this->assertEqual(count($bits), 5, 'could not break string into id bits');
			
			$derivedIdPath = "";
			foreach($bits as $id) {
				$derivedIdPath .= $this->permObj->create_id_path_part($id);
			}
			$this->assertEqual($derivedIdPath, $expectThis, 'Invalid idPath, expected=('. $expectThis .'), actual=('. $derivedIdPath .')');
			
			$idPathList = array(':9:', ':9::5:', ':0::0::0:', ':-1:', ':-1::-1:', ':-400::1::3:', ':51041::600000::8109223::999999999999999999999999999999999:');
			foreach($idPathList as $tPath) {
				$this->assertTrue($this->permObj->is_id_path($tPath), "valid path (". $tPath .") not recognized as such");
			}
			
			$invalidIdPathList = array('', ':--1:', '1::3::4:', ':1::3::4', ':1:3:4:');
			foreach($invalidIdPathList as $tPath) {
				$this->assertFalse($this->permObj->is_id_path($tPath), "invalid path (". $tPath .") evaluated as valid");
			}
		}
		
		//Search for existing items (should have been created from setUp())
		{
			$requiredItems = array('{APPURL}', 'member');
			$existingItems = $this->permObj->get_object_ids($requiredItems, false);
			$this->assertEqual(count($existingItems), 2, 'Required items not present... '. $this->gfObj->debug_print($existingItems,0,1));
			
			//in the event the required items aren't there, create them.
			$testExistingItems = $this->permObj->get_object_ids($requiredItems, true);
			$this->assertEqual(count($requiredItems), count($testExistingItems), 'failed to create some existing items');
			$this->assertEqual(count($testExistingItems), count($existingItems), 'WARNING: some required items were not found');
		}
		
		//Build new ID paths...
		{
			$newObjects = array('admin', 'logs', 'view');
			$idPath = $this->permObj->create_id_path('/admin/logs/view');
			$this->assertTrue(preg_match('/^:[0-9]{1,}::[0-9]{1,}::[0-9]{1,}:/', $idPath), 'path appears syntactically incorrect ('. $idPath .')');
			
			//make sure the manually-created ID Path matches what was actually created.
			$idList = $this->permObj->get_object_ids($newObjects, false);
			$this->assertEqual(count($idList), count($newObjects), "there must be missing objects, counts don't match");
			
			$expectedIdPath = "";
			foreach($idList as $id=>$n) {
				$expectedIdPath .= $this->permObj->create_id_path_part($id);
			}
			$this->assertEqual($expectedIdPath, $idPath, "Manually created path (". $expectedIdPath .") does not match idPath (". $idPath .")");
		}
	}//end test_object_paths()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function test_permissions() {
		#$GLOBALS['keepDb'] = true;
		
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
			
			$this->assertEqual(strlen($this->permObj->make_perm_string($myPermArr)),9);
			$this->assertEqual(count($this->permObj->parse_perm_string($permString)), 9);
			$this->assertEqual($this->permObj->make_perm_string($myPermArr), $permString);
			$this->assertEqual(array_keys($this->permObj->parse_perm_string($permString)), array_keys($myPermArr));
			$this->assertEqual($this->permObj->make_perm_string($this->permObj->parse_perm_string($permString)), $permString);
			$this->assertEqual(array_keys($this->permObj->parse_perm_string($this->permObj->make_perm_string($myPermArr))), array_keys($myPermArr));
			
			$this->assertEqual($this->permObj->get_perm_list($myPermArr,'u'), $permByType['u']);
			$this->assertEqual($this->permObj->get_perm_list($myPermArr,'g'), $permByType['g']);
			$this->assertEqual($this->permObj->get_perm_list($myPermArr,'o'), $permByType['o']);
		}
		
		//create some permissions.
		{
			$userKeys = array_keys($this->validUsers);
			$myUser = $this->validUsers[$userKeys[0]];
			$myUid = $myUser['uid'];
			
			$usePermString = 'rwxrw-r--';
			$usePermName = __METHOD__ .'/test1';
			$this->assertFalse($this->permObj->permission_exists($usePermName));
			$permId = $this->permObj->create_permission($usePermName, $myUid, 1, $usePermString);
			if(!$this->assertTrue($this->permObj->permission_exists($usePermName), "Permission ('". $usePermName ."', id=". $permId .") does not exist")) {
				$this->gfObj->debug_print($this->permObj->testDbTableHandler->get_records());
			}
			$this->assertTrue(is_numeric($permId));
			
			//the method 'build_permissions_string()' should disregard extra indices in the array & build the string.
			$this->assertEqual($this->permObj->make_perm_string($this->permObj->get_permission_by_id($permId)), $usePermString);
			$this->assertEqual($this->permObj->make_perm_string($this->permObj->get_permission($usePermName)), $usePermString);
			
			//check to make sure individual permission requests work as expected.
			$this->assertTrue($this->permObj->has_read_permission($myUid, $usePermName));
			$this->assertTrue($this->permObj->has_write_permission($myUid, $usePermName));
			$this->assertTrue($this->permObj->has_execute_permission($myUid, $usePermName));
			
			//make sure "anonymous" permissions are correct.
			$this->assertTrue($this->permObj->has_read_permission(0,$usePermName));
			$this->assertFalse($this->permObj->has_write_permission(0,$usePermName));
			$this->assertFalse($this->permObj->has_execute_permission(0,$usePermName));
			
			//put a second user into the proper user_group, then test group permissions.
			$secondUser = $this->validUsers[$userKeys[1]]['uid'];
			$this->assertTrue(is_numeric($this->permObj->create_user_group($secondUser, 1)));
			$this->assertTrue($this->permObj->has_read_permission($secondUser, $usePermName));
			$this->assertTrue($this->permObj->has_write_permission($secondUser, $usePermName));
			$this->assertFalse($this->permObj->has_execute_permission($secondUser, $usePermName));
			
			//test a THIRD user (non-zero uid), make sure they are subject to the same permissions as anonymous (uid=0)
			$thirdUser = $this->validUsers[$userKeys[2]]['uid'];
			$this->assertEqual($this->permObj->has_read_permission(0,$usePermName), $this->permObj->has_read_permission($thirdUser,$usePermName));
			$this->assertEqual($this->permObj->has_write_permission(0,$usePermName), $this->permObj->has_write_permission($thirdUser,$usePermName));
			$this->assertEqual($this->permObj->has_execute_permission(0,$usePermName), $this->permObj->has_execute_permission($thirdUser,$usePermName));
		}
		
		//create a huge list of permissions to test different scenarios.
		{
			$testPaths = array(
				'/member',
				'/member/ttorp',
				'/member/ttorp/character',
				'/member/ttorp/character/sheet',
				'/member/ttorp/character/sheet/44',
				'/admin/x',
				'/admin/logs',
				'/test/scripts/1/2/3/magicMarker',
				'/api/system/test/212312300231223.21231/get/333',
				'/content/dev/corner/cs/project',
				'/content/dev/corner/cs/web/app/libs',
				'/content/dev',
				'/content/dev/corner',
				'/content/dev/coner/cs',
				
				//The following items fail for no apparent reason (that I can find)...
				'/admin',
				'/cntenx',
				'/content/dev/corner/cs/content', //fails due to duplicate objects in path ("content" is in there twice).
			);
			$testPerms = array(
				'rwxrwxrwx',
				'rwxrwx---',
				'rwx---rwx',
				'---rwxrwx',
				'rwx------',
				'---------',
				'r--r--r--',
				'-w--w--w-',
				'--x--x--x'
			);
			foreach($testPaths as $tPath) {
				$tPermString = 'rwxrwx---';
				$newPermId = $this->permObj->create_permission($tPath, $this->chosenOneUid, $this->defaultGroupId, $tPermString);
				$this->assertTrue(is_numeric($newPermId));
			}
		}///*/
	}//end test_permissions
	//--------------------------------------------------------------------------
	
}

class _gpTester extends cs_genericPermission {
	public $testDbTableHandler;
	public function __construct($db) {
		parent::__construct($db);
		$this->testDbTableHandler = $this->dbTableHandler;
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
