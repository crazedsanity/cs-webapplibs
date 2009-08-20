<?php
/*
 * Created on Jun 12, 2009
 */


require_once(dirname(__FILE__) .'/../cs_phpDB.class.php');

class TestOfCSPHPDB extends UnitTestCase {
	
	private $dbParams=array();
	private $dbObjs = array();
	
	
	//-------------------------------------------------------------------------
	public function __construct() {
		$this->gfObj = new cs_globalFunctions;
		
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function setUp() {
		$this->skipUnless($this->check_requirements(), "Skipping database tests, not configured");
	}
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	private function check_requirements() {
		$retval=false;
		
		$globalPrefix = 'UNITTEST__';
		
		$requirements = array(
			'host'		=> 'DB_HOST',
			'user'		=> 'DB_USER',
			'password'	=> 'DB_PASS',
			'dbname'	=> 'DB_NAME'
		);
		
		$dbTypes = array(
			'mysql'	=> "MY_",
			'pgsql'	=> "PG_");
		
		foreach($dbTypes as $type=>$prefix) {
			foreach($requirements as $index => $name) {
				$myIndex = $globalPrefix . $prefix . $name;
				$this->dbParams[$type][$index] = constant($myIndex);
			}
		}
		
		
		
		$validDbs = 0;
		foreach($this->dbParams as $dbType=>$data) {
			if(count($data) >= 4) {
				$validDbs++;
			}
			else {
				$this->gfObj->debug_print(__METHOD__ .": dropping ". $dbType .": not enough params (". count($data) .")");
				unset($this->dbParams[$dbType]);
			}
		}
		
		if($dbTypes >= 1) {
			$retval = true;
		}
		
		return($retval);
	}//end check_requirements()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	private function internal_connect_db($connect=true) {
		$this->dbObjs['pgsql'] = new cs_phpDB('pgsql');
		$this->dbObjs['mysql'] = new cs_phpDB('mysql');
		
		if($connect) {
			$this->dbObjs['pgsql']->connect($this->dbParams['pgsql']);
			$this->dbObjs['mysql']->connect($this->dbParams['mysql']);
		}
		
	}//end internal_connect_db()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	private function handle_sql($dbType, $sql) {
		if(strlen($dbType) && isset($this->dbObjs[$dbType])) {
			$this->dbObjs[$dbType]->exec($sql);
			
			
			$numrows = $this->dbObjs[$dbType]->numRows();
			if(!$numrows) {
				$numrows = $this->dbObjs[$dbType]->numAffected();
			}
			$dberror = $this->dbObjs[$dbType]->errorMsg();
			
			if(strlen($dberror) || !is_numeric($numrows) || $numrows < 0) {
				$retval = false;
			}
			else {
				$retval = $numrows;
			}
		}
		else {
			$this->gfObj->debug_print($this);
			throw new exception(__METHOD__ .": invalid dbType (". $dbType .")");
		}
		
		return($retval);
		
	}//end handle_sql();
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function test_transactions() {
		$this->assertTrue(true);
		$this->skipUnless($this->check_requirements(), "Skipping transaction tests (not configured: ". $this->check_requirements() .")");

		$this->internal_connect_db();
		//
		$beginTransRes = $this->dbObjs['pgsql']->beginTrans();
		$transactionStatus = $this->dbObjs['pgsql']->get_transaction_status();
		$beginTransRes = true;
		if($this->assertTrue($beginTransRes, "Start of transaction failed (". $beginTransRes .")")) {
			
			$createRes = $this->handle_sql('pgsql', 'CREATE TABLE test (id serial not null, data text not null);');
			$this->assertTrue($createRes, "failed to create table (". $createRes .") -- affected: (". $this->dbObjs['pgsql']->numAffected() .")");
			
			$data = array(
				'test1', 'test2'
			);
			$i=1;
			foreach($data as $val) {
				#$this->assertTrue($this->handle_sql('pgsql', "INSERT INTO test (data) VALUES ('". $val ."')"));
				#$this->assertEqual($i, $this->dbObjs['pgsql']->lastID());
			}
			
			$this->assertTrue($this->handle_sql('pgsql', 'ROLLBACK'));
		}
		else {
		}
	}//end test_transactions()
	//-------------------------------------------------------------------------
	
	
}
?>
