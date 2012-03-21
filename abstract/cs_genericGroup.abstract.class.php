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

abstract class cs_genericGroupAbstract extends cs_webapplibsAbstract {
	
	/** Database object. */
	public $db;
	
	/** cs_globalFunctions object, for cleaning strings & such. */
	public $gfObj;
	
	/** Table name used to store groups. */
	const groupTable = "cswal_group_table";
	
	/** Sequence for groups table. */
	const groupSeq = "cswal_group_table_group_id_seq";
	
	//============================================================================
	public function __construct(cs_phpDB $db) {
		$this->db = $db;
		$this->gfObj = new cs_globalFunctions;
	}//end __construct()
	//============================================================================
	
	
	
	//============================================================================
	protected function clean_group_name($name) {
		if(!is_null($name) && is_string($name) && strlen($name)) {
			$name = $this->gfObj->cleanString(strtolower($name), 'email');
		}
		else {
			throw new exception(__METHOD__ .":: invalid string (". $name .")");
		}
		return($name);
	}//end clean_group_name()
	//============================================================================
	
	
	
	//============================================================================
	public function create_group($name) {
		try{
			$name = $this->clean_group_name($name);
			$sql = "INSERT INTO ". self::groupTable ." (group_name) VALUES ('". $name ."')";
			$newId = $this->db->run_insert($sql, self::groupSeq);
		}
		catch(Exception $e) {
			throw new exception(__METHOD__ .":: failed to create new record, DETAILS::: ". $e->getMessage());
		}
		
		return($newId);
	}//end create_group()
	//============================================================================
	
	
	
	//============================================================================
	public function get_group($name) {
		try {
			$name = $this->clean_group_name($name);
			$sql = "SELECT * FROM ". self::groupTable ." WHERE group_name='". $name ."'";
			$retval = $this->db->run_query($sql);
		}
		catch(Exception $e) {
			throw new exception(__METHOD__ .":: error while locating group '". $name ."', DETAILS::: ". $e->getMessage());
		}
		
		return($retval);
	}//end get_group()
	//============================================================================
	
	
	
	//============================================================================
	public function get_all_groups() {
		try {
			$sql = "SELECT * FROM ". self::groupTable ." ORDER BY group_name";
			$retval = $this->db->run_query($sql);
		}
		catch(Exception $e) {
			throw new exception(__METHOD__ .":: failed to retrieve groups, DETAILS::: ". $e->getMessage());
		}
		
		return($retval);
	}//end get_all_groups()
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
			throw new exception(__METHOD__ .":: error while locating group '". $groupId ."', DETAILS::: ". $e->getMessage());
		}
		
		return($retval);
	}//end get_group_by_id()
	//============================================================================
	
	
	
	//============================================================================
	/**
	 * Build the schema for the generic permissions system.
	 */
	protected function build_schema() {
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
