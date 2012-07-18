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
		
	}//end test_basic_functions()
	//--------------------------------------------------------------------------
	
	
	
	
}


/***
 * Exposes some of the innards of cs_webdbupgrade{}
 */
class upgradeTester extends cs_webdbupgrade {
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