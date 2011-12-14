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
	protected $permTable = "cswal_permission_table";
	
	/** Sequence for permissions table. */
	protected $permSeq = "cswal_permission_table_permission_id_seq";
	
	/** List of valid keys... */
	protected $keys = array();
	
	/** How to clean the path (if at all); boolean true = use cs_globalFunctions::clean_url(); boolean false will 
		cause it to not be cleaned at all; a string will use cs_globalFunctions::cleanString({string})*/
	protected $pathCleaner=true;
	
	/** dbTableHandler{} object for easier SQL. */
	protected $dbTableHandler;
	
	//============================================================================
	/**
	 * Generic permission system based on *nix filesystem permissions.
	 */
	public function __construct(cs_phpDB $db, $useUrlCleaner=true) {
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
		if(is_bool($useUrlCleaner) || (is_string($useUrlCleaner) && strlen($useUrlCleaner))) {
			$this->pathCleaner = $useUrlCleaner;
		}
		$cleanString = array(
			'system_name'		=> 'integer',
			'path'				=> 'email_plus',
			'user_id'			=> 'integer',
			'group_id'			=> 'integer',
			'inherit'			=> 'bool',
			'u_r'				=> 'bool',
			'u_w'				=> 'bool',
			'u_x'				=> 'bool',
			'g_r'				=> 'bool',
			'g_w'				=> 'bool',
			'g_x'				=> 'bool',
			'o_r'				=> 'bool',
			'o_w'				=> 'bool',
			'o_x'				=> 'bool',
		);
		$this->dbTableHandler = new cs_dbTableHandler($this->db, $this->permTable, $this->permSeq, 'permission_id', $cleanString);
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
	/**
	 * Parses a string like 'rwxr-xr--' into keys for the database.
	 */
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
	/** 
	 * Create permission string based on an array (the opposite of parse_permission_string())
	 */
	protected function build_permission_string(array $perms) {
cs_debug_backtrace(1);
		$this->_sanityCheck();
		
		//NOTE:: the incoming $perms must have more (or equal) items vs. $this->keys so that it can accept arrays with extra
		//	items, but can disregard those that obviously do not have enough.
		if(is_array($perms) && count($perms) >= count($this->keys)) {
			$retval = "";
			foreach($this->keys as $dbColName) {
				if(isset($perms[$dbColName])) {
					//get the last character of the column name.
					$thisPermChar = substr($dbColName, -1);
					if($this->evaluate_perm_value($perms[$dbColName], $thisPermChar)) {
						$permChar = substr($dbColName, -1);
					}
					else {
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
			$extraInfo="";
			if(!is_array($perms)) {
				$extraInfo = " (expected array, received ". gettype($perms) ." '". $perms ."')";
			}
			throw new exception(__METHOD__ .":: invalid permission set". $extraInfo);
		}
		return($retval);
	}//end build_permission_string();
	//============================================================================
	
	
	
	//============================================================================
	/** 
	 * Creates a permission object record.
	 */
	public function create_permission($name, $userId, $groupId, $permString) {
		if(is_string($name) && strlen($name) && is_numeric($userId) && $userId >= 0 && is_numeric($groupId) && $groupId >= 0) {
			try{
				$insertArr = $this->parse_permission_string($permString);
				$insertArr['path'] = $name;
				$insertArr['user_id'] = $userId;
				$insertArr['group_id'] = $groupId;
				
				$newId = $this->dbTableHandler->create_record($insertArr,false);
			}
			catch(Exception $e) {
				throw new exception(__METHOD__ .":: failed to create new record, name=(". $name ."), permString=(". $permString .") DETAILS::: ". $e->getMessage());
			}
		}
		else {
			throw new exception(__METHOD__ .":: invalid argument(s)");
		}
		
		return($newId);
	}//end create_permission()
	//============================================================================
	
	
	
	//============================================================================
	/**
	 * Retrieves a permission object by name from the database, exception on failure.
	 */
	public function get_permission($name) {
		try {
			$retval = $this->dbTableHandler->get_single_record(array('path'=>$name));
			
$this->gfObj->debug_var_dump($retval,1);
			//now translate the object_path...
			$retval['perm_string'] = $this->build_permission_string($retval);
		}
		catch(Exception $e) {
			throw new exception(__METHOD__ .":: error while locating permission '". $name ."', DETAILS::: ". $e->getMessage());
		}
		
		return($retval);
	}//end get_permission()
	//============================================================================
	
	
	
	//============================================================================
	/**
	 * Retrieves a permission object from the database based on an ID.
	 */
	public function get_permission_by_id($permId) {
		try {
			if(!is_null($permId) && is_numeric($permId)) {
				$retval = $this->dbTableHandler->get_record_by_id($permId);
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
	
	
	
	//============================================================================
	/**
	 * Check available permissions...
	 */
	public function check_permission($path, $userId) {
		$availablePerms = array(
			'r'	=> false,
			'w'	=> false,
			'x'	=> false
		);
			
		try {
			//get the object.
			$permission = $this->get_permission($path);
			
			//now figure out what permissions they have.
			if($permission['user_id'] == $userId) {
				//it is the owner, determine based on the permissions with the 'u_' prefix.
				$availablePerms = $this->get_permission_list($permission, 'u');
			}
			elseif($this->is_group_member($userId, $permission['group_id'])) {
				//group member, use the 'g_' permissions.
				$availablePerms = $this->get_permission_list($permission, 'g');
			}
			else {
				//not owner OR group member, use the 'o_' permissions
				$availablePerms = $this->get_permission_list($permission, 'o');
			}
		}
		catch(Exception $e) {
			//consider logging this... maybe based on some internal variable.
		}
		
		return($availablePerms);
	}//end check_permission()
	//============================================================================
	
	
	
	//============================================================================
	/** 
	 * Creates an array of permission bits based on user/group/other from the given 
	 * permission data (i.e. the return from get_permission()).
	 */
	protected function get_permission_list(array $permData, $type) {
		$retval = array();
		if(in_array($type, array('u', 'g', 'o'))) {
			foreach($this->keys as $myKey) {
				if(preg_match('/'. $type .'_[rwx]$/',$myKey)) {
					//chop the last character off (i.e. 'r' from 'u_r')
					$myPermChar = substr($myKey, -1);
					$retval[$myPermChar] = $this->evaluate_perm_value($permData[$myKey], $type);
				}
			}
		}
		else {
			throw new exception(__METHOD__ .":: invalid type (". $type ."), must be u/g/o");
		}
		
		return($retval);
	}//end get_permission_list()
	//============================================================================
	
	
	
	//============================================================================
	/**
	 * Evaluate the value in a permission bit as true (allowed) or false (disallowed).
	 */
	protected function evaluate_perm_value($val=null) {
		
		if($val === '-' || $val === false || $val === 'f' || $val === 0 || $val === '0' || !strlen($val)) {
			$retval = false;
		}
		else {
			$retval = true;
		}
		return($retval);
	}//end evaluate_perm_value
	//============================================================================
	
	
	
	//============================================================================
	/**
	 * Determines if a permission record exists (based on name).
	 */
	public function permission_exists($permName) {
		try {
			$info = $this->get_permission($permName);
			$retval = false;
			if(is_array($info)) {
				$retval = true;
			}
		}
		catch(Exception $e) {
			$retval = false;
		}
		
		return($retval);
	}//end permission_exists()
	//============================================================================
	
	
	
	//============================================================================
	/**
	 * If user has read permission for the given named permission/object.
	 */
	public function has_read_permission($userId, $permName) {
		$myPerms = $this->check_permission($permName, $userId);
		$retval = false;
		if($myPerms['r'] === true) {
			$retval = true;
		}
		return($retval);
	}//end has_read_permission()
	//============================================================================
	
	
	
	//============================================================================
	/**
	 * If user has write permission for the given named permission/object.
	 */
	public function has_write_permission($userId, $permName) {
		$myPerms = $this->check_permission($permName, $userId);
		$retval = false;
		if($myPerms['w'] === true) {
			$retval = true;
		}
		return($retval);
	}//end has_write_permission()
	//============================================================================
	
	
	
	//============================================================================
	/**
	 * If user has execute permission for the given named permission/object.
	 */
	public function has_execute_permission($userId, $permName) {
		$myPerms = $this->check_permission($permName, $userId);
		$retval = false;
		if($myPerms['x'] === true) {
			$retval = true;
		}
		return($retval);
	}//end has_execute_permission()
	//============================================================================
	
	
	
	//============================================================================
	public function update_permission() {
	}//end update_permission()
	//============================================================================
}
?>
