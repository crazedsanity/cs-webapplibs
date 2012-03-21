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

abstract class cs_gdlAttribAbstract extends cs_gdlPathAbstract {

	
	const table='cswal_gdl_attribute_table';
	const tableSeq = 'cswal_gdl_attribute_table_attribute_id_seq';	
	
	
	
	//-------------------------------------------------------------------------
	public function create_attrib($path, $data, $type=null) {
		
		$objectId = $this->get_object_id_from_path($path);
		
		$insertString = "";
		$attribs = array();
		if(is_array($data) && count($data)) {
			foreach($data as $n=>$v) {
				$n = $this->translate_attrib_name($n);
				$attribs[$n] = $v;
			}
		}
		elseif(!is_null($type)) {
			$key = $this->translate_attrib_name($type);
			$attribs = array($key => $data);
		}
		else {
			throw new exception(__METHOD__ .": data was not array and no type set");
		}
		
		if(!is_array($attribs) || !count($attribs)) {
			throw new exception(__METHOD__ .": failed to create an array of attributes... ". $this->gfObj->debug_print(func_get_args(),0));
		}
		
		$attribs['object_id'] = $objectId;
		$insertString = $this->gfObj->string_from_array($attribs, 'insert');
		
		
		if(!strlen($insertString)) {
			throw new exception(__METHOD__ .": invalid insert string (". $insertString .")");
		}
		$sql = "INSERT INTO ". self::attrTable ." ". $insertString;
		
		try {
			$retval = $this->db->run_insert($sql, self::attrTableSeq);
		}
		catch(exception $e) {
			throw new exception(__METHOD__ .": failed to perform insert::: ". $e->getMessage() .' ---- '. $sql);
		}
		
		return($retval);
	}//end create_attrib()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function translate_attrib_name($name) {
		$retval = null;
		foreach($this->validTypes as $type) {
			if(preg_match('/^'. $type .'/', $name)) {
				$retval = 'a_'. $type;
				break;
			}
		}
		
		if(is_null($retval) || !strlen($retval)) {
			$this->gfObj->debug_print(__METHOD__ .": name was (". $name ."), retval=(". $retval .")",1);
			throw new exception(__METHOD__ .": invalid attribute name (". $name .")");
		}
		
		return($retval);
	}//end translate_attrib_name()
	//-------------------------------------------------------------------------
	
}
?>