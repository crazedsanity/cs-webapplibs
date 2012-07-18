<?php

//TODO: make this work for more than just PostgreSQL.
abstract class testDbAbstract extends UnitTestCase {
	
	public $dbParams=array();
	public $dbObjs = array();
	
	
	//-------------------------------------------------------------------------
	public function __construct() {
		$this->gfObj = new cs_globalFunctions;
		
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function skip() {
		$this->skipUnless($this->check_requirements(), "Skipping database tests, not configured");
	}
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function check_requirements() {
		$retval=false;
		
		$globalPrefix = 'UNITTEST__';
		
		$requirements = array(
			'dsn'		=> 'DB_DSN',
			'user'		=> 'DB_USERNAME',
			'pass'		=> 'DB_PASSWORD'
		);
		
		//TODO: add mysql to this list (someday)
		$dbTypes = array(
			'pgsql'	=> "PG_");
		
		foreach($dbTypes as $type=>$prefix) {
			foreach($requirements as $index => $name) {
				$myIndex = $globalPrefix . $prefix . $name;
				if(defined($myIndex)) {
					$this->dbParams[$type][$index] = constant($myIndex);
				}
				else {
					$this->gfObj->debug_print(__METHOD__ .": missing required index (". $myIndex .")",1);
				}
			}
		}
		
		
		
		$validDbs = 0;
		foreach($this->dbParams as $dbType=>$data) {
			if(count($data) >= count($requirements)) {
				$validDbs++;
			}
			else {
				$this->gfObj->debug_print(__METHOD__ .": dropping ". $dbType .": not enough params (". count($data) .")");
				unset($this->dbParams[$dbType]);
			}
		}
		
		if($validDbs >= 1) {
			$retval = true;
		}
		
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
		foreach($this->dbParams as $type=>$config) {
			$this->dbObjs[$type] = new cs_phpDB($config['dsn'], $config['user'], $config['pass']);
		}
		$this->gfObj = new cs_globalFunctions();
	}//end internal_connect_db()
	//-------------------------------------------------------------------------
	
	
	
	//-----------------------------------------------------------------------------
	public function create_db() {
		$myDbName = strtolower(__CLASS__ .'_'. preg_replace('/\./', '', microtime(true)));
		$this->templateDb = new cs_phpDB($this->config['dsn']. 'template1', $this->config['username'], $this->config['password']);
		
		$this->templateDb->exec("CREATE DATABASE ". $myDbName);
		$this->templateDb = null;
		
		//now run the SQL file.
		$this->db = new cs_phpdb($this->config['dsn']. $myDbName, $this->config['username'], $this->config['password']);
		$this->db->run_sql_file(dirname(__FILE__) .'/../tests/files/test_db.sql');
	}//end create_db()
	//-----------------------------------------------------------------------------
	
	
	
	//-----------------------------------------------------------------------------
	public function destroy_db() {
		$this->db->close();
		$this->templateDb->exec("DROP DATABASE ". $this->config['dbname']);
	}//end destroy_db()
	//-----------------------------------------------------------------------------
	
	
	
	//-----------------------------------------------------------------------------
	public function __destruct() {
		#$this->destroy_db();
	}//end __destruct()
	//-----------------------------------------------------------------------------



}//end testDbAbstract{}

?>
