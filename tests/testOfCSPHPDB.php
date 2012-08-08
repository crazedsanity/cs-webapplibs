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
		
		$this->assertEqual($type, $this->dbObj->get_dbType(), "Database type mismatch, expecting (". $type ."), got (". $this->dbObj->get_dbType() .")");
		
		//
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
		$this->assertTrue($this->dbObj->rollbackTrans());
	}//end test_basics()
	//-------------------------------------------------------------------------
	
	
}
?>
