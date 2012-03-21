<?php

class upgrade_to_1_2_0_ALPHA7 extends dbAbstract {
	
	private $logsObj;
	
	//=========================================================================
	public function __construct(cs_phpDB &$db) {
		if(!$db->is_connected()) {
			throw new exception(__METHOD__ .": database is not connected");
		}
		$this->db = $db;
		
		$this->logsObj = new logsClass($this->db, 'Upgrade');
		
		$this->gfObj = new cs_globalFunctions;
		$this->gfObj->debugPrintOpt = 1;
	}//end __construct()
	//=========================================================================
	
	
	
	//=========================================================================
	public function run_upgrade() {
		
		$this->db->beginTrans(__METHOD__);
		
		
		$this->run_schema_changes();
		
		
		$this->db->commitTrans(__METHOD__);
		
		return('Upgrade complete');
	}//end run_upgrade()
	//=========================================================================
	
	
	
	//=========================================================================
	private function run_schema_changes() {
		
		$this->gfObj->debug_print(__METHOD__ .": running SQL file...");
		$this->run_sql_file(dirname(__FILE__) .'/../docs/sql/upgrades/upgradeTo1.2.0-ALPHA7.sql');
		
		$details = "Executed SQL file, '". $this->lastSQLFile ."'.  Encoded contents::: ". 
			base64_encode($this->fsObj->read($this->lastSQLFile));
		$this->logsObj->log_by_class($details, 'system');
		
	}//end run_schema_changes()
	//=========================================================================
}

?>
