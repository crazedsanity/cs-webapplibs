<?php
/*
 * Created on Aug 19, 2009
 */

abstract class cs_newWebapplibsAbstract {
	
	static public $version;
	protected $gfObj;
	
	//-------------------------------------------------------------------------
    public function __construct($makeGfObj=true) {
		self::$version = new cs_version(dirname(__FILE__) .'/../VERSION');
		
		if($makeGfObj === true) {
			$this->gfObj = new cs_globalFunctions();
		}
    }//end __construct()
	//-------------------------------------------------------------------------
	public static function GetVersion() {
		return(self::$version);
	}
	
	
	
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
