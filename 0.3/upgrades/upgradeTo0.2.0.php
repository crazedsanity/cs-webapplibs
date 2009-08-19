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
		
		return($upgradeRes);
		
	}//end run_upgrade()
	//=========================================================================
}

?>
