<?php
/*
 * Created on Aug 19, 2009
 *
 *  SVN INFORMATION:::
 * -------------------
 * Last Author::::::::: $Author$ 
 * Current Revision:::: $Revision$ 
 * Repository Location: $HeadURL$ 
 * Last Updated:::::::: $Date$
 */


require_once(dirname(__FILE__) .'/cs_webapplibs.abstract.class.php');

class cs_authToken extends cs_webapplibsAbstract {
	
	/** Database object. */
	private $db;
	
	//=========================================================================
	public function __construct(cs_phpDB $db) {
		
		if($db->is_connected()) {
			$this->db = $db;
		}
		else {
			throw new exception(__METHOD__ .": database object not connected");
		}
		
	}//end __construct()
	//=========================================================================
	
	
	
	//=========================================================================
	public function load_table() {
		$file = dirname(__FILE__) .'/setup/authtoken_schema.'. $this->db->get_dbtype() .'.sql';
		
		if(file_exists($file)) {
			try {
				$this->db->run_update(file_get_contents($file), true);
			}
			catch(exception $e) {
				throw new exception(__METHOD__ .": error while trying to load table::: ". $e->getMessage());
			}
		}
		else {
			throw new exception(__METHOD__ .": unsupported database type (". $this->db->get_dbtype() .")");
		}
	}//end load_table()
	//=========================================================================
	
	
	
	//=========================================================================
	//=========================================================================
	
}
?>
