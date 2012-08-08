<?php

//TODO: make this work for more than just PostgreSQL.
abstract class testDbAbstract extends UnitTestCase {
	
	public $dbParams=array();
	public $dbObj = array();
	
	
	//-------------------------------------------------------------------------
	public function __construct() {
		$this->gfObj = new cs_globalFunctions;
		
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function skip() {
		$this->skipUnless($this->check_requirements(), "Skipping tests for '". $this->getLabel() ."', database not configured");
	}
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function check_requirements() {
		// TODO: make *sure* to stop if there's a lockfile from cs_webdbupgrade.
		
		$retval=false;
		
		$globalPrefix = 'UNITTEST__';
		
		$requirements = array(
			'dsn'		=> 'DB_DSN',
			'user'		=> 'DB_USERNAME',
			'pass'		=> 'DB_PASSWORD'
		);
		
		foreach($requirements as $index => $name) {
			$myIndex = $globalPrefix . $name;
			if(defined($myIndex)) {
				$this->dbParams[$index] = constant($myIndex);
			}
			else {
				#$this->gfObj->debug_print(__METHOD__ .": missing required index (". $myIndex .")",1);
			}
		}
		
		
		if(count($this->dbParams) == count($requirements)) {
			$retval = true;
		}
		
		#$this->gfObj->debug_print($this->dbParams,1);
		#$this->gfObj->debug_print("RESULT: (". (count($this->dbParams) == count($requirements)) .")", 1);
		
		return($retval);
	}//end check_requirements()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function setUp() {
		$this->internal_connect_db();
	}//end setUp()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function tearDown() {
		
	}//end tearDown()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function internal_connect_db() {
		if(!is_object($this->dbObj)) {
			$this->dbObj = new cs_phpDB($this->dbParams['dsn'], $this->dbParams['user'], $this->dbParams['pass']);
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
			$this->dbObj->beginTrans();

			$this->dbObj->run_query("DROP SCHEMA public CASCADE");
			$this->dbObj->run_query("CREATE SCHEMA public AUTHORIZATION " . $this->dbParams['user']);

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
	}//end __destruct()
	//-----------------------------------------------------------------------------



}//end testDbAbstract{}

?>
