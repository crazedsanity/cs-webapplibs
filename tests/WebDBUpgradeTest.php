<?php

class testOfCSWebDbUpgrade extends testDbAbstract {
	
	
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
		
		$this->reset_db();
		parent::setUp();
	}//end setUp()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function tearDown() {
		parent::tearDown();
	}//end tearDown()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function test_basic_functions() {
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
		
	}//end test_basic_functions()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function test_stuff() {
		$upgObj = new upgradeTester();
		$upgObj->gfObj = new cs_globalFunctions();
		$upgObj->fsObj = new cs_fileSystem(dirname(__FILE__) .'/files');
		
		$fileToVersion = array(
			dirname(__FILE__). '/files/VERSION-1'	=> "1.0.0-RC8000312",
			dirname(__FILE__) .'/files/VERSION-2'	=> "1.9.0",
			dirname(__FILE__) .'/files/VERSION-3'	=> "8.0.4003-RC2"
		);
		
		foreach($fileToVersion as $file=>$version) {
			$upgObj->versionFileLocation = $file;
			if($this->assertTrue(file_exists($file))) {
				try {
					$this->assertEquals($version, $upgObj->read_version_file());
				}
				catch(Exception $e) {
					//yeah...
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
	}//end test_stuff()
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
		$upgObj->doSetup($versionFileLocation, $upgradeConfigFile, $this->dbObj);
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
	
	
	public function get_database_version() {
		return(parent::get_database_version());
	}//end get_database_version()
	
	
	public function update_database_version($newVersionString) {
		return(parent::update_database_version($newVersionString));
	}//end update_database_version()
	
	
	public function check_database_version() {
		return(parent::check_database_version());
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