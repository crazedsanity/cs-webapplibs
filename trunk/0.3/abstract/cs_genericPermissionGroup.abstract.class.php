<?php

/*
 * Created on June 14, 2010
 * 
 * FILE INFORMATION:
 * 
 * $HeadURL$
 * $Id$
 * $LastChangedDate$
 * $LastChangedBy$
 * $LastChangedRevision$
 */

abstract class cs_genericPermissionGroupAbstract extends cs_genericGroupAbstract {
	
	/** Table name used to store permission groups. */
	const permGroupTable = "cswal_permission_group_table";
	
	/** Sequence for permission_group table. */
	const groupSeq = "cswal_permission_group_table_permission_group_id_seq";
	
	//============================================================================
	public abstract function __construct(cs_phpDB $db) {
		parent::__construct($db);
	}//end __construct()
	//============================================================================
	
	
	
	//============================================================================
	protected function clean_perm_group_name($permGroupName) {
		try {
			$retval = $this->clean_group_name($permGroupName);
		}
		catch(Exception $e) {
			throw new exception(__METHOD__ .":: failed to clean group name (". $groupName .")");
		}
		return($retval);
	}//end clean_perm_group_name()
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
