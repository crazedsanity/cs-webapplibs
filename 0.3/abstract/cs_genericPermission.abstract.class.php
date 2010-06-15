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

abstract class cs_genericPermissionAbstract extends cs_webapplibsAbstract {
	
	/** Database object. */
	public $db;
	
	/** cs_globalFunctions object, for cleaning strings & such. */
	public $gfObj;
	
	/** Table name used to store permissions. */
	const permTable = "cswal_permission_table";
	
	/** Sequence for permissions table. */
	const permSeq = "cswal_permission_table_permission_id";
	
	//============================================================================
	public abstract function __construct(cs_phpDB $db) {
		$this->db = $db;
		$this->gfObj = new cs_globalFunctions;
	}//end __construct()
	//============================================================================
	
	
	
	//============================================================================
	protected function clean_permission_name($name) {
		if(!is_null($name) && is_string($name) && strlen($name)) {
			$name = $this->gfObj->cleanString(strtolower($name), 'email');
		}
		else {
			throw new exception(__METHOD__ .":: invalid string (". $name .")");
		}
	}//end clean_permission_name()
	//============================================================================
	
	
	
	//============================================================================
	public function create_permission($name) {
		try{
			$name = $this->clean_permission_name($name);
			$sql = "INSERT INTO ". self::permTable ." (permission_name) VALUES ('". $name ."')";
			$newId = $this->db->run_insert($sql, self::permSeq);
		}
		catch(Exception $e) {
			throw new exception(__METHOD__ .":: failed to create new record, DETAILS::: ". $e->getMessage());
		}
		
		return($newId);
	}//end create_permission()
	//============================================================================
	
	
	
	//============================================================================
	public function get_permission($name) {
		try {
			$name = $this->clean_permission_name($name);
			$sql = "SELECT * FROM ". self::permTable ." WHERE permission_name='". $name ."'";
			$retval = $this->db->run_query($sql);
		}
		catch(Exception $e) {
			throw new exception(__METHOD__ .":: error while locating permission '". $name ."', DETAILS::: ". $e->getMessage());
		}
		
		return($retval);
	}//end get_permission()
	//============================================================================
	
	
	
	//============================================================================
	public function get_permission_by_id($permId) {
		try {
			if(!is_null($permId) && is_numeric($permId)) {
				$sql = "SELECT * FROM ". self::permTable ." WHERE permission_id='". $permId ."'";
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
	
	
	
	//============================================================================
	/**
	 * Build the schema for permissions.
	 */
	private function build_schema() {
		try {
			$result = $this->db->run_sql_file(dirname(__FILE__) .'/../setup/genericPermissions.pgsql.sql');
		}
		catch(Exception $e) {
			throw new exception(__METHOD__ .":: failed to create schema, DETAILS::: ". $e->getMessage());
		}
		if($result !== true) {
			throw new exception(__METHOD__ .":: failed to create schema (no details)");
		}
	}//end build_schema()
	//============================================================================
}
?>
