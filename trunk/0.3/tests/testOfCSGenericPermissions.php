<?php
/*
 * Created on June 21, 2010
 * 
 * FILE INFORMATION:
 * 
 * $HeadURL$
 * $Id$
 * $LastChangedDate$
 * $LastChangedBy$
 * $LastChangedRevision$
 */

class testOfCSGenericPermissions extends UnitTestCase {
	
	//--------------------------------------------------------------------------
	function __construct() {
		$this->gfObj = new cs_globalFunctions;
		$this->gfObj->debugPrintOpt=1;
		if(!defined('CS_UNITTEST')) {
			throw new exception(__METHOD__ .": FATAL: constant 'CS_UNITTEST' not set, can't do testing safely");
		}
	}//end __construct()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	private function create_dbconn() {
		$dbParams = array(
			'host'		=> constant('cs_webapplibs-DB_CONNECT_HOST'),
			'dbname'	=> constant('cs_webapplibs-DB_CONNECT_DBNAME'),
			'user'		=> constant('cs_webapplibs-DB_CONNECT_USER'),
			'password'	=> constant('cs_webapplibs-DB_CONNECT_PASSWORD'),
			'port'		=> constant('cs_webapplibs-DB_CONNECT_PORT')
		);
		$db = new cs_phpDB(constant('DBTYPE'));
		$db->connect($dbParams);
		return($db);
	}//end create_dbconn()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	private function remove_tables() {
		$tableList = array(
			'cswal_gdl_object_table', 'cswal_gdl_attribute_table', 'cswal_gdl_path_table',
			'cswal_object_table', 'cswal_user_group_table', 'cswal_group_table'
		);
		
		$db = $this->create_dbconn();
		foreach($tableList as $name) {
			try {
				$db->run_update("DROP TABLE ". $name ." CASCADE", true);
			}
			catch(exception $e) {
				//force an error.
				//$this->assertTrue(false, "Error while dropping (". $name .")::: ". $e->getMessage());
			}
		}
	}//end remove_tables()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function test_userGroups() {
		$perm = new cs_genericPermission($this->create_dbconn());
	}//end test_userGroups
	//--------------------------------------------------------------------------
	
	
	
}
?>
