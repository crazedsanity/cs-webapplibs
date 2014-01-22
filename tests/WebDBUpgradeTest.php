<?php

class testOfCSWebDbUpgrade extends testDbAbstract {
	
	public $fileToVersion = array();
	
	//--------------------------------------------------------------------------
	function __construct() {
		$this->gfObj = new cs_globalFunctions;
		$this->gfObj->debugPrintOpt=1;
		parent::__construct();
		
		$this->fileToVersion = array(
			dirname(__FILE__). '/files/VERSION-1'	=> "1.0.0-RC8000312",
			dirname(__FILE__) .'/files/VERSION-2'	=> "1.9.0",
			dirname(__FILE__) .'/files/VERSION-3'	=> "8.0.4003-RC2"
		);
	}//end __construct()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	function setUp() {
		$this->gfObj = new cs_globalFunctions;
		$this->gfObj->debugPrintOpt=1;
		
		$this->reset_db(dirname(__FILE__) .'/../setup/schema.pgsql.sql');
		parent::setUp();
	}//end setUp()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function tearDown() {
		parent::tearDown();
	}//end tearDown()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function test_versionParsing() {
		$upgObj = new upgradeTester();
		
		// These should all pass muster.
		$testVersions = array(
			"1.2.3-BETA4", "1.0", "85.20005.1-BETA33", "1.01", "1.10"
		);
		foreach($testVersions as $testThis) {
			// Check how it parses version strings.
			$vArr = $upgObj->parse_version_string($testThis);
			$this->assertTrue(isset($vArr['version_string']));
			$this->assertTrue(isset($vArr['version_major']));
			$this->assertTrue(isset($vArr['version_minor']));
			$this->assertTrue(isset($vArr['version_maintenance']));
			$this->assertTrue(isset($vArr['version_suffix']));
			$this->assertEquals(count($vArr), 5, "Version info (". $testThis .") has unexpected number of elements (". count($vArr) .")");
		}
		
		// enumerate each piece of a fairly static version string, make sure it works.
		$vArr = $upgObj->parse_version_string("1.2.3-BETA12");
		$this->assertEquals($vArr['version_string'], '1.2.3-BETA12');
		$this->assertEquals($vArr['version_major'], '1');
		$this->assertEquals($vArr['version_minor'], '2');
		$this->assertEquals($vArr['version_maintenance'], '3');
		$this->assertEquals($vArr['version_suffix'], 'BETA12');
		
		
		// use a condensed version string, make sure it still passes.
		$vArr = $upgObj->parse_version_string("1.2");
		$this->assertEquals($vArr['version_string'], '1.2');
		$this->assertEquals($vArr['version_major'], '1');
		$this->assertEquals($vArr['version_minor'], '2');
		$this->assertEquals((string)$vArr['version_maintenance'], '0');
		$this->assertEquals($vArr['version_suffix'], '');
		
		
		// Parse some suffixes, make sure they're okay.
		$testArray = array("BETA1", "ALPHA2", "RC3");
		foreach($testArray as $suffix) {
			$vArr = $upgObj->parse_suffix($suffix);
			
			$this->assertEquals(count($vArr), 2, "Invalid number of returned elements in (". $suffix .")");
			
			$this->assertTrue(isset($vArr['type']), "Missing index 'type'");
			$this->assertTrue(isset($vArr['number']), "Missing index 'number'");
			
			$this->assertFalse(is_numeric($vArr['type']), "Type (". $vArr['type'] .") appears to be numeric?");
			$this->assertTrue(is_numeric($vArr['number']), "Number (". $vArr['number'] .") isn't a number...?");
		}
		
	}
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function test_dbVersions() {
		$x = new upgradeTester();
		$x->db = $this->dbObj;
		$x->versionFileLocation = dirname(__FILE__) .'/files/VERSION-1';
		$x->upgradeConfigFile = dirname(__FILE__) .'/files/upgrade.ini';
		
		$this->assertEquals('1.0.0-RC8000312', $x->read_version_file());
		$this->assertEquals('cs-webapplibstest', $x->projectName);
		
		
		$testThis = $x->get_database_version();
		$this->assertEquals("", $testThis);
		$setRes = $x->set_initial_version('0.2.1-ALPHA3');
		$this->assertTrue($setRes);
		
		$dbVersion = $x->get_database_version();
		$this->assertEquals('0.2.1-ALPHA3', $dbVersion['version_string']);
		$this->assertEquals('0', $dbVersion['version_major']);
		$this->assertEquals('2', $dbVersion['version_minor']);
		$this->assertEquals('1', $dbVersion['version_maintenance']);
		$this->assertEquals('ALPHA3', $dbVersion['version_suffix']);
		
		$updateRes = $x->update_database_version('1.2.3-RC1');
		$this->assertTrue((bool)$updateRes);
		$this->assertTrue($x->check_database_version($x->parse_version_string('1.2.3-RC1')));
		$this->assertEquals($x->parse_version_string('1.2.3-RC1'), $x->get_database_version());
	}
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function test_lockingLogic() {
		$x = new upgradeTester();
		$x->lockObj = new cs_lockfile("unittest.lock");
		$x->databaseVersion = '1.2.3';
		
		try {
			$this->assertFalse($x->is_upgrade_in_progress());
			$this->assertTrue($x->set_upgrade_in_progress());
			$this->assertTrue($x->is_upgrade_in_progress());
			$this->assertTrue($x->lockObj->delete_lockfile());
			$this->assertFalse($x->is_upgrade_in_progress());
		}
		catch(Exception $ex) {
			$this->fail(__METHOD__ .": unable to create lock... ". $ex->getMessage());
		}
	}
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function test_exceptionNoVersionData() {
		$x = new upgradeTester();
		$x->db = $this->dbObj;
		$x->projectName = __FUNCTION__;
		
		try {
			$x->allowNoDBVersion = true;
			$this->assertFalse($x->get_database_version());
		}
		catch(Exception $ex) {
			$this->fail("unexpected exception after allowing no version: ". $ex->getMessage());
		}
		
		try {
			$x->allowNoDBVersion = null;
			$x->get_database_version();
			$this->fail("Database version is empty, it should have succeeded");
		}
		catch(Exception $ex2) {
		}
	}
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function test_versionConflict() {
		$x = new upgradeTester();
		$x->db = $this->dbObj;
		
		$initialVersion = '1.2.3';
		$x->versionFileVersion = $initialVersion;
		$x->projectName = __FUNCTION__;
		$x->set_initial_version('1.2.3');
		
		$this->assertFalse($x->check_for_version_conflict());
		$x->versionFileVersion = '1.2.4';
		$this->assertEquals('maintenance', $x->check_for_version_conflict());
		
		$x->versionFileVersion = '1.3.0';
		$this->assertEquals('minor', $x->check_for_version_conflict());
		
		$x->versionFileVersion = '2.0.0';
		$this->assertEquals('major', $x->check_for_version_conflict());
		
		$x->versionFileVersion = '1.2.3-ALPHA1';
		$this->assertEquals('suffix', $x->check_for_version_conflict());
		
		$x->versionFileVerison = '1.2.3-BETA1';
		$this->assertEquals('suffix', $x->check_for_version_conflict());
		
		$x->versionFileVersion = '1.2.3-RC1';
		$this->assertEquals('suffix', $x->check_for_version_conflict());
	}
	//--------------------------------------------------------------------------
	
	
	//TODO: test exceptions about unsupported downgrades...
	
	
	
	
	//--------------------------------------------------------------------------
	public function test_versionFile() {
		$x = new upgradeTester;
		$x->db = $this->dbObj;
		
		foreach($this->fileToVersion as $file => $version) {
			$this->assertTrue(file_exists($file));
			
			$x->versionFileLocation = $file;
			$this->assertEquals($version, $x->read_version_file());
		}
	}//end test_versionFile()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function test_reconnectDb() {
		$x = new upgradeTester;
		$x->db = $this->dbObj;
		$x->projectName = __FUNCTION__;
		
		//
		$this->assertFalse($x->db->get_transaction_status());
		$this->assertTrue($x->set_initial_version('1.0.0'));
		$this->assertEquals($x->parse_version_string('1.0.0'), $x->get_database_version());
		
		$this->assertTrue($x->db->beginTrans());
		$this->assertTrue($x->db->get_transaction_status());
		$this->assertEquals($x->parse_version_string('1.0.0'), $x->get_database_version());
		
		$this->assertTrue($x->db->get_transaction_status());
		$this->assertTrue((bool)$x->update_database_version('2.0.0'));
		$this->assertEquals($x->parse_version_string('2.0.0'), $x->get_database_version());
		
		//now reconnect, and test that the database version is NOT updated.
		$this->assertTrue($x->reconnect_db());
		$this->assertEquals($x->parse_version_string('1.0.0'), $x->get_database_version());
		
	}
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function test_cache() {
		$x = new upgradeTester();
		$x->db = $this->dbObj;
		$x->projectName = __FUNCTION__;
		
		$this->assertEquals(array(), $x->_getCache());
		
		$x->_setCache('test', array(1,2,3));
		$this->assertTrue(is_array($x->_getCache()));
		
		$testThis = array(1,2,3);
		$this->assertEquals($testThis, $x->_getCache('test'));
		
		$x->_setCache(null, null);
		$this->assertEquals(array(), $x->_getCache());
	}
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function test_calls() {
		$x = new upgradeTester();
		
		$this->assertEquals(0, $x->_getCalls());
		$x->_setCalls(9999);
		$this->assertEquals(9999, $x->_getCalls());
	}
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function xtest_normalCall() {
		
		/*
		 * NOTE::: this is a *simulated* normal call, as using cs_webdbupgrade{} 
		 *	directly would not allow testing of protected members.
		 */
		$x = new upgradeTester();
		$x->projectName = __FUNCTION__;
//		$x->set_initial_version('1.0.0');
		$x->doSetup(
				dirname(__FILE__) .'/files/VERSION-1', 
				dirname(__FILE__) .'/files/upgrade.ini',
				$this->dbObj
		);
		
		$this->assertEquals(1, $x->_getCalls());
		$this->assertEquals(__FUNCTION__, $x->projectName);
		$this->assertEquals($x->parse_version_string('1.0.0'), $x->get_database_version());
		$this->assertEquals(1, $x->_getCalls());
	}
	
	
	
	//--------------------------------------------------------------------------
	public function xtest_stuff() {
		$upgObj = new upgradeTester();
		$upgObj->gfObj = new cs_globalFunctions();
		$upgObj->fsObj = new cs_fileSystem(dirname(__FILE__) .'/files');
		
		foreach($this->fileToVersion as $file=>$version) {
			$upgObj->versionFileLocation = $file;
			if($this->assertTrue(file_exists($file))) {
				try {
					$this->assertEquals($version, $upgObj->read_version_file());
				}
				catch(Exception $e) {
					$this->fail("Error reading version file: ". $e->getMessage());
				}
			}
		}
		
		$upgradeConfigFile = dirname(__FILE__). '/files/upgrade.xml';
		
		//$configArr = array('UPGRADE_CONFIG_FILE' => $upgradeConfigFile);
		$upgObj->upgradeConfigFile = $upgradeConfigFile;
		
		#$upgObj->config['UPGRADE_CONFIG_FILE'] = $upgradeConfigFile;
		if($this->assertTrue(file_exists($upgObj->upgradeConfigFile), "Upgrade file (". $upgObj->upgradeConfigFile .") missing")) {
			
			$upgObj->read_upgrade_config_file();
			
			// now make sure things seem to line-up.
			$this->assertEquals($upgObj->initialVersion, "0.1.0", "Initial version didn't match expected version, parsing failed");
			
			#$this->gfObj->debug_var_dump($upgObj->read_upgrade_config_file(),1);
			
			#$this->gfObj->debug_print(new cs_phpxmlparser(file_get_contents($upgradeConfigFile)),1);
		}
		
		#$upgObj->versionFileLocation = dirname(__FILE__) .'/files/VERSION-3';
		
		
		//$upgObj->doSetup();
	}
	//--------------------------------------------------------------------------
	
	
	//--------------------------------------------------------------------------
	/*
	 * This test is pretty ugly: the system in question simply wasn't built to 
	 * be unit-tested, so it needs work... A LOT of work.
	 */
	public function xtest_upgrade() {
				#$this->dbObj->beginTrans();
		$upgObj = new upgradeTester();
		$upgObj->gfObj = new cs_globalFunctions();
		$upgObj->fsObj = new cs_fileSystem(dirname(__FILE__) .'/files');
		$upgObj->db = $this->dbObj;

		// attempt to load the required database table.
		$this->assertTrue($upgObj->load_table() === true, "Failed loading version table");

		$versionFileLocation = dirname(__FILE__) . '/files/VERSION-1';
		$upgObj->set_version_file_location($versionFileLocation);
		$upgObj->doSetup($versionFileLocation, null, $this->dbObj);
		if (!$this->assertEquals($upgObj->versionFileVersion, '1.9.0')) {
			cs_global::debug_print($upgObj, 1);
		}
		/*
		  $upgObj->read_version_file();
		  $versionCheckRes = $upgObj->check_versions(TRUE);
		  $this->assertTrue($versionCheckRes, "Failed result from check_versions (". $versionCheckRes .")");

		  $initialUpgrade = $upgObj->load_initial_version();
		  $this->assertTrue($initialUpgrade, "Failed initial upgrade (". $initialUpgrade .")");
		  $dbVersion = $upgObj->get_database_version();
		  $this->assertEquals($dbVersion['version_string'], '0.1.0');//that's the initial version specified in the upgrade.xml file

		  $versionCheckRes = $upgObj->check_versions(TRUE);
		  $this->assertTrue($versionCheckRes, "Failed result from check_versions (". $versionCheckRes .")");

		  $this->assertEquals(get_class($upgObj->db), 'cs_phpDB');

		  // now make sure we've got the correct version loaded.
		  $dbVersion = $upgObj->get_database_version();
		  $this->assertEquals("1.0.0-RC8000312", $dbVersion['version_string']);
		  $this->assertNotEquals(false, $dbVersion);
		  $versionFileVersion = "1.0.0-RC8000312";
		  $this->assertEquals($versionFileVersion, $upgObj->read_version_file());
		  //$this->dbObj->rollBackTrans();
		  # */

		try {
			$upgObj->db->exec(file_get_contents(dirname(__FILE__) . '/files/destroy_test_db.sql'));
		} catch (Exception $e) {
			// It's all good.  Probably just failed to drop some tables or something.
		}

		try {
			$upgObj->db->rollbackTrans();
		}
		catch(Exception $ex) {
			//nothing to see here
		}
	}//end test_upgrade()
	//--------------------------------------------------------------------------
}


