<?php

//TODO: make this work for more than just PostgreSQL.
abstract class testDbAbstract extends PHPUnit_Framework_TestCase {
	
	public $dbParams=array();
	public $dbObj = null;
	protected $lock = null;
	protected $dsn = "pgsql:host=localhost;dbname=_unittest_";
	protected $user = "postgres";
	protected $pass = null;
	
	//-------------------------------------------------------------------------
	public function __construct() {
		$this->gfObj = new cs_globalFunctions;
		$this->lock = new cs_lockfile(constant('UNITTEST__LOCKFILE'));
		
		$this->tearDown(); //make sure the database is truly in a consistent state
		$this->setUp();
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function skip() {
		$this->skipUnless($this->check_lockfile(), "Lockfile missing (". $this->lock->get_lockfile() ."): create one BEFORE database-related tests occur.");
		$this->skipUnless($this->check_requirements(), "Skipping tests for '". $this->getLabel() ."', database not configured");
	}
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function check_lockfile() {
		$retval = false;
		
		if($this->lock->is_lockfile_present()) {
			$retval = true;
		}
		
		return($retval);
	}//end check_lockfile()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function check_requirements() {
		// TODO: make *sure* to stop if there's a lockfile from cs_webdbupgrade.
		
		$retval=false;
		
		if($this->lock->is_lockfile_present()) {
			$retval = true;
		}
		else {
			$this->gfObj->debug_print(__METHOD__ .": lockfile missing (". $this->lock->get_lockfile() .") while attempting to run test '". $this->getLabel() ."'");
		}
		
		return($retval);
	}//end check_requirements()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function setUp() {
		$this->internal_connect_db();
	}//end setUp()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function tearDown() {
		$this->reset_db();
	}//end tearDown()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function internal_connect_db() {
		if(!is_object($this->dbObj)) {
			$this->dbObj = new cs_phpDB($this->dsn, $this->user, $this->pass);
		}
		$this->gfObj = new cs_globalFunctions();
	}//end internal_connect_db()
	//-------------------------------------------------------------------------
	
	
	
	//-----------------------------------------------------------------------------
	public function reset_db($schemaFile=null) {
		$retval = false;
		
		if(!is_null($schemaFile) && !file_exists($schemaFile)) {
			throw new exception(__METHOD__ .": schema file (". $schemaFile .") does not exist");
		}
		
		try {
			$this->internal_connect_db();
			if($this->dbObj->get_transaction_status() == 1) {
				$this->dbObj->rollbackTrans();
			}
			$this->dbObj->beginTrans();

			$this->dbObj->run_query("DROP SCHEMA public CASCADE");
			$this->dbObj->run_query("CREATE SCHEMA public AUTHORIZATION " . $this->user);

			if (!is_null($schemaFile)) {
				$this->dbObj->run_sql_file($schemaFile);
			}

			$this->dbObj->commitTrans();

			$retval = true;
		} catch (Exception $e) {
			$this->dbObj->rollbackTrans();
			throw $e;
		}
		return ($retval);
		
	}//end create_db()
	//-----------------------------------------------------------------------------
	
	
	
	//-----------------------------------------------------------------------------
	public function __destruct() {
		#$this->destroy_db();
		$this->tearDown();
	}//end __destruct()
	//-----------------------------------------------------------------------------



}//end testDbAbstract{}

?>
