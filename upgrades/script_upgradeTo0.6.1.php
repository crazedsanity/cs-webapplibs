<?php

use crazedsanity\database\Database;
use crazedsanity\core\ToolBox;

class upgrade_to_0_6_1 {
	
	private $logsObj;
	
	//=========================================================================
	public function __construct(Database $db) {
		$this->db = $db;
	}//end __construct()
	//=========================================================================
	
	
	
	//=========================================================================
	public function run_upgrade() {
		#$this->db->beginTrans(__METHOD__);
		ToolBox::debug_print(__METHOD__ .": running SQL file...");
		$this->db->run_sql_file(dirname(__FILE__) .'/schemaChangesFor_0.6.1.sql');
		#$this->db->commitTrans(__METHOD__);
		return(true);
	}//end run_upgrade()
	//=========================================================================
}

?>
