<?php
/*
 * Created on Aug 19, 2009
 */

abstract class cs_webapplibsAbstract extends cs_version {
	
	protected $gfObj;
	static public $version;
	
	//-------------------------------------------------------------------------
    public function __construct($makeGfObj=true) {
		
		if($makeGfObj === true) {
			$this->gfObj = new cs_globalFunctions();
		}
		$this->set_version_file_location(dirname(__FILE__) .'/../VERSION');
    }//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public static function GetVersionObject() {
		if(!is_object(self::$version)) {
			self::$version = new cs_version(dirname(__FILE__) .'/../VERSION');
		}
		return(self::$version);
	}//end GetVersionObject()
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
