<?php
/*
 * Created on Oct 27, 2009
 * 
 * THE IDEA:::
 * 		1.) Unix/Linux-like paths lead to an attribute.
 * 		2.) Multiple paths can lead to the same attribute.
 * 		3.) An attribute can be linked to its original path.
 * 		4.) Each "directory" in a path is an object with an ID.
 * 		5.) Paths themselves are only stored on attributes: intermediate paths may be valid if all objects
 * 				for that path are also valid (i.e. if "/one/two/three" is valid, so is "/two/one/three" and "/three/two/one").
 * 		6.) Database...
 * 			a.) finding an attribute referencing a single object should be straightforward and fast.
 * 			b.) objects are unique to avoid excess duplicate pathways
 * 			c.) using id paths with each number wrapped in colons is simple (i.e. ":3342:", ":3342::3::3:"
 * 
 * The idea here is to have a class that generically links data together (in a 
 * database). It is not meant to be a super clean or speedy system, instead meant 
 * as a way of describing relationships between various pieces of data.
 * 
 * Once a path is created (list object_id's, separated by '::'), it should always have an attribute.
 * 
 * 
 * 1::2::3					-> select * from <bla> WHERE path = '2' OR path like '2::%' OR path like '%::2'
 *   -OR-
 * :1::2::3:				-> select * from <bla> WHERE path like '%:2:%'
 * 
 * If an attribute is created with a small path (like "/test") and the id is 1, the attribute shows ":1:"
 * 		--> if the id is 7720218, then the attribute shows ":7720218:"
 * 
 *
 *  SVN INFORMATION:::
 * -------------------
 * Last Author::::::::: $Author$ 
 * Current Revision:::: $Revision$ 
 * Repository Location: $HeadURL$ 
 * Last Updated:::::::: $Date$
 */



class cs_genericDataLinker extends cs_gdlAttribAbstract {
	
	const attrTable='cswal_gdl_attribute_table';
	const attrTableSeq='cswal_gdl_attribute_table_attribute_id_seq';
	
	protected $validTypes = array('text', 'int', 'dec', 'bool');
	protected $gfObj;
	protected $basePath=null;
	
	public $db;
	
	//-------------------------------------------------------------------------
	public function __construct(cs_phpDB $db) {
		
		parent::__construct($db);
		if(!$db->is_connected()) {
			throw new exception(__METHOD__ .": database not connected");
		}
		$this->db = $db;
		$this->gfObj = new cs_globalFunctions;
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function create_path_objects($path) {
		$newPath = $this->clean_path($path);
		$newPath = preg_replace('/^\//', '', $newPath);
		
		//break it into bits.
		$bits = explode('/', $newPath);
		
		$myList = $this->build_object_id_list($bits);
		if(count($myList) !== count($bits)) {
			$createThese = array();
			foreach($bits as $name) {
				if(!isset($myList[$name])) {
					$createThese[] = $name;
				}
			}
			$this->create_objects_enmasse($createThese);
			$myList = $this->build_object_id_list($bits);
		}
		
		$retval = array();
		foreach($bits as $name) {
			@$retval[$name] = $myList[$name];
		}
		
		if(is_null($retval) || !is_array($retval) || !count($retval)) {
			throw new exception(__METHOD__ .": failed to build path objects... ". $retval);
		}
		
		return($retval);
	}//end create_path_objects()
	//-------------------------------------------------------------------------
}

?>
