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
			'cswdbl_category_table'		=> array(
												'log_category_table',
												'category_id',
											),
			'cswdbl_class_table'		=> array(
												'log_class_table',
												'class_id',
											),
			'cswdbl_event_table'		=> array(
												'log_event_table',
												'event_id',
											),
			'cswdbl_log_table'			=> array(
												'log_table',
												'log_id'
											)
		);
		
		try {
			/*
			 * NOTE::: this is more complex because cs_webdbupgrade will log stuff into the newly-created tables,
			 * so some categories will already exist, possibly with different ID's.
			 */
			
			foreach($tables as $newTable=>$info) {
				
				$oldTable = $info[0];
				
				$this->db->run_update('TRUNCATE '. $newTable .' CASCADE', true);
				
				$newSequence = $newTable .'_'. $info[1] .'_seq';
				if(isset($info[2])) {
					$sql = "INSERT INTO ". $newTable ." (". $info[3] .") SELECT " 
							. $info[2] ." FROM ". $oldTable ." WHERE ". $info[2] 
							." NOT IN (SELECT ". $info[3] ." FROM ". $newTable .")";
				}
				else {
					$sql = "INSERT INTO ". $newTable ." SELECT * FROM ". $oldTable ."";
				}
				$this->gfObj->debug_print(__METHOD__ .": SQL::: ". $sql,1);
				$this->db->run_update($sql, true);
				
				
				if($this->db->get_dbtype() == 'pgsql') {
					$setSeq = "select setval('". $newSequence ."', (SELECT max(". $info[1] .") FROM ". $newTable ."));";
					$this->db->run_update($setSeq,true);
				}
				elseif($this->db->get_dbtype() == 'mysql') {
					//is there anything to do here?
				}
			}
			
			//now drop the old tables.
			foreach($tables as $newTable=>$info) {
				$oldTable = $info[0];
				$sql = "DROP TABLE ". $oldTable ." CASCADE";
				$this->db->run_update($sql,true);
			}
			$upgradeRes = true;
		}
		catch(exception $e) {
			$this->gfObj->debug_print(__METHOD__ .": upgrade failed::: ". $e->getMessage(),1);
			$upgradeRes = false;
		}
		
		return($upgradeRes);
		
	}//end run_upgrade()
	//=========================================================================
}

?>
