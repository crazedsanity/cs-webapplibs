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

abstract class cs_gdlPathAbstract extends cs_gdlObjectAbstract {

	
	const table='cswal_gdl_path_table';
	const tableSeq = 'cswal_gdl_path_table_path_id_seq';	
	
	
	
	
	
	//-------------------------------------------------------------------------
	public function create_path($path) {
		$idList = $this->create_path_objects($path);
		$pathIdList = $this->create_id_path($idList);
		
		if($this->get_path_from_idlist($pathIdList)) {
			$retval = $pathIdList;
		}
		else {
			
			$sql = "INSERT INTO ". self::table ." (path_id_list) VALUES ('". $pathIdList ."')";
			
			try {
				$insertedId = $this->db->run_insert($sql, self::tableSeq);
				$retval = $pathIdList;
			}
			catch(exception $e) {
				throw new exception(__METHOD__ .": unable to create path::: ". $e->getMessage());
			}
		}
		
		return($retval);
	}//end create_path()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function clean_path($path, $appendBase=true) {
		if(strlen($path)) {
			if($appendBase === true && !is_null($this->basePath)) {
				$path = $this->basePath .'/'. $path;
			}
			$newPath = preg_replace('/\/{2,}/', '/', $path);
			
			if(!strlen($newPath)) {
				throw new exception(__METHOD__ .": new path is zero-length (". $newPath ."), old path=(". func_get_arg(0) .")");
			}
		}
		else {
			throw new exception(__METHOD__ .": no valid path given (". $path .")");
		}
		
		return($newPath);
	}//end clean_path()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function set_base_path($path) {
		if(is_null($path) || !strlen($path)) {
			$this->basePath = null;
		}
		else {
			$this->basePath = $this->clean_path($path,false);
		}
	}//end set_base_path()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function explode_path($path, $appendBase=true) {
		$path = $this->clean_path($path, $appendBase);
		$path = preg_replace('/^\//', '', $path);
		$path = preg_replace('/\/$/', '', $path);
		
		return(explode('/', $path));
	}//end explode_path()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function create_id_path(array $idList) {
		$retval = ':'. $this->gfObj->string_from_array($idList, null, '::') .':';
		return($retval);
	}//end create_id_path()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_path_from_idlist($idPath) {
		
		$idList = explode('::', preg_replace('/^:/', '', preg_replace('/:$/', '', $idPath)));
		
		$nameList = $this->build_object_name_list($idList);
		
		$retval = "/";
		foreach($nameList as $id=>$name) {
			$retval = $this->gfObj->create_list($retval, $name, '/');
		}
		
		$retval = $this->clean_path($retval,false);
		
		return($retval);
	}//end get_text_path_from_id_path()
	//-------------------------------------------------------------------------
	
}
?>