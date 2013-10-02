<?php
/*
 * Created on Aug 19, 2009
 */

abstract class cs_webapplibsAbstract extends cs_version implements cs_versionInterface {
	
	protected $gfObj;
	static public $version;
	
	//-------------------------------------------------------------------------
    public function __construct($makeGfObj=true) {
		
		if($makeGfObj === true) {
			$this->gfObj = new cs_globalFunctions();
		}
    }//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public static function GetVersionObject() {
		if(!is_object(self::$version)) {
			self::$version = new cs_version();
		}
		return(self::$version);
	}//end GetVersionObject()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function load_schema($dbType, cs_phpDb $db) {
		if(is_object($db)) {
			$file = dirname(__FILE__) .'/../setup/schema.'. $dbType .'.sql';
			try {
				$result = $db->run_sql_file($file);
cs_global::debug_print(__METHOD__ .": result of loading schema: (". $result .")");
cs_debug_backtrace(1);
			}
			catch(Exception $e) {
				throw new exception(__METHOD__ .": failed to load schema file (". $file ."), DETAILS::: ". $e->getMessage());
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid database object");
		}
		return($result);
	}//end load_schema()
	//-------------------------------------------------------------------------
}

?>
