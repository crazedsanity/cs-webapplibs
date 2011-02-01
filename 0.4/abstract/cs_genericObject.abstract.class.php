<?php

/*
 * FILE INFORMATION:
 * 
 * $HeadURL$
 * $Id$
 * $LastChangedDate$
 * $LastChangedBy$
 * $LastChangedRevision$
 */

abstract class cs_genericObjectAbstract extends cs_genericUserGroupAbstract {
	
	/** Table name used to store object records. */
	protected $oTable = "cswal_object_table";
	
	/** Sequence for object table. */
	protected $oSeq = "cswal_object_table_object_id_seq";
	
	/** dbTableHandler{} object for simplifying SQL. */
	private $dbTableHandler;
	
	//============================================================================
	public function __construct(cs_phpDB $db) {
		parent::__construct($db);
		$cleanString = array(
			'object_name'	=> 'sql'
		);
		$this->dbTableHandler = new cs_dbTableHandler($this->db, $this->oTable, $this->oSeq, 'group_id', $cleanString);
	}//end __construct()
	//============================================================================
	
	
	
	//============================================================================
	public function create_object($objectName) {
		if(strlen($objectName)) {
			try {
				$newId = $this->dbTableHandler->create_record(array('object_name' => $objectName));
			}
			catch(Exception $e) {
				throw new exception(__METHOD__ .": failed to create object, DETAILS::: ". $e->getMessage());
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid object name (". $objectName .")");
		}
		return($newId);
	}//end create_object()
	//============================================================================
	
	
	
	//============================================================================
	public function get_object_by_name($objectName) {
		if(strlen($objectName)) {
			try {
				$retval = $this->dbTableHandler->get_single_record(array('object_name'=>$objectName));
			}
			catch(Exception $e) {
				throw new exception(__METHOD__ .":: failed to object from name=(". $objectName ."), DETAILS::: ". $e->getMessage());
			}
		}
		else {
			throw new exception(__METHOD__ .":: invalid object name (". $objectName .")");
		}
		return($retval);
	}//end get_object_by_name()
	//============================================================================
	
	
	
	//============================================================================
	public function get_object_by_id($objectId) {
		if(strlen($objectName)) {
			try {
				$retval = $this->dbTableHandler->get_record_by_id($objectId);
			}
			catch(Exception $e) {
				throw new exception(__METHOD__ .":: failed to object for ID=(". $objectId ."), DETAILS::: ". $e->getMessage());
			}
		}
		else {
			throw new exception(__METHOD__ .":: invalid object ID (". $objectId .")");
		}
		return($retval);
	}//end get_object_by_id()
	//============================================================================
	
	
	
	//============================================================================
	public function get_object_ids(array $objectNames, $createMissing=true) {
		$nvpArray = array();
		if(is_array($objectNames) && count($objectNames)) {
			$sql = "SELECT object_id, object_name FROM ". $this->oTable ." WHERE "
				. "object_name IN ";
			
			$myFilter = "";
			foreach($objectNames as $n) {
				$tString = "'". $this->clean_object_name($n) ."'";
				$myFilter = $this->gfObj->create_list($myFilter, $tString);
			}
			$sql .= '('. $myFilter .')';
			
			try {
				$nvpArray = $this->dbTableHandler->dbObj->run_query($sql, 'object_id', 'object_name');
			}
			catch(Exception $e) {
				throw new exception(__METHOD__ .": failed to retrieve object list, DETAILS::: ". $e->getMessage());
			}
			
			try {
				if($createMissing === true) {
					//clean object names...
					foreach($objectNames as $i=>$n) {
						$objectNames[$i] = $this->clean_object_name($n);
					}
					//pull the missing indexes out so they can be created...
					if(!is_array($nvpArray)) {
						$nvpArray = array();
					}
					$missingIndexes = array_diff($objectNames, $nvpArray);
					
					if(count($missingIndexes)) {
$this->gfObj->debug_print(__METHOD__ .": MISSING INDEXES::: ". $this->gfObj->debug_print($missingIndexes,0,1));
						foreach($missingIndexes as $newObjectName) {
							$newId = $this->create_object($newObjectName);
							$nvpArray[$newId] = $newObjectName;
						}
					}
$this->gfObj->debug_print(__METHOD__ .": createMissing=(". $createMissing ."), counts=(". count($objectNames) ."/". count($nvpArray)  ."/". count($missingIndexes)."), SQL::: ". $sql);
				}
				if(!is_array($nvpArray) || !count($nvpArray)) {
$this->gfObj->debug_print(__METHOD__ .": objectNames::: ". $this->gfObj->debug_print($objectNames,0,1));
$this->gfObj->debug_print(__METHOD__ .": nvpArray::: ". $this->gfObj->debug_print($nvpArray,0,1));
$this->gfObj->debug_print(__METHOD__ .": missingIndexes::: ". $this->gfObj->debug_print($missingIndexes,0,1));
cs_debug_backtrace(1);
					throw new exception(__METHOD__ .": no data returned");
				}
			}
			catch(Exception $e) {
				throw new exception(__METHOD__ .": error while creating missing objects, DETAILS::: ". $e->getMessage());
			}
		}
		return($nvpArray);
	}//end get_object_ids()
	//============================================================================
	
	
	
	//============================================================================
	public function create_id_path_part($id) {
		if(is_numeric($id)) {
			$retval = ':'. $id .':';
		}
		else {
			throw new exception(__METHOD__ .": invalid id (". $id .")");
		}
		return($retval);
	}//end create_id_path_part()
	//============================================================================
	
	
	
	//============================================================================
	public function create_id_path_from_objects(array $objects) {
		try {
			$myIds = $this->get_object_ids($objects,true);
			
			$idPath = "";
			if(is_array($myIds) && count($myIds)) {
				foreach($myIds as $id=>$name) {
					try {
						$idPath = $this->gfObj->create_list($idPath, $this->create_id_path_part($id), '');
					}
					catch(Exception $e) {
						throw new exception($e->getMessage());
					}
				}
			}
			else {
				throw new exception(__METHOD__ .": failed to create any IDs");
			}
		}
		catch(Exception $e) {
			throw new exception(__METHOD__ .": failed to create id path, DETAILS::: ". $e->getMessage());
		}
		return($idPath);
	}//end create_id_path_from_objects()
	//============================================================================
	
	
	
	//============================================================================
	protected function clean_object_name($n) {
		//pulled from cs-content, cs_globalFunctions::cleanString(), style="query"; modified to allow the brackets.
		$evilChars = array("\$", ":", "%", "~", "*",">", "<", "-", "[", "]", ")", "(", "&", "#", "?", ".", "\,","\/","\\","\"","\|","!","^","+","`","\n","\r");
		$n = preg_replace("/\|/","",$n);
		$n = preg_replace("/\'/", "", $n);
		$n = str_replace($evilChars,"", $n);
		$n = stripslashes(addslashes($n));
		
		return($n);
	}//end clean_object_name($n)
	//============================================================================
	
	
	
	//============================================================================
	public function is_id_path($path) {
		$isPath = false;
		if(is_string($path) && strlen($path)) {
			if(preg_match('/^(:-{0,1}[0-9]{1,}:){1,}$/', $path)) {
				$isPath = true;
			}
		}
		return($isPath);
	}//end is_id_path()
	//============================================================================
	
	
	
	//============================================================================
	public function explode_id_path($idPath) {
		//make the expected string into something that be broken into an array of numbers.
		$chunktify = preg_replace('/^:(.*):$/', '$1', $idPath);
		$chunktify = preg_replace('/:{2,}/', ':', $chunktify);
		$bits = explode(':', $chunktify);
		return($bits);
	}//end explode_id_path()
	//============================================================================
	
	
	
	//============================================================================
	public function translate_id_path($idPath) {
		if($this->is_id_path($idPath)) {
			$bits = $this->explode_id_path($idPath);
			$translatedPath = $this->get_object_names($this->explode_id_path($idPath));
		}
		else {
			throw new exception(__METHOD__ .": invalid path (". $idPath .")");
		}
		return($translatedPath);
	}//end translate_id_path()
	//============================================================================
	
	
	
	//============================================================================
	public function get_object_names(array $idList) {
		if(is_array($idList) && count($idList)) {
			$sql = "SELECT object_id, object_name FROM ". $this->oTable ." WHERE object_id IN ";
			
			$idListString = "";
			foreach($idList as $id) {
				$idListString = $this->gfObj->create_list($idListString, $id, ", ");
			}
			$sql .= "(". $idListString .")";
			
			//run it.
			try {
				$objectNames = $this->dbTableHandler->dbObj->run_query($sql, 'object_id', 'object_name');
			}
			catch(Exception $e) {
				throw new exception(__METHOD__ .": error while retrieving object names, DETAILS::: ". $e->getMessage());
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid data type (". gettype($idList) .") or empty array");
		}
		return($objectNames);
	}//end get_object_names()
	//============================================================================
	
}
?>
