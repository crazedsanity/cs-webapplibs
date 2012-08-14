<?php

class testOfCSWebDbLogger extends testDbAbstract {
	
	
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
	public function test_basic_functions() {
		$log = new _logTester($this->dbObj);
		
		$log->build_cache();
		$myCache = $log->logClassCache;
		
		$this->assertTrue(is_array($myCache));
		$this->assertEqual(count($myCache), 0, "Expected no categories, found some::: ". $this->gfObj->debug_print($myCache,0));
		
		$categoryName = "TEST";
		
		$myCatId = $log->create_log_category($categoryName);
		$this->assertTrue(is_numeric($myCatId), "No category created, or invalid data returned (". $myCatId .")");
		$log->logCategoryId = $myCatId;
		
		$checkCatId = $log->get_category_id($categoryName);
		$this->assertEqual($myCatId, $checkCatId, "Duplicate category created");
		
		$className = "TESTING";
		$classId = $log->create_class($className);
		$this->assertTrue(is_numeric($classId), "Failed to create class, or invalid data returned (". $classId .")");
		
		$checkClassId = $log->get_class_id($className);
		$this->assertEqual($classId, $checkClassId, "Duplicate class created");
		
//		$logId = $log->log_by_class("Just a test", $className, 0);
//		$this->assertTrue(is_numeric($logId), "No log ID created");
		
		$params = array(
			'classId'		=> $classId,
			'categoryId'	=> $myCatId
		);
		$sql = "SELECT event_id FROM cswal_event_table WHERE " .
			"class_id=:classId AND category_id=:categoryId";
		
		$numRows = $log->db->run_query($sql, $params);
		$data = $log->db->farray_fieldnames();
		
		$this->gfObj->debug_print($data,1);
		
		$this->assertEqual($numRows, count($data), "Invalid number of rows returned: expected(". count($data) ."), got (". $numRows .")");
		

	}//end test_basic_functions()
	//--------------------------------------------------------------------------
	
	
}

class _logTester extends cs_webdblogger {
	public function __construct(cs_phpDB $db) {
		$this->db = $db;
		$this->gfObj = new cs_globalFunctions();
	}
	
	public function init(cs_phpDB $db, $logCategory=null, $checkForUpgrades=true) {
		parent::__construct($db, $logCategory, $checkForUpgrades);
	}
	
	public function build_cache() {
		parent::build_cache();
	}
	
	public function get_class_id($name) {
		return(parent::get_class_id($name));
	}
	
	public function get_event_id($logClassName) {
		return(parent::get_event_id($logClassName));
	}
	
	public function auto_insert_record($logClassId) {
		return(parent::auto_insert_record($logClassId));
	}
	
	public function get_category_id($catName) {
		return(parent::get_category_id($catName));
	}
	
	public function create_log_category($catName) {
		return(parent::create_log_category($catName));
	}
	
	public function create_class($className) {
		return(parent::create_class($className));
	}
	
	public function get_class_name($classId) {
		return(parent::get_class_name($classId));
	}
	
	public function get_category_name($categoryId) {
		return(parent::get_category_name($categoryId));
	}
	
	public function create_attribute($attribName, $buildCache = true) {
		return(parent::create_attribute($attribName, $buildCache));
	}
	
	public function create_log_attributes($logId, array $attribs) {
		return(parent::create_log_attributes($logId, $attribs));
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