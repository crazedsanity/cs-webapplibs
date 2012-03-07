<?php
/*
 * Created on Jan 29, 2009
 * 
 * FILE INFORMATION:
 * 
 * $HeadURL$
 * $Id$
 * $LastChangedDate$
 * $LastChangedBy$
 * $LastChangedRevision$
 */

abstract class cs_gdlObjectAbstract extends cs_webapplibsAbstract {

	
	const table='cswal_gdl_object_table';
	const tableSeq = 'cswal_gdl_object_table_object_id_seq';	
	
	//-------------------------------------------------------------------------
	public function get_object_id_from_path($path) {
		$sql = "SELECT object_id FROM ". self::table ." WHERE object_path='". 
				$this->clean_path($path) ."'";
		
		try {
			$data = $this->db->run_query($sql);
			
			if(is_array($data) && count($data) == 1) {
				$retval = $data['object_id'];
			}
			else {
				throw new exception(__METHOD__ .": invalid data for path (". $this->clean_path($path) .")::: ". $this->gfObj->debug_var_dump($data,0));
			}
		}
		catch(exception $e) {
			throw new exception(__METHOD__ .": error retrieving path::: ". $e->getMessage());
		}
		
		return($retval);
	}//end get_object_id_from_path()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function create_object($name, $data=null, $type=null) {
		$sql = "INSERT INTO ". self::table ." (object_name) VALUES ('". 
				$this->gfObj->cleanString($this->clean_path($name), 'sql_insert') ."')";
		try {
			$retval = $this->db->run_insert($sql, self::tableSeq);
		}
		catch(exception $e) {
			throw new exception(__METHOD__ .": failed to perform insert::: ". $e->getMessage());
		}
			
		if(!is_null($data)) {
			throw new exception(__METHOD__ .": can't create data for objects yet");
		}
		return($retval);
	}//end create_object()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function build_object_id_list(array $objects) {
		$this->gfObj->switch_force_sql_quotes(1);
		$sql = "SELECT * FROM ". self::table ." WHERE object_name IN (".
				$this->gfObj->string_from_array($objects, null, ',', 'sql') .")";
		$this->gfObj->switch_force_sql_quotes(0);
		
		$retval = array();
		try {
			$retval = $this->db->run_query($sql, 'object_name', 'object_id');
			if(!is_array($retval)) {
				$retval = array();
			}
		}
		catch(exception $e) {
			//throw new exception(__METHOD__ .": failed to retrieve list");
		}
		
		return($retval);
	}//end build_object_id_list()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function build_object_name_list(array $objectIds) {
		$this->gfObj->switch_force_sql_quotes(1);
//string_from_array($array,$style=NULL,$separator=NULL, $cleanString=NULL, $removeEmptyVals=FALSE)
		foreach($objectIds as $i=>$myId) {
			if(!strlen($myId)) {
				unset($objectIds[$i]);
			}
		}
		$sql = "SELECT * FROM ". self::table ." WHERE object_id IN (".
				$this->gfObj->string_from_array($objectIds, null, ',', 'sql',true) .")";
		$this->gfObj->switch_force_sql_quotes(0);
		
		try {
			$retval = $this->db->run_query($sql, 'object_id', 'object_name');
			if(!is_array($retval)) {
				$retval = array();
			}
		}
		catch(exception $e) {
			throw new exception(__METHOD__ .": failed to retrieve list::: ". $e->getMessage());
		}
		
		return($retval);
	}//end build_object_id_list()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function create_objects_enmasse(array $objectList) {
		$retval = 0;
		foreach($objectList as $name) {
			try {
				$this->create_object($name);
				$retval++;
			}
			catch(exception $e) {
				//nothing to see here, move along.
			}
		}
		return($retval);
	}//end create_objects_enmasse()
	//-------------------------------------------------------------------------
	
	
}
?>
