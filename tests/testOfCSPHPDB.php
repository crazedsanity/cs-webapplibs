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
			if($this->assertFalse($this->dbObj->get_transaction_status(), "Already in transaction...?")) {

				$beginTransRes = $this->dbObj->beginTrans();
				$transactionStatus = $this->dbObj->get_transaction_status();
				$this->assertTrue($transactionStatus);
				$beginTransRes = true;
				if($this->assertTrue($beginTransRes, "Start of transaction failed (". $beginTransRes ."), status=(". $transactionStatus .")")) {
					
					try {
						$this->dbObj->exec('CREATE TABLE test (id serial not null PRIMARY KEY, data text not null);');
						$this->assertTrue($this->dbObj->get_transaction_status(), "Got out of transaction...?");
					}
					catch(Exception $e) {
						
					}
					
					
					// Make sure we get 0 rows before any data has been inserted.
					$numRows = $this->dbObj->run_query("SELECT * FROM test");
					$data = $this->dbObj->farray_fieldnames();
					$this->assertEqual($numRows, count($data), "Invalid number of rows returned: expected (". count($data) ."), got (". $numRows .")");
					$this->assertEqual($numRows, 0, "Returned unexpected number of rows on fresh table (". $numRows .")");
					
					
					$testData = array(
						0 => 'test1', 
						1 => 'test2'
					);
					$i=1;
					$insertTestSql = "INSERT INTO test (data) VALUES (:val)";
					foreach($testData as $val) {
						$createdId = $this->dbObj->run_insert($insertTestSql, array('val'=>$val), 'test_id_seq');
						$this->assertTrue(is_numeric($createdId), "Insert did not yield integer value (". $createdId .")");
						$this->assertEqual($i, $createdId, "Expected Id (". $i .") does not match created id (". $createdId .") for test data (". $val .")");
						$i++;
					}
					
					// now make sure we've got the date expected.
					$numRows = $this->dbObj->run_query("SELECT * FROM test");
					$data = $this->dbObj->farray_fieldnames();
					$this->assertTrue(is_array($data), "Returned data in an invalid format");
					$this->assertEqual($numRows, count($testData), "Invalid number of records created, expected (". count($testData) ."), got (". $numRows .")");
					
					$this->assertTrue(isset($data[0]), "Zeroth index does not exist?");
					$this->assertTrue(isset($data[0]['id']), "ID index missing from returned data");
					$this->assertTrue(isset($data[0]['data']), "DATA index missing from returned data");
					$this->assertEqual($data[0]['id'], 1, "Invalid ID in element 0, expected 1 but got (". $data[0]['id'] .")");
					
					$this->assertEqual($data[1]['id'], 2, "Invalid ID in element 1, expected 2 but got (". $data[1]['id'] .")");
					
					$numRows = $this->dbObj->run_query("SELECT * FROM test");
					$data = $this->dbObj->farray_nvp('id', 'data');
					
					$this->assertEqual("test1", $data[1], "Expected ID 1 to be 'test1', but instead got '". $data[1] ."'");
					$this->assertEqual("test2", $data[2], "Expected ID 2 to be 'test2', but instead got '". $data[2] ."'");
					
					// add a record with a specified ID (retrieving the sequence value will appear to be incorrect, because we're not using it).
					$testData[4] = "test5";
					$createdId = $this->dbObj->run_insert("INSERT INTO test (id, data) VALUES (:id, :val)", array('id'=>5, 'val'=>$testData[4]), 'test_id_seq');
					$this->assertNotEqual($createdId, 5, "Inserting out-of-order index failed, insert ID should have been 2 (not ". $createdId .")");
					
					$numRows = $this->dbObj->run_query("SELECT * FROM test");
					$data = $this->dbObj->farray_nvp('id', 'data');
					$this->assertTrue(is_array($data), "Did not retrieve array of information from database... (". $data .")");
					$this->assertEqual(count($data), count($testData), "Number of records in database (". count($data) .") do not match what is expected (". count($testData) .")");
					
					$testData[2] = "test3";
					$createdId = $this->dbObj->run_insert($insertTestSql, array('val'=>$testData[2]), 'test_id_seq');
					$this->assertEqual($createdId, 3, "Failed to insert ID #3...?");
					$testData[3] = "test4";
					$createdId = $this->dbObj->run_insert($insertTestSql, array('val'=>$testData[3]), 'test_id_seq');
					$this->assertEqual($createdId, 4, "Failed to insert ID #4...?");
					
					
					// Make sure farray_fieldnames works as expected.
					$numRows = $this->dbObj->run_query("SELECT * FROM test");
					$data = $this->dbObj->farray_fieldnames('id');
					
					$this->assertEqual(array('id'=>1, 'data'=>'test1'), $data[1]);
					$this->assertEqual(array('id'=>2, 'data'=>'test2'), $data[2]);
					$this->assertEqual(array('id'=>3, 'data'=>'test3'), $data[3]);
					$this->assertEqual(array('id'=>4, 'data'=>'test4'), $data[4]);
					$this->assertEqual(array('id'=>5, 'data'=>'test5'), $data[5]);
					
					$this->assertEqual(count($data), 5);
					
					
					$this->assertTrue($this->dbObj->commitTrans());
					
					$this->assertTrue($this->dbObj->beginTrans());
					// This illustrates what happens when we attempt to insert a duplicate.
					{
						
						//Okay, here's where there should be an error (re-inserting data that's already there)
						try {
							$createdId = $this->dbObj->run_insert($insertTestSql, array('val'=>$testData[4]), 'test_id_seq');
							$this->assertTrue(false, "DANGER WILL ROBINSON! This should have produced an error!");
						}
						catch(Exception $ex) {
							$errorInfo = $this->dbObj->errorInfo();
							#$this->gfObj->debug_print($ex);
							
							// Make sure it said something about a duplicate key, throw an error if not.
							$this->assertTrue(strstr($errorInfo[2], "duplicate key"), "Error was strange... (". $this->gfObj->debug_print($errorInfo,0));
						}
					}
				}
				else {
					// transaction failed
				}
				$this->assertTrue($this->dbObj->commitTrans());
				
				// make sure we're not in a transaction.
				$this->assertFalse($this->dbObj->get_transaction_status());
				
				
				// Simpler test for farray()
				$numRows = $this->dbObj->run_query("SELECT * FROM test WHERE id > :id ORDER BY id", array('id'=>0));
				$data = $this->dbObj->farray();
				$this->assertTrue($numRows = count($data));
				$this->assertTrue($numRows > 0);
				$this->assertTrue($numRows = 5);
				
				$this->assertEqual($data[0][0], 1);
				$this->assertEqual($data[0][1], 'test1');
				$this->assertEqual($data[0]['id'], 1);
				$this->assertEqual($data[0]['data'], 'test1');
				
				$testElement4 = array(
					0		=> 5,
					'id'	=> 5,
					1		=> 'test5',
					'data'	=> 'test5'
				);
				$this->assertEqual($data[4], $testElement4);
				
				
				// use farray_nvp(), but swap id with value (should work, since values are unique)
				$numRows = $this->dbObj->run_query(
						"SELECT * FROM test WHERE id > :id ORDER BY :orderBy", 
						array('id'=>0, 'orderBy' => 'id')
					);
				$data = $this->dbObj->farray_nvp('data', 'id');
				
				$this->assertEqual($numRows, count($data));
				$this->assertEqual($numRows, 5);
				$this->assertEqual($data['test5'], 5);
				$this->assertEqual($data['test3'], 3);
				
				
				$numRows = $this->dbObj->run_query("SELECT * FROM test WHERE id=:id", array('id'=> 2));
				$data = $this->dbObj->get_single_record();
				
				$this->assertEqual(array('id'=>2, 'data'=>'test2'), $data);
			}
		}
//		
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
