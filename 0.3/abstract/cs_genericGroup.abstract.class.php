<?php

/*
 * Created on June 03, 2010
 * 
 * FILE INFORMATION:
 * 
 * $HeadURL$
 * $Id$
 * $LastChangedDate$
 * $LastChangedBy$
 * $LastChangedRevision$
 */

abstract class cs_genericGroupAbstract extends cs_genericPermissionAbstract {
	
	/** Table name used to store groups. */
	const groupTable = "cswal_group_table";
	
	/** Sequence for groups table. */
	const groupSeq = "cswal_group_table_group_id_seq";
	
	//============================================================================
	public abstract function __construct(cs_phpDB $db) {
		parent::__construct($db);
	}//end __construct()
	//============================================================================
	
	
	
	//============================================================================
	protected function clean_group_name($groupName) {
		try {
			$retval = $this->clean_permission_name($groupName);
		}
		catch(Exception $e) {
			throw new exception(__METHOD__ .":: failed to clean group name (". $groupName .")");
		}
		return($retval);
	}//end clean_group_name()
	//============================================================================
	
	
	
	//============================================================================
	public function create_group($groupName) {
		try {
			$groupName = $this->clean_group_name($groupName);
			$sql = "INSERT INTO ". self::groupTable ." (group_name) VALUES ('". $groupName ."')";
			$newId = $this->db->run_insert($sql, self::groupSeq);
		}
		catch(Exception $e) {
			throw new exception(__METHOD__ .":: failed to create group (". $groupName ."), DETAILS::: ". $e->getMessage());
		}
		return($newId);
	}//end create_group()
	//============================================================================
	
	
	
	//============================================================================
	public function get_group($groupName) {
		try {
			$groupName = $this->clean_group_name($groupName);
			$sql = "SELECT * FROM ". self::groupTable ." WHERE group_name='". $groupName ."'";
			$retval = $this->db->run_query($sql);
		}
		catch(Exception $e) {
			throw new exception(__METHOD__ .":: failed to locate group (". $groupName ."), DETAILS::: ". $e->getMessage());
		}
		return($retval);
	}//end get_group()
	//============================================================================
	
	
	
	//============================================================================
	public function get_group_by_id($groupId) {
		try {
			if(!is_null($groupId) && is_numeric($groupId)) {
				$sql = "SELECT * FROM ". self::groupTable ." WHERE group_id='". $groupId ."'";
				$retval = $this->db->run_query($sql);
			}
			else {
				throw new exception(__METHOD__ .":: invalid group ID (". $groupId .")");
			}
		}
		catch(Exception $e) {
			throw new exception(__METHOD__ .":: failed to locate group ID (". $groupId ."), DETAILS::: ". $e->getMessage());
		}
		return($retval);
	}//end get_group_by_id()
	//============================================================================
	
}
?>
