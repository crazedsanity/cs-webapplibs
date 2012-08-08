<?php
/*
 * Created on Jun 12, 2009
 */


class TestOfCSPHPDB extends testDbAbstract {
	
	
	//-------------------------------------------------------------------------
	public function __construct() {
		parent::__construct();
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	//-------------------------------------------------------------------------
	public function test_basics() {
		
		$this->assertTrue(is_object($this->dbObj), "No database objects to test");
		
		$type = 'pgsql';
		$this->assertEqual($type, $this->dbObj->get_dbType(), "Database type mismatch, expecting (". $type ."), got (". $this->dbObj->get_dbType() .")");
		
		//
		if($this->assertTrue($this->reset_db(dirname(__FILE__) .'/../setup/schema.pgsql.sql'), "Failed to reset database")) {

			$beginTransRes = $this->dbObj->beginTrans();
			$transactionStatus = $this->dbObj->get_transaction_status();
			$beginTransRes = true;
			if($this->assertTrue($beginTransRes, "Start of transaction failed (". $beginTransRes ."), status=(". $transactionStatus .")")) {

				$this->dbObj->exec('CREATE TABLE test (id serial not null, data text not null);');

				$data = array(
					'test1', 'test2'
				);
				$i=1;
				foreach($data as $val) {
					$createdId = $this->dbObj->run_insert("INSERT INTO test (data) VALUES (:val)", array('val'=>$val), 'test_id_seq');
					$this->assertTrue(is_numeric($createdId), "Insert did not yield integer value (". $createdId .")");
					$this->assertEqual($i, $createdId, "Expected Id (". $i .") does not match created id (". $createdId .") for test data (". $val .")");
					$i++;
				}

			}
			else {
				// transaction failed
			}
		}
		$this->assertTrue($this->dbObj->rollbackTrans());
		
		// test to see that old-school SQL works...
		$numRows = $this->dbObj->run_query('SELECT (CURRENT_TIMESTAMP = CURRENT_TIMESTAMP) as date_test, CURRENT_TIMESTAMP as date;');
		$this->assertEqual($numRows, 1, "Expected one row, actually returned (". $numRows .")");
		
		
		$data = $this->dbObj->farray_fieldnames();
		$this->assertTrue(isset($data[0]), "Data does not contain zero-based index...");
		$this->assertEqual(count($data), 1, "Returned too many records, or mal-formed array");
		$this->assertTrue($data[0]['date_test'], "Current date doesn't match in database or mal-formed array");
		$this->assertEqual(count($data[0]), 2, "Too many values beneath index 0");
		
		$dateString = strftime('%Y-%m-%d');
		$this->assertTrue(preg_match('/^'. $dateString .'/', $data[0]['date']), "Date in database is invalid or malformed: (". $data[0]['date'] ." does not start with '". $dateString ."')");
		
		
	}//end test_basics()
	//-------------------------------------------------------------------------
	
	
}
?>
