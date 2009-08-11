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
												'name',
												'category_name'
											),
			'cswdbl_class_table'		=> array(
												'log_class_table',
												'class_id',
												'name',
												'class_name'
											),
			'cswdbl_event_table'		=> array(
												'log_event_table',
												'event_id',
												'description',
												'description'
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
			
			//TODO: 
			$oldCats = $this->db->run_query("SELECT * FROM log_category_table", 'name', 'log_category_id');
			$newCats = $this->db->run_query("SELECT * FROM cswdbl_category_table", 'category_name', 'category_id');
			foreach($oldCats as $id=>$name) {
				if(!isset($newCats[$name])) {
					$newCats[$name] = $this->db->run_insert("INSERT INTO cswdbl_category_table (category_name) VALUES " .
							"('". $name ."')", 'cswdbl_category_table_category_id_seq');
				}
			}
			
			$oldClass = $this->db->run_query("SELECT * FROM log_class_table", 'name', 'log_class_id');
			$newClass = $this->db->run_query("SELECT * FROM cswdbl_class_table", 'class_name', 'class_id');
			foreach($oldClass as $id=>$name) {
				if(!isset($newClass[$name])) {
					$newClass[$name] = $this->db->run_insert("INSERT INTO cswdbl_class_table (class_name) VALUES " .
							"('". $name ."')", 'cswdbl_class_table_class_id_seq');
				}
			}
			
			//now the tricky part: creating events with the proper class & category.
			$oldEvents = $this->db->run_query("SELECT * FROM log_event_table", 'log_id');
			$newEvents = $this->db->run_query();
			
			
//			$sqlArr = array(
//				"INSERT INTO cswdbl_category_table (category_name) SELECT name FROM log_category_table WHERE name NOT IN (SELECT category_name FROM cswdbl_category_table)",
//				"INSERT INTO cswdbl_class_table (class_name) SELECT name FROM log_class_table WHERE name NOT IN (SELECT class_name FROM cswdbl_class_table)",
//				"INSERT INTO cswdbl_event_table (class_id, description) SELECT description FROM log_event_table WHERE description NOT IN (SELECT description FROM cswdbl_event_table)"
//			);
//			
//			foreach($tables as $newTable=>$info) {
//				$oldTable = $info[0];
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
