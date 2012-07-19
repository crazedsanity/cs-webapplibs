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
		
		$upgObj = new upgradeTester();
		$this->skipUnless($this->assertFalse($upgObj->upgrade_in_progress()), "An upgrade is already in progress....???");
		
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
			$this->assertEqual(count($vArr), 5, "Version info (". $testThis .") has unexpected number of elements (". count($vArr) .")");
		}
		
		// enumerate each piece of a fairly static version string, make sure it works.
		$vArr = $upgObj->parse_version_string("1.2.3-BETA12");
		$this->assertEqual($vArr['version_string'], '1.2.3-BETA12');
		$this->assertEqual($vArr['version_major'], '1');
		$this->assertEqual($vArr['version_minor'], '2');
		$this->assertEqual($vArr['version_maintenance'], '3');
		$this->assertEqual($vArr['version_suffix'], 'BETA12');
		
		
		// use a condensed version string, make sure it still passes.
		$vArr = $upgObj->parse_version_string("1.2");
		$this->assertEqual($vArr['version_string'], '1.2');
		$this->assertEqual($vArr['version_major'], '1');
		$this->assertEqual($vArr['version_minor'], '2');
		$this->assertEqual($vArr['version_maintenance'], '');
		$this->assertEqual($vArr['version_suffix'], '');
		
		
		// Parse some suffixes, make sure they're okay.
		$testArray = array("BETA1", "ALPHA2", "RC3");
		foreach($testArray as $suffix) {
			$vArr = $upgObj->parse_suffix($suffix);
			
			$this->assertEqual(count($vArr), 2, "Invalid number of returned elements in (". $suffix .")");
			
			$this->assertTrue(isset($vArr['type']), "Missing index 'type'");
			$this->assertTrue(isset($vArr['number']), "Missing index 'number'");
			
			$this->assertFalse(is_numeric($vArr['type']), "Type (". $vArr['type'] .") appears to be numeric?");
			$this->assertTrue(is_numeric($vArr['number']), "Number (". $vArr['number'] .") isn't a number...?");
		}
		
	}//end test_basic_functions()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function test_load_schema() {
		
	}//end test_load_schema()
	//--------------------------------------------------------------------------
}


/***
 * Exposes some of the innards of cs_webdbupgrade{}
 */
class upgradeTester extends cs_webdbupgrade {
	
	public function __construct() {
		return;
	}//end __construct()
	
	
	public function doSetup($versionFileLocation, $upgradeConfigFile, cs_phpDB $db = null, $lockFile = 'upgrade.lock') {
		parent::__construct($versionFileLocation, $upgradeConfigFile, $db, $lockFile);
	}//end doSetup()
	
	
	public function read_version_file() {
		return(parent::read_version_file());
	}//end read_version_file()
	
	
	public function read_upgrade_config_file() {
		parent::read_upgrade_config_file();
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