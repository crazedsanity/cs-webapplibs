<?php
/*
 * Created on Jan 25, 2009
 * 
 * FILE INFORMATION:
 * 
 * $HeadURL$
 * $Id$
 * $LastChangedDate$
 * $LastChangedBy$
 * $LastChangedRevision$
 */

require_once(dirname(__FILE__) .'/../cs_authToken.class.php');

class testOfCSAuthToken extends UnitTestCase {
	
	//--------------------------------------------------------------------------
	function __construct() {
		$this->gfObj = new cs_globalFunctions;
		$this->gfObj->debugPrintOpt=1;
	}//end __construct()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	function setUp() {
		$tok = new cs_authToken($this->create_db());
		try {
			$tok->load_table();
		}
		catch(exception $e) {
		}
	}//end setUp()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	function tearDown() {
		$db = $this->create_db();
		try {
			$db->run_update('DROP TABLE cswal_auth_token_table', true);
		}
		catch(exception $e) {
		}
	}//end
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	private function create_db() {
		$dbParams = array(
			'host'		=> constant('DB_PG_HOST'),
			'dbname'	=> constant('DB_PG_DBNAME'),
			'user'		=> constant('DB_PG_DBUSER'),
			'password'	=> constant('DB_PG_DBPASS'),
			'port'		=> constant('DB_PG_PORT')
		);
		$db = new cs_phpDB(constant('DBTYPE'));
		$db->connect($dbParams);
		return($db);
	}//end create_db()
	//--------------------------------------------------------------------------
	
	
	//--------------------------------------------------------------------------
	function test_basics() {
		$db = $this->create_db();
		$tok = new cs_authToken($db);
		
		
	}//end test_basics()
	//--------------------------------------------------------------------------
}


?>
