<?php

class upgrade_to_0_2_1 {
	
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
		
		
		$upgradeFilename = dirname(__FILE__) ."/sql/upgradeTo0_2_1.". $this->db->get_dbtype() .".sql";
		if(!file_exists($upgradeFilename)) {
			throw new exception(__METHOD__ .": missing upgrade filename (". $upgradeFilename ."), " .
					"probably due to unsupported database type (". $this->db->get_dbtype() .")");
		}
		
		//run the SQL file.
		$file = dirname(__FILE__) .'/sql/upgradeTo0_2_1.'. $this->db->get_dbtype() .'.sql';
		$upgradeRes = false;
		if(file_exists($file)) {
			$this->db->run_update(file_get_contents($file),true);
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