/***
 * Exposes some of the innards of cs_webdbupgrade{}
 */
class upgradeTester extends cs_webdbupgrade {
	
	public function __construct() {
		return;
	}//end __construct()
	
	
	public function doSetup($versionFileLocation, $upgradeConfigFile, cs_phpDB $db = null, $lockFile = 'unittest_upgrade.lock') {
		parent::__construct($versionFileLocation, $upgradeConfigFile, $db, $lockFile);
	}//end doSetup()
	
	
	public function read_version_file() {
		return(parent::read_version_file());
	}//end read_version_file()
	
	
	public function read_upgrade_config_file() {
		return(parent::read_upgrade_config_file());
	}//end read_upgrade_config_file()
	
	
	public function perform_upgrade() {
		parent::perform_upgrade();
	}//end perform_upgrade()
	
	
	public function check_for_version_conflict() {
		return(parent::check_for_version_conflict());
	}//end check_for_version_conflict()
	
	
	public function update_database_version($newVersionString) {
		return(parent::update_database_version($newVersionString));
	}//end update_database_version()
	
	
	public function check_database_version(array $checkThis) {
		return(parent::check_database_version($checkThis));
	}//end check_database_version()
	
	
	public function do_single_upgrade($fromVersion, $toVersion=null) {
		parent::do_single_upgrade($fromVersion, $toVersion);
	}//end do_single_upgrade()
	
	
	public function do_scripted_upgrade(array $upgradeData) {
		parent::do_scripted_upgrade($upgradeData);
	}//end do_scripted_upgrade()
	
	
	public function get_upgrade_list() {
		return(parent::get_upgrade_list());
	}//end get_upgrade_list()
	
	
	public function parse_suffix($suffix) {
		return(parent::parse_suffix($suffix));
	}//end parse_suffix()
	
	
	public function fix_xml_config($config, $path=null) {
		parent::fix_xml_config($config, $path);
	}//end fix_xml_config()
	
	
	public function remove_lockfile() {
		parent::remove_lockfile();
	}//end remove_lockfile()
	
	
	public function get_full_version_string($versionString) {
		return(parent::get_full_version_string($versionString));
	}//end get_full_version_string
	
	
	public function do_log($message, $type) {
		parent::do_log($message, $type);
	}//end do_log()
	
