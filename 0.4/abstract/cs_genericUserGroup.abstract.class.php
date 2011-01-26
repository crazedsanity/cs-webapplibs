<?php

/*
 * Created on June 18, 2010
 * 
 * FILE INFORMATION:
 * 
 * $HeadURL$
 * $Id$
 * $LastChangedDate$
 * $LastChangedBy$
 * $LastChangedRevision$
 */

abstract class cs_genericUserGroupAbstract extends cs_genericGroupAbstract {
	
	/** Table name used to store user_group records. */
	const ugTable = "cswal_user_group_table";
	
	/** Sequence for user_group table. */
	const ugSeq = "cswal_user_group_table_user_group_id_seq";
	
	//============================================================================
	public function __construct(cs_phpDB $db) {
		parent::__construct($db);
	}//end __construct()
	//============================================================================
	
	
	
	//============================================================================
	public function create_user_group($userId, $groupId) {
		if(is_numeric($userId) && is_numeric($groupId) && $userId >= 0 && $groupId >= 0) {
			try {
				$sql = "INSERT INTO ". self::ugTable ." (user_id, group_id) VALUES (". $userId .", ". $groupId .")";
				$newId = $this->db->run_insert($sql, self::ugSeq);
			}
			catch(Exception $e) {
				throw new exception(__METHOD__ .":: failed to create user group, DETAILS::: ". $e->getMessage());
			}
		}
		else {
			throw new exception(__METHOD__ .":: invalid or non-numeric user_id (". $userId .") or group_id (". $groupId .")");
		}
		return($newId);
	}//end create_group()
	//============================================================================
	
	
	
	//============================================================================
	public function get_user_groups($userId) {
		if(is_numeric($userId) && $userId >= 0) {
			try {
				$sql = "SELECT ug.*, g.group_name, g.group_admin FROM ". self::ugTable ." AS ug INNER "
					."JOIN ". parent::groupTable ." as g USING (group_id) WHERE user_id=". $userId;
				$retval = $this->db->run_query($sql, 'group_id');
			}
			catch(Exception $e) {
				throw new exception(__METHOD__ .":: failed to locate groups for user_id=(". $userId ."), DETAILS::: ". $e->getMessage());
			}
		}
		else {
			throw new exception(__METHOD__ .":: invalid or non-numeric user_id (". $userId .")");
		}
		return($retval);
	}//end get_user_groups()
	//============================================================================
	
	
	
	//============================================================================
	public function is_group_member($userId, $groupId) {
		$groupList = $this->get_user_groups($userId);
		$retval = false;
		if(isset($groupList[$groupId])) {
			$retval = true;
		}
		return($retval);
	}//end is_group_member()
	//============================================================================
	
}
?>
