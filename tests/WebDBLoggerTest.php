<?php

require_once(dirname(__FILE__) .'/../abstract/testDb.abstract.class.php');
require_once(dirname(__FILE__) .'/../abstract/cs_webapplibs.abstract.class.php');
require_once(dirname(__FILE__) .'/../cs_webdblogger.class.php');

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
//		parent::tearDown();
	}//end tearDown()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function test_basic_functions() {
		$log = new _logTester($this->dbObj);
		
		$log->build_cache();
		$myCache = $log->logClassCache;
		
		$this->assertTrue(is_array($myCache));
		$this->assertEquals(count($myCache), 0, "Expected no categories, found some::: ". $this->gfObj->debug_print($myCache,0));
		
		$categoryName = "TEST";
		
		$myCatId = $log->create_log_category($categoryName);
		$this->assertTrue(is_numeric($myCatId), "No category created, or invalid data returned (". $myCatId .")");
		$log->logCategoryId = $myCatId;
		
		$checkCatId = $log->get_category_id($categoryName);
		$this->assertEquals($myCatId, $checkCatId, "Duplicate category created");
		
		$className = "TESTING";
		$classId = $log->create_class($className);
		$this->assertTrue(is_numeric($classId), "Failed to create class, or invalid data returned (". $classId .")");
		
		$checkClassId = $log->get_class_id($className);
		$this->assertEquals($classId, $checkClassId, "Duplicate class created");
		
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
		
		$this->assertEquals($numRows, count($data), "Invalid number of rows returned: expected(". count($data) ."), got (". $numRows .")");
		

	}//end test_basic_functions()
	//--------------------------------------------------------------------------
	
	
	
	public function test_get_logs() {
		$x = new cs_webdblogger($this->dbObj, __CLASS__);
		
		// create some logs to search through.
//		$x->log_by_class(__METHOD__, "error");
		
		$createRecords = array(
			'MAIN'	=> array(
				'first',
				'second',
				'third',
				'fourth',
			),
			'xxx'		=> array(
				'fifth',
				'sixth',
			),
			'error'		=> array(
				'seventh',
			),
			'another'	=> array(
				'EigTh',
			)
		);
		
		$totalRecords = 0;
		$testRecords = array();
		$byClass = array();
		
		foreach($createRecords as $class=> $list) {
			foreach($list as $details) {
				$id = $x->log_by_class($details, $class);
				
				$this->assertTrue(is_numeric($id));
				
				$testRecords[$id] = $details;
				
				if(isset($byClass[$class])) {
					$byClass[$class]++;
				}
				else {
					$byClass[$class] = 1;
				}
				$totalRecords++;
			}
		}
		
		foreach(array_keys($createRecords) as $class) {
			$theLogs = $x->get_logs($class);
			$this->assertEquals(count($createRecords[$class]), count($theLogs), "Failed to find logs that match '". $class ."'... ". cs_global::debug_print($theLogs));
		}
		
//		$data = $x->get_logs(null);
//		
//		$this->assertEquals(1, count($data), cs_global::debug_print($data,1));
//		
//		$this->assertEquals(1, count($x->get_logs('test')));
	}
	
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
