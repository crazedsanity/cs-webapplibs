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

class cs_genericPermission extends cs_genericUserGroupAbstract {
	
	/** Database object. */
	public $db;
	
	/** cs_globalFunctions object, for cleaning strings & such. */
	public $gfObj;
	
	/** Table name used to store permissions. */
	const objTable = "cswal_object_table";
	
	/** Sequence for permissions table. */
	const objSeq = "cswal_object_table_object_id_seq";
	
	/** List of valid keys... */
	protected $keys = array();
	
	//============================================================================
	public function __construct(cs_phpDB $db) {
		$this->db = $db;
		parent::__construct($db);
		$this->gfObj = new cs_globalFunctions;
		$this->keys = array(
			0	=> 'u_r',
			1	=> 'u_w',
			2	=> 'u_x',
			3	=> 'g_r',
			4	=> 'g_w',
			5	=> 'g_x',
			6	=> 'o_r',
			7	=> 'o_w',
			8	=> 'o_x'
		);
	}//end __construct()
	//============================================================================
	
	
	
	//============================================================================
	/**
	 * Checks internals to make sure all is okay; throws an exception on fail.
	 */
	private function _sanityCheck() {
		if(!is_array($this->keys) || count($this->keys) != 9) {
			throw new exception(__METHOD__ .":: internal error, no keys");
		}
	}//end _sanityCheck()
	//============================================================================
	
	
	
	//============================================================================
	protected function parse_permission_string($string) {
		$this->_sanityCheck();
		if(is_string($string) && strlen($string) == 9) {
			$retval = array();
			//handle it like an array.
			for($x=0;$x<strlen($string);$x++) {
				$myVal = false;
				if($string[$x] !== '-') { 
					$myVal = true;
				}
				$key = $this->keys[$x];
				$retval[$key] = $myVal;
			}
		}
		else {
			throw new exception(__METHOD__ .":: invalid permission string (". $string ."), non-string or not 9 characters long (example: 'rwxrw-rw-')");
		}
		return($retval);
	}//end parse_permission_string()
	//============================================================================
	
	
	
	//============================================================================
	protected function build_permission_string(array $perms) {
		$this->_sanityCheck();
		if(is_array($perms) && count($perms) >= count($this->keys)) {
			$retval = "";
			foreach($this->keys as $dbColName) {
				if(isset($perms[$dbColName])) {
					//get the last character of the column name.
					$permChar = substr($dbColName, -1);
					if($perms[$dbColName] === false || !strlen($perms[$dbColName]) || $perms[$dbColName] === '-' || $perms[$dbColName] === 'f') {

						$permChar = '-';
					}
					$retval .= $permChar;
				}
				else {
					throw new exception(__METHOD__ .":: missing permission index (". $dbColName .")");
				}
			}
		}
		else {
			throw new exception(__METHOD__ .":: invalid permission set.");
		}
		return($retval);
	}//end build_permission_string();
	//============================================================================
	
	
	
	//============================================================================
	public function create_object($name, $userId, $groupId, $permString) {
		return($this->create_permission($name, $userId, $groupId, $permString));
	}//end create_object()
	//============================================================================
	
	
	
	//============================================================================
	public function create_permission($name, $userId, $groupId, $permString) {
		if(is_string($name) && strlen($name) && is_numeric($userId) && $userId >= 0 && is_numeric($groupId) && $groupId >= 0) {
			$cleanStringArr = array(
				'object_name'	=> 'sql',
				'user_id'		=> 'numeric',
				'group_id'		=> 'numeric',
				'u_r'			=> 'bool',
				'u_w'			=> 'bool',
				'u_x'			=> 'bool',
				'g_r'			=> 'bool',
				'g_w'			=> 'bool',
				'g_x'			=> 'bool',
				'o_r'			=> 'bool',
				'o_w'			=> 'bool',
				'o_x'			=> 'bool'
			);
			try{
				$insertArr = $this->parse_permission_string($permString);
				$insertArr['object_name'] = $this->gfObj->cleanString($name, 'sql', 0);
				$insertArr['user_id'] = $userId;
				$insertArr['group_id'] = $groupId;
				
				$insertSql = $this->gfObj->string_from_array($insertArr, 'insert', null, $cleanStringArr);
				$sql = "INSERT INTO ". self::objTable ." ". $insertSql;
				$newId = $this->db->run_insert($sql, self::objSeq);
			}
			catch(Exception $e) {
				throw new exception(__METHOD__ .":: failed to create new record, DETAILS::: ". $e->getMessage());
			}
		}
		else {
			throw new exception(__METHOD__ .":: invalid argument(s)");
		}
		
		return($newId);
	}//end create_permission()
	//============================================================================
	
	
	
	//============================================================================
	public function get_object($name) {
		return($this->get_permission($name));
	}//end get_object()
	//============================================================================
	
	
	
	//============================================================================
	public function get_permission($name) {
		try {
			$name = $this->gfObj->cleanString($name, 'sql', 0);
			$sql = "SELECT * FROM ". self::objTable ." WHERE object_name='". $name ."'";
			$retval = $this->db->run_query($sql);
		}
		catch(Exception $e) {
			throw new exception(__METHOD__ .":: error while locating permission '". $name ."', DETAILS::: ". $e->getMessage());
		}
		
		return($retval);
	}//end get_permission()
	//============================================================================
	
	
	
	//============================================================================
	public function get_object_by_id($objectId) {
		return($this->get_permission_by_id($objectId));
	}//end get_object_by_id()
	//============================================================================
	
	
	
	//============================================================================
	public function get_permission_by_id($permId) {
		try {
			if(!is_null($permId) && is_numeric($permId)) {
				$sql = "SELECT * FROM ". self::objTable ." WHERE object_id='". $permId ."'";
				$retval = $this->db->run_query($sql);
			}
			else {
				throw new exception(__METHOD__ .":: invalid permission ID (". $permId .")");
			}
		}
		catch(Exception $e) {
			throw new exception(__METHOD__ .":: error while locating permission '". $permId ."', DETAILS::: ". $e->getMessage());
		}
		
		return($retval);
	}//end get_permission_by_id()
	//============================================================================
}
?>
