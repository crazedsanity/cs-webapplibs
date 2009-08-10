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
		
	}//end run_upgrade()
	//=========================================================================
}

?>
