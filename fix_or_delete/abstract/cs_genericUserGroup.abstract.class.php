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

abstract class cs_genericUserGroupAbstract extends cs_genericGroupAbstract {
	
	/** Table name used to store user_group records. */
	protected $ugTable = "cswal_user_group_table";
	
	/** Sequence for user_group table. */
	protected $ugSeq = "cswal_user_group_table_user_group_id_seq";
	
	/** dbTableHandler{} object for simplifying SQL. */
	private $dbTableHandler;
	
	//============================================================================
	public function __construct(cs_phpDB $db) {
		parent::__construct($db);
		$cleanString = array(
			'user_id'	=> 'integer',
			'group_id'	=> 'integer'
		);
		$this->dbTableHandler = new cs_dbTableHandler($this->db, $this->ugTable, $this->ugSeq, 'user_group_id', $cleanString);
	}//end __construct()
	//============================================================================
	
	
	
	//============================================================================
	public function create_user_group($userId, $groupId) {
		if(is_numeric($userId) && is_numeric($groupId) && $userId >= 0 && $groupId >= 0) {
			try {
				$newId = $this->dbTableHandler->create_record(array('user_id'=>$userId,'group_id'=>$groupId));
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
				$retval = $this->dbTableHandler->get_records(array('user_id'=>$userId));
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
		if(is_array($groupList)) {
			$keys = array_keys($groupList);
			if($groupList[$keys[0]]['group_id'] == $groupId) {
				$retval = true;
			}
		}
		return($retval);
	}//end is_group_member()
	//============================================================================
	
}
?>
