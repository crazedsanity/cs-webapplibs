<?php

class upgrade_to_0_2_0 extends cs_webdbupgrade {
	
	//=========================================================================
	public function __construct(cs_phpDB &$db) {
		if(!$db->is_connected()) {
			throw new exception(__METHOD__ .": database is not connected");
		}
		$this->db = $db;
		
		$this->gfObj = new cs_globalFunctions;
		$this->gfObj->debugPrintOpt = 1;
		
		//make sure there's enough info to use.
		$requiredConstants = array(
			'table'	=> 'cs_webdbupgrade-DB_TABLE',
			'pkey'	=> 'cs_webdbupgrade-DB_PRIMARYKEY',
			'seq'	=> 'cs_webdbupgrade-DB_SEQUENCE'
		);
		
		$this->dbStuff = array();
		foreach($requiredConstants as $k=>$v) {
			if(defined($v)) {
				$this->dbStuff[$k] = constant($v);
			}
			else {
				throw new exception(__METHOD__ .": missing required constant (". $v .")");
			}
		}
		
		
	}//end __construct()
	//=========================================================================
	
	
	
	//=========================================================================
	public function run_upgrade() {
		
		$dropColumns = array('version_major', 'version_minor', 'version_maintenance', 'version_suffix');
		foreach($dropColumns as $col) {
			$sql = "ALTER TABLE ". $this->dbStuff['table'] ." DROP COLUMN ". $col;
			$this->db->run_update($sql, true);
		}
		
		return(true);
	}//end run_upgrade()
	//=========================================================================
}

?>
