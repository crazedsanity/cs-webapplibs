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
			'object_name'	=> 'text'
		);
		$this->dbTableHandler = new cs_dbTableHandler($this->db, $this->oTable, $this->oSeq, 'group_id', $cleanString);
	}//end __construct()
	//============================================================================
	
	
	
	//============================================================================
	public function create_object($objectName) {
		if(strlen($objectName)) {
			$newId = $this->dbTableHandler->create_record(array('object_name', $objectName));
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
	//============================================================================
	
}
?>