	public function check_versions($doUpgrade=FALSE) {
		parent::check_versions($doUpgrade);
		cs_webdbupgrade::$cache = array();
	}//end check_versions
	
	public function check_internal_upgrades() {
		parent::check_internal_upgrades();
		cs_webdbupgrade::$cache = array();
	}
	
	public function set_initial_version($versionString) {
		return parent::set_initial_version($versionString);
	}
	
	public function reconnect_db() {
		return parent::reconnect_db();
	}
	
	public function _getCache($index=null) {
		if(!is_null($index)) {
			$retval = parent::$cache[$index];
		}
		else {
			$retval = parent::$cache;
		}
		return $retval;
	}
	
	public function _setCache($name, $val) {
		if(is_null($name) && is_null($val)) {
			parent::$cache = array();
		}
		else {
			parent::$cache[$name] = $val;
		}
	}
	
	public function _getCalls() {
		return parent::$calls;
	}
	
	public function _setCalls($val) {
		parent::$calls = $val;
	}
	

// >>>> MAGIC METHODS...
	public function __get($name) {
		return($this->$name);
	}
	public function __set($name, $value) {
		$this->$name = $value;
	}
	public function __isset($name) {
		return(isset($this->$name));
	}
	public function __unset($name) {
		unset($this->$name);
	}
// <<<< MAGIC METHODS....
}

?>
