<?php
/*
 * Created on February 25, 2011
 * 
 * FILE INFORMATION:
 * 
 * $HeadURL$
 * $Id$
 * $LastChangedDate$
 * $LastChangedBy$
 * $LastChangedRevision$
 */

abstract class cs_genericChatCategoryAbstract extends cs_webapplibsAbstract {
	
	/** Database object. */
	public $db;
	
	/** cs_globalFunctions object, for cleaning strings & such. */
	public $gfObj;
	
	/** Table name used to store categories. */
	protected $myTable = "cswal_chat_category_table";
	
	/** Sequence for chat category table. */
	protected $mySeq = "cswal_chat_category_table_change_category_id_seq";
	
	/** Table handler object for simple SQL handling */
	private $dbTableHandler;
	
	/** Internal categoryId to use...  */
	private $categoryId=0;
	
	//============================================================================
	public function __construct(cs_phpDB $db) {
		$this->db = $db;
		$this->gfObj = new cs_globalFunctions;
		
		//setup table handler.
		$cleanString = array(
			'category_name'		=> "text"
		);
		$this->dbTableHandler = new cs_dbTableHandler($this->db, $this->myTable, $this->mySeq, 'chat_category_id', $cleanString);
	}//end __construct()
	//============================================================================
	
	
	
	//============================================================================
	public function set_category_id($categoryId) {
		if(is_numeric($categoryId)) {
			$this->categoryId=$categoryId;
		}
		else{
			throw new exception(__METHOD__ .": invalid categoryId (". $categoryId .")");
		}
		return($this->categoryId);
	}//end set_category_id()
	//============================================================================
	
	
	
	//============================================================================
	/**
	 * Build the schema for the generic chat system.
	 */
	protected function build_schema() {
		try {
			$result = $this->db->run_sql_file(dirname(__FILE__) .'/../setup/genericChat.pgsql.sql');
		}
		catch(Exception $e) {
			throw new exception(__METHOD__ .":: failed to create schema, DETAILS::: ". $e->getMessage());
		}
		if($result !== true) {
			throw new exception(__METHOD__ .":: failed to create schema (no details)");
		}
	}//end build_schema()
	//============================================================================
	
	
	
	//============================================================================
	public function create_category($name) {
		try {
			$result = $this->dbTableHandler->create_record(array('category_name' => $name));
		}
		catch(Exception $e) {
			throw new exception(__METHOD__ .":: failed to create record, DETAILS::: ". $e->getMessage());
		}
		return($result);
	}//end create_category()
	//============================================================================
	
	
	
	//============================================================================
	public function update_category($id, $name) {
		try {
			$result = $this->update_record($id, array('category_name' => $name));
		}
		catch(Exception $e) {
			throw new exception(__METHOD__ .":: failed to update record, DETAILS::: ". $e->getMessage());
		}
		return($result);
	}//end update_category()
	//============================================================================

}

