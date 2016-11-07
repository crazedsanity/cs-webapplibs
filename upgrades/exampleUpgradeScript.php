<?php

use crazedsanity\database\Database;
use crazedsanity\core\ToolBox;

//    upgrade_to_0_4_1
class upgrade_to_0_4_1 {
	
	private $logsObj;
	
	//=========================================================================
	public function __construct(Database $db) {
		if(!$db->is_connected()) {
			throw new exception(__METHOD__ .": database is not connected");
		}
		$this->db = $db;
	}//end __construct()
	//=========================================================================
	
	
	
	//=========================================================================
	public function run_upgrade() {
		
		// Check if there's an existing auth table...
		$doSecondarySql = false;
		$this->db->beginTrans(__METHOD__);
		
		
		ToolBox::debug_print(__METHOD__ .": running SQL file...");
		$this->db->run_sql_file(dirname(__FILE__) .'/schemaChangesFor_0.4.1.sql');
		
		if($doSecondarySql) {
			ToolBox::debug_print(__METHOD__ .": running SQL file...");
			$this->db->run_sql_file(dirname(__FILE__) .'/schemaChangesFor_0.4.1__existingAuthTable.sql');
		}
		
		$this->db->commitTrans(__METHOD__);
		
		return(true);
	}//end run_upgrade()
	//=========================================================================
}

?>
