<?php

class upgrade_to_0_2_0 {
	
	//=========================================================================
	public function __construct(cs_phpDB &$db) {
		if(!$db->is_connected()) {
			throw new exception(__METHOD__ .": database is not connected");
		}
		$this->db = $db;
		
		$this->gfObj = new cs_globalFunctions;
		$this->gfObj->debugPrintOpt = 1;
		
	}//end __construct()
	//=========================================================================
	
	
	
	//=========================================================================
	public function run_upgrade() {
		$this->gfObj->debug_print(__METHOD__ .": STARTING... ", 1);
		
		
		$upgradeFilename = dirname(__FILE__) ."/sql/upgradeTo0_2_0.". $this->db->get_dbtype() .".sql";
		if(!file_exists($upgradeFilename)) {
			throw new exception(__METHOD__ .": missing upgrade filename (". $upgradeFilename ."), " .
					"probably due to unsupported database type (". $this->db->get_dbtype() .")");
		}
		
		
		$tables = array(
			'cat'	=> array(
						'cswdbl_category_table',
						'log_category_table',
						'category_id'
					),
			'class'	=> array(
						'cswdbl_class_table',
						'log_class_table',
						'class_id'
					),
			'event'	=> array(
						'cswdbl_event_table',
						'log_event_table',
						'event_id'
					),
			'log'	=> array(
						'cswdbl_log_table',
						'log_table',
						'log_id'
					)
			);
		
		//run the SQL file.
		$file = dirname(__FILE__) .'/sql/upgradeTo0_2_0.'. $this->db->get_dbtype() .'.sql';
		$upgradeRes = false;
		if(file_exists($file)) {
			$this->db->run_update(file_get_contents($file),true);
			$totalToSync = count($tables);
			$totalSynced = 0;
			
			//now make sure there's the same amount of data in BOTH tables.
			foreach($tables as $key => $tables) {
				$c1 = $this->db->run_query("SELECT * FROM ". $tables[0]);
				$num1 = $this->db->numRows();
				$c2 = $this->db->run_query("SELECT * FROM ". $tables[1]);
				$num2 = $this->db->numRows();
				
				if($num1 === $num2) {
					$totalSynced++;
					$this->db->run_update("DROP TABLE ". $tables[1] ." CASCADE", true);
					
					if($this->db->get_dbtype() == 'pgsql') {
						//Update the sequence...
						$seq = $tables[0] .'_'. $tables[2] .'_seq';
						$this->db->run_update("SELECT setval('". $seq ."', (SELECT max(". $tables[2] .") FROM ". $tables[0] ."))",true);
					}
				}
				else {
					throw new exception(__METHOD__ .": failed to sync ". $tables[0] ." with ". $tables[1] ." (". $num1 ." != ". $num2 .")");
				}
			}
			$upgradeRes = true;
		}
		else {
			throw new exception(__METHOD__ .": missing upgrade SQL file (". $file .")");
		}
		
//		try {
//			/*
//			 * NOTE::: this is more complex because cs_webdbupgrade will log stuff into the newly-created tables,
//			 * so some categories will already exist, possibly with different ID's.
//			 */
//			
//			foreach($tables as $newTable=>$info) {
//				
//				$oldTable = $info[0];
//				
//				$this->db->run_update('TRUNCATE '. $newTable .' CASCADE', true);
//				
//				$newSequence = $newTable .'_'. $info[1] .'_seq';
//				if(isset($info[2])) {
//					$sql = "INSERT INTO ". $newTable ." (". $info[3] .") SELECT " 
//							. $info[2] ." FROM ". $oldTable ." WHERE ". $info[2] 
//							." NOT IN (SELECT ". $info[3] ." FROM ". $newTable .")";
//				}
//				else {
//					$sql = "INSERT INTO ". $newTable ." SELECT * FROM ". $oldTable ."";
//				}
//				$this->gfObj->debug_print(__METHOD__ .": SQL::: ". $sql,1);
//				$this->db->run_update($sql, true);
//				
//				
//				if($this->db->get_dbtype() == 'pgsql') {
//					$setSeq = "select setval('". $newSequence ."', (SELECT max(". $info[1] .") FROM ". $newTable ."));";
//					$this->db->run_update($setSeq,true);
//				}
//				elseif($this->db->get_dbtype() == 'mysql') {
//					//is there anything to do here?
//				}
//			}
//			
//			//now drop the old tables.
//			foreach($tables as $newTable=>$info) {
//				$oldTable = $info[0];
//				$sql = "DROP TABLE ". $oldTable ." CASCADE";
//				$this->db->run_update($sql,true);
//			}
//			$upgradeRes = true;
//		}
//		catch(exception $e) {
//			$this->gfObj->debug_print(__METHOD__ .": upgrade failed::: ". $e->getMessage(),1);
//			$upgradeRes = false;
//		}
		$this->gfObj->debug_print(__METHOD__ .": FINISHED ", 1);
//		
		return($upgradeRes);
		
	}//end run_upgrade()
	//=========================================================================
}

?>
