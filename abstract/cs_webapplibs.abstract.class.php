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

abstract class cs_webapplibsAbstract extends cs_versionAbstract {
	
	protected $gfObj;
	
	//-------------------------------------------------------------------------
    public function __construct($makeGfObj=true) {
		$this->set_version_file_location(dirname(__FILE__) . '/../VERSION');
		
		if($makeGfObj === true) {
			//make a cs_globalFunctions{} object.
			//TODO::: find a way to avoid h
			$this->gfObj = new cs_globalFunctions();
		}
    }//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function load_schema($dbType, cs_phpDb $db) {
		$file = dirname(__FILE__) .'/../setup/schema.'. $dbType .'.sql';
		try {
			$result = $db->run_sql_file($file);
		}
		catch(Exception $e) {
			throw new exception(__METHOD__ .": failed to load schema file (". $file ."), DETAILS::: ". $e->getMessage());
		}
		return($result);
	}//end load_schema()
	//-------------------------------------------------------------------------
}

?>
