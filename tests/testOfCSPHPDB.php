<?php
/*
 * Created on Jun 12, 2009
 */


class TestOfCSPHPDB extends UnitTestCase {
	
	private $dbParams=array();
	private $dbObjs = array();
	
	
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
	private function check_requirements() {
		$retval=false;
		
		$globalPrefix = 'UNITTEST__';
		
		$requirements = array(
			'dsn'		=> 'DB_DSN',
			'user'		=> 'DB_USERNAME',
			'pass'	=> 'DB_PASSWORD'
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
	private function internal_connect_db($connect=true) {
		if($connect) {
			foreach($this->dbParams as $type=>$config) {
				$this->dbObjs[$type] = new cs_phpDB($config['dsn'], $config['user'], $config['pass']);
			}
		}
		$this->gfObj = new cs_globalFunctions();
	}//end internal_connect_db()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	private function handle_sql($dbType, $sql, array $params = null) {
		if(strlen($dbType) && isset($this->dbObjs[$dbType])) {
			$this->dbObjs[$dbType]->exec($sql);
			
			
			$numrows = $this->dbObjs[$dbType]->numRows();
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
		
		$this->internal_connect_db();
		$this->assertTrue(count($this->dbObjs), "No database objects to test");
		foreach($this->dbObjs as $type => $dbObj) {
			//
			$beginTransRes = $dbObj->beginTrans();
			$transactionStatus = $dbObj->get_transaction_status();
			$beginTransRes = true;
			if($this->assertTrue($beginTransRes, "Start of transaction failed (". $beginTransRes ."), status=(". $transactionStatus .")")) {

				$dbObj->exec('CREATE TABLE test (id serial not null, data text not null);');

				$data = array(
					'test1', 'test2'
				);
				$i=1;
				foreach($data as $val) {
					$createdId = $dbObj->run_insert("INSERT INTO test (data) VALUES (:val)", array('val'=>$val), 'test_id_seq');
					$this->assertTrue(is_numeric($createdId), "Insert did not yield integer value (". $createdId .")");
					$this->assertEqual($i, $createdId, "Expected Id (". $i .") does not match created id (". $createdId .") for test data (". $val .")");
					$i++;
				}
				
			}
			else {
				// transaction failed
			}
			$this->assertTrue($dbObj->rollbackTrans());
		}
	}//end test_transactions()
	//-------------------------------------------------------------------------
	
	
}
?>
