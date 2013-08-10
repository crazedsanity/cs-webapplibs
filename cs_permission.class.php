<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

define('CS_CREATE', 1);
define('CS_READ', 2);
define('CS_UPDATE', 4);
define('CS_DELETE', 8);




class cs_permission {
	
	const T_PERM = 'cswal_permission_table';
	const S_PERM = 'cswal_permission_table_permission_id_seq';
	const K_PERM = 'permission_id';
	
	const T_GROUP = 'cswal_group_table';
	const S_GROUP = 'cswal_group_table_group_id_seq';
	const K_GROUP = 'group_id';
	
	const T_GPERM = 'cswal_group_permission_table';
	const S_GPERM = 'cswal_group_permission_table_group_permission_id_seq';
	const K_GPERM = 'group_permission_id';
	
	const T_UG = 'cswal_user_group_table';
	const S_UG = 'cswal_user_group_table_user_group_id_seq';
	const K_UG = 'user_group_id';
	
	const T_UP = 'cswal_user_permission_table';
	const S_UP = 'cswal_user_permission_table_user_permission_id_seq';
	const K_UP = 'user_permission_id';
	
	public $gf = null;
	public $db = null;
	
	
	
	//--------------------------------------------------------------------------
	public function __construct(cs_phpdb $db) {
		$this->gf = new cs_globalFunctions();
		$this->db = $db;
	}//end __construct()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	/**
	 * The easiest way to set $defaultPerms (in a readable way) is like:  
	 *	$defaultPerms = CS_CREATE | CS_READ | CS_UPDATE 
	 */
	public function create_permission($location, $defaultPerms) {
		$tLocation = $this->clean_location($location);
		
		$tPerms = $defaultPerms;
		if(!is_numeric($defaultPerms)) {
			$tPerms = $this->get_perms_from_string($defaultPerms);
		}
		
		$sql = "INSERT INTO ". self::T_PERM .' (location, default_permissions) '.
				'VALUES (:loc, :perm)';
		
		try {
			$params = array(
				'loc'	=> $tLocation,
				'perm'	=> $tPerms
			);
			$newId = $this->db->run_insert($sql, $params, self::S_PERM);
		}
		catch(Exception $ex) {
			throw new exception(__METHOD__ .": failed to create permission, DETAILS::: ". $ex->getMessage());
		}
		
		return ($newId);
	}//end create_permission()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function get_permission($path) {
	}//end get_permission()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function clean_location($url) {
		$bits = preg_split('/\@/', $url);
		
		if(count($bits) == 2) {
			$retval = preg_replace('~/+~', '/', strtolower($bits[0])) .'@'. $bits[1];
		}
		else {
			$retval = preg_replace('~/+~', '/', strtolower($url));
		}
		
		return($retval);
	}//end clean_location()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function get_perms_from_string($string) {
		$tString = preg_replace('/[^c|r|u|d]/', '', strtolower($string));
		$retval = 0;
		
		$bits = preg_split('//', $tString, -1, PREG_SPLIT_NO_EMPTY);
		
		if(in_array('c', $bits)) {
			$retval |= CS_CREATE;
		}
		if(in_array('r', $bits)) {
			$retval |= CS_READ;
		}
		if(in_array('u', $bits)) {
			$retval |= CS_UPDATE;
		}
		if(in_array('d', $bits)) {
			$retval |= CS_DELETE;
		}
		
		return($retval);
	}//end get_perms_from_string()
	//--------------------------------------------------------------------------
	

	
	//--------------------------------------------------------------------------
	public function create_group($name, $description) {
		$params = array(
			'name'	=> $name,
			'desc'	=> $description
		);
		
		$sql = 'INSERT INTO '. self::T_GROUP .' (group_name, group_description)'
				.' VALUES (:name, :desc)';
		
		try {
			$groupId = $this->db->run_insert($sql, $params, self::S_GROUP);
		}
		catch(Exception $ex) {
			throw new exception(__METHOD__ .": unable to create group, DETAILS::: ". $ex->getMessage());
		}
		
		return($groupId);
	}//end create_group()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function create_group_permission($groupId, $permissionId, $perms) {
		$permInt = $perms;
		if(!is_numeric($perms)) {
			$permInt = $this->get_perms_from_string($perms);
		}
		$params = array(
			'gid'	=> $groupId,
			'pid'	=> $permissionId,
			'perm'	=> $permInt
		);
		
		$sql = 'INSERT INTO '. self::T_GPERM .' (group_id, permission_id, permissions)'
				.' VALUES (:gid, :pid, :perm)';
		
		try {
			$newId = $this->db->run_insert($sql, $params, self::S_GPERM);
		}
		catch(Exception $ex) {
			throw new exception(__METHOD__ .": failed to create group permission, DETAILS::: ". $ex->getMessage());
		}
		
		return($newId);
	}//end create_group_permission()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function create_user_group($userId, $groupId) {
		$params = array(
			'uid'	=> $userId,
			'gid'	=> $groupId
		);
		$sql = 'INSERT INTO '. self::T_UG .' (user_id, group_id) VALUES (:uid, :gid)';
		
		try {
			$newId = $this->db->run_insert($sql, $params, self::S_UG);
		}
		catch(Exception $ex) {
			throw new exception(__METHOD__ .": failed to create user group, DETAILS::: ". $ex->getMessage());
		}
		
		return($newId);
	}//end create_user_group()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function create_user_permission($userId, $perms) {
		$permInt = $perms;
		if(!is_numeric($perms)) {
			$permInt = $this->get_perms_from_string($perms);
		}
		$params = array(
			'uid'	=> $userId,
			'perms'	=> $permInt
		);
		
		$sql = 'INSERT INTO '. self::T_UP .' (user_id, permissions) VALUES '
				.'(:uid, :perms)';
		
		try {
			$newId = $this->db->run_insert($sql, $params, self::S_UP);
		}
		catch(Exception $ex) {
			throw new exception(__METHOD__ .": failed to create user permission, DETAILS::: ". $ex->getMessage());
		}
		
		return($newId);
	}//end create_user_permission()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function can_create($perms) {
		$permInt = $perms;
		if(!is_numeric($permInt)) {
			$permInt = $this->get_perms_from_string($perms);
		}
		
		$retval = false;
		if($permInt & CS_CREATE) {
			$retval = true;
		}
		return($retval);
	}//end can_create()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function can_read($perms) {
		$permInt = $perms;
		if(!is_numeric($permInt)) {
			$permInt = $this->get_perms_from_string($perms);
		}
		
		$retval = false;
		if($permInt & CS_READ) {
			$retval = true;
		}
		return($retval);
	}//end can_read()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function can_update($perms) {
		$permInt = $perms;
		if(!is_numeric($permInt)) {
			$permInt = $this->get_perms_from_string($perms);
		}
		
		$retval = false;
		if($permInt & CS_UPDATE) {
			$retval = true;
		}
		return($retval);
	}//end can_update()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function can_delete($perms) {
		$permInt = $perms;
		if(!is_numeric($permInt)) {
			$permInt = $this->get_perms_from_string($perms);
		}
		
		$retval = false;
		if($permInt & CS_DELETE) {
			$retval = true;
		}
		return($retval);
	}//end can_delete()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function create_perms_from_bools($create, $read, $update, $delete) {
		$retval = 0;
		if($create) {
			$retval |= CS_CREATE;
		}
		if($read) {
			$retval |= CS_READ;
		}
		if($update) {
			$retval |= CS_UPDATE;
		}
		if($delete) {
			$retval |= CS_DELETE;
		}
		
		return($retval);
	}//end create_perms_from_bools()
	//--------------------------------------------------------------------------
}
?>