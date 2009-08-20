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

require_once(dirname(__FILE__) .'/../cs_version.abstract.class.php');
require_once(dirname(__FILE__) .'/../cs_authToken.class.php');

class testOfCSWebAppLibs extends UnitTestCase {
	
	//--------------------------------------------------------------------------
	function __construct() {
		$this->gfObj = new cs_globalFunctions;
		$this->gfObj->debugPrintOpt=1;
	}//end __construct()
	//--------------------------------------------------------------------------
	
	
	//--------------------------------------------------------------------------
	function test_version_basics() {
		
		$tests = array(
			'files/version1'	=> array(
				'0.1.2-ALPHA8754',
				'test1',
				array(
					'version_major'			=> 0,
					'version_minor'			=> 1,
					'version_maintenance'	=> 2,
					'version_suffix'		=> 'ALPHA8754'
				)
			),
			'files/version2'	=> array(
				'5.4.0',
				'test2',
				array(
					'version_major'			=> 5,
					'version_minor'			=> 4,
					'version_maintenance'	=> 0,
					'version_suffix'		=> null
				)
			),
			'files/version3'	=> array(
				'5.4.3-BETA5543',
				'test3 stuff',
				array(
					'version_major'			=> 5,
					'version_minor'			=> 4,
					'version_maintenance'	=> 3,
					'version_suffix'		=> 'BETA5543'
				)
			)
		);
		
		foreach($tests as $fileName=>$expectedArr) {
			$ver = new middleTestClass();
			$ver->set_version_file_location(dirname(__FILE__) .'/'. $fileName);
			
			$this->assertEqual($expectedArr[0], $ver->get_version(), "Failed to match string from file (". $fileName .")");
			$this->assertEqual($expectedArr[1], $ver->get_project(), "Failed to match project from file (". $fileName .")");
			
			//now check that pulling the version as an array is the same...
			$checkItArr = $ver->get_version(true);
			$expectThis = $expectedArr[2];
			$expectThis['version_string'] = $expectedArr[0];
		}
	}//end test_version_basics()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	function test_check_higher() {
		
		//NOTE: the first item should ALWAYS be higher.
		$tests = array(
			'basic, no suffix'	=> array('1.0.1', '1.0.0'),
			'basic + suffix'	=> array('1.0.0-ALPHA1', '1.0.0-ALPHA0'),
			'basic w/o maint'	=> array('1.0.1', '1.0'),
			'suffix check'		=> array('1.0.0-BETA1', '1.0.0-ALPHA1'),
			'suffix check2'		=> array('1.0.0-ALPHA10', '1.0.0-ALPHA1'),
			'suffix check3'		=> array('1.0.1', '1.0.0-RC1')
		);
		
		foreach($tests as $name=>$checkData) {
			$ver = new middleTestClass;
			$this->assertTrue($ver->is_higher_version($checkData[1], $checkData[0]));
			$this->assertFalse($ver->is_higher_version($checkData[0], $checkData[1]));
		}
		
		//now check to ensure there's no problem with parsing equivalent versions.
		$tests = array(
			'no suffix'				=> array('1.0', '1.0.0'),
			'no maint + suffix'		=> array('1.0-ALPHA1', '1.0.0-ALPHA1'),
			'no maint + BETA'		=> array('1.0-BETA5555', '1.0.0-BETA5555'),
			'no maint + RC'			=> array('1.0-RC33', '1.0.0-RC33'),
			'maint with space'		=> array('1.0-RC  33', '1.0.0-RC33'),
			'extra spaces'			=> array(' 1.0   ', '1.0.0')
		);
		foreach($tests as $name=>$checkData) {
			$ver = new middleTestClass;
			
			//rip apart & recreate first version to test against the expected...
			$derivedFullVersion = $ver->build_full_version_string($ver->parse_version_string($checkData[0]));
			$this->assertEqual($derivedFullVersion, $checkData[1], "TEST=(". $name ."): derived version " .
					"(". $derivedFullVersion .") doesn't match expected (". $checkData[1] .")");
			
			//now rip apart & recreate the expected version (second) and make sure it matches itself.
			$derivedFullVersion = $ver->build_full_version_string($ver->parse_version_string($checkData[1]));
			$this->assertEqual($derivedFullVersion, $checkData[1], "TEST=(". $name ."): derived version " .
					"(". $derivedFullVersion .") doesn't match expected (". $checkData[1] .")");
		}
		
		
	}//end test_check_higher()
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
		
		//try to create a token with max_uses of 0.
		{
			$tokenData = $tok->create_token(2, 'test', 'xxxxyyyyyxxxx', null, 0);
			$checkData = $tok->tokenData($tokenData['id']);
			$checkData = $checkData[$tokenData['id']];
			
			$this->assertTrue(is_array($checkData));
			if(!$this->assertEqual($tokenData['id'], $checkData['auth_token_id'])) {
				$this->gfObj->debug_print($checkData);
			}
			$this->assertEqual($checkData['max_uses'], null);
		}
	}//end test_token_basics()
	//--------------------------------------------------------------------------
}


class middleTestClass extends cs_versionAbstract {
	function __construct(){}
} 

class authTokenTester extends cs_authToken {
	public $isTest=true;
	
	public function tokenData($id) {
		return($this->get_token_data($id));
	}
}
?>
