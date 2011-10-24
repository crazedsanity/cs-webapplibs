<?php
/*
 * Created on June 24, 2010
 *
 *  SVN INFORMATION:::
 * -------------------
 * Last Author::::::::: $Author$ 
 * Current Revision:::: $Revision$ 
 * Repository Location: $HeadURL$ 
 * Last Updated:::::::: $Date$
 */

abstract class cs_singleTableHandlerAbstract extends cs_webapplibsAbstract {
	
	protected $gfObj;
	protected $tableName;
	protected $seqName;
	protected $pkeyField;
	protected $cleanStringArr;
	protected $dbParams;
	
	//-------------------------------------------------------------------------
	/**
	 * Generic way of using a class to define how to update a single database table.
	 * 
	 * @param $dbObj			(object) Connected instance of cs_phpDB{}.
	 * @param $tableName		(str) Name of table inserting/updating.
	 * @param $seqName			(str) Name of sequence, used with PostgreSQL for retrieving the last inserted ID.
	 * @param $pkeyField		(str) Name of the primary key field, for performing updates & retrieving specific records.
	 * @param $cleanStringArr	(array) Array of {fieldName}=>{dataType} for allowing updates & creating records.
	 */
    public function __construct(cs_phpDB $dbObj, $tableName, $seqName, $pkeyField, array $cleanStringArr) {
		$this->set_version_file_location(dirname(__FILE__) . '/../VERSION');
		parent::__construct(true);
		
		if(isset($dbObj) && is_object($dbObj) && $dbObj->is_connected()) {
			$this->dbObj = $dbObj;
		}
		else {
			throw new exception(__METHOD__ .":: database object not connected or not passed");
		}
		
		if(is_string($tableName) && strlen($tableName)) {
			$this->tableName = $tableName;
		}
		else {
			throw new exception(__METHOD__ .":: invalid table name (". $tableName .")");
		}
		
		if(is_string($seqName) && strlen($seqName)) {
			$this->seqName = $seqName;
		}
		else {
			throw new exception(__METHOD__ .":: invalid sequence name (". $seqName .")");
		}
		
		if(is_string($pkeyField) && strlen($pkeyField)) {
			$this->pkeyField = $pkeyField;
		}
		else {
			throw new exception(__METHOD__ .":: invalid primary key field name (". $pkeyField .")");
		}
		
		if(is_array($cleanStringArr) && count($cleanStringArr)) {
			$this->cleanStringArr = $cleanStringArr;
		}
		else {
			throw new exception(__METHOD__ .":: invalid clean string array");
		}
    }//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/** 
	 * Insert a new record into the table.
	 * 
	 * @param $data		(array) field=>value pairs of data to be inserted.
	 * 
	 * @RETURN (int)	SUCCESS: the (int) is the last inserted ID.
	 * @EXCEPTION		FAIL: exception indicates the error.
	 */
	public function create_record(array $data, $removeEmptyVals=TRUE) {
		if(is_array($data) && count($data)) {
			$sql = 'INSERT INTO '. $this->tableName .' '
				. $this->gfObj->string_from_array($data, 'insert', null, $this->cleanStringArr, $removeEmptyVals);
			try {
				$newId = $this->dbObj->run_insert($sql, $this->seqName);
			}
			catch(Exception $e) {
				throw new exception(__METHOD__ .":: failed to create record, DETAILS::: ". $e->getMessage());
			}
		}
		else {
			throw new exception(__METHOD__ .":: no data passed");
		}
		return($newId);
	}//end create_record()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Retrieve a record based on a given ID, such as was returned from create_record().
	 * 
	 * @param $recId		(int) ID to retrieve.
	 * 
	 * @RETURN (array)		SUCCESS: list of field=>value of data from database.
	 * @EXCEPTION			FAIL: exception indicates the error.
	 */
	public function get_record_by_id($recId) {
		if(is_numeric($recId)) {
			try {
				$data = $this->get_records(array($this->pkeyField => $recId));
				if(isset($data[$recId])) {
					$data = $data[$recId];
				}
				else {
					throw new exception(__METHOD__ .": returned data did not contain ID (". $recId .")");
				}
			}
			catch(Exception $e) {
				throw new exception(__METHOD__ .":: error while retrieving record (". $recId ."), DETAILS::: ". $e->getMessage());
			}
		}
		else {
			throw new exception(__METHOD__ .":: failed to retrieve record (". $recId ."), DETAILS::: ". $e->getMessage());
		}
		return($data);
	}//end get_record_by_id()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Just a simple wrapper to get_records(), guaranteed to return a single record.
	 * 
	 * @param $filter			(array) fieldname=>value list of filters.
	 * 
	 * @RETURN (array)			SUCCESS: returns single record with all fields.
	 * @EXCEPTION 				FAIL: exception indicates error 
	 */
	public function get_single_record(array $filter) {
		if(is_array($filter) && count($filter)) {
			try {
				$data = $this->get_records($filter, null, 1);
				
				if(is_array($data)) {
					$keys = array_keys($data);
					$retval = $data[$keys[0]];
				}
				else {
					//technically, the call to get_records() got boolean(false) from cs_phpDB::run_query(), so we could just return $data directly...
					$retval = false;
				}
			}
			catch(Exception $e) {
				throw new exception(__METHOD__ .":: failed to retrieve record, DETAILS::: ". $e->getMessage());
			}
		}
		else {
			throw new exception(__METHOD__ .":: no filter passed");
		}
		
		return($retval);
	}//end get_single_record()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Retrieves a number of records based on arguments.
	 * 
	 * @$filter 		(array) Field=>value list of filters (i.e. 'my_id'=>1)
	 * @$orderBy		(str) Field to order by; can contain "DESC" or "ASC".
	 * @$limit			(int) How many max records to display.
	 * @$offset			(int) Offset by this number of records.
	 * 
	 * @RETURN (array)	SUCCESS: Primary index is the record ID, sub-array is same as returned by get_record_by_id().
	 * @EXCEPTION		FAIL: exception indicates error.
	 */
	public function get_records(array $filter=null, $orderBy=null, $limit=null, $offset=null) {
		$limitOffsetStr = '';
		if(is_numeric($limit) && $limit > 0) {
			$limitOffsetStr = ' LIMIT '. $limit;
			
			//While it appears to be acceptable to provide an offset without a limit, it seems ridiculous to me.
			if(is_numeric($offset) && $offset > 0) {
				$limitOffsetStr .= ' OFFSET '. $offset;
			}
		}
		
		$orderByStr = ' ORDER BY '. $this->pkeyField;
		if(is_string($orderBy) && strlen($orderBy)) {
			$orderByStr = ' ORDER BY '. $orderBy;
		}
		
		$filterStr = '';
		if(is_array($filter) && count($filter) > 0) {
			$filterSql = $this->gfObj->string_from_array($filter, 'select', null, $this->cleanStringArr, true);
			if(strlen($filterSql) > 2) {
				$filterStr = ' WHERE '. $filterSql;
			}
			else {
				throw new exception(__METHOD__ .":: no filter created (". $this->gfObj->debug_print($filter,0) .")");
			}
		}
		
		$sql = 'SELECT * FROM '. $this->tableName . $filterStr . $orderByStr . $limitOffsetStr;
		try {
			$data = $this->dbObj->run_query($sql, $this->pkeyField);
		}
		catch(Exception $e) {
			throw new exception(__METHOD__ .":: failed to retrieve records, DETAILS::: ". $e->getMessage());
		}
		return($data);
	}//end get_records()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_records_using_custom_filter($filter, $orderBy=null, $limit=null, $offset=null) {
		if(is_string($filter) && strlen($filter)) {
			$limitOffsetStr = '';
			if(is_numeric($limit) && $limit > 0) {
				$limitOffsetStr = ' LIMIT '. $limit;
				
				//using an offset without a limit seems silly...
				if(is_numeric($limitOffsetStr) && $offset > 0) {
					$limitOffsetStr .= ' OFFSET '. $offset;
				}
			}
			
			$orderByStr = ' ORDER BY '. $this->pkeyField;
			if(is_string($orderBy) && strlen($orderBy)) {
				$orderByStr = ' ORDER BY '. $orderBy;
			}
			
			$sql = 'SELECT * FROM '. $this->tableName ." WHERE ". $filter . $orderByStr . $limitOffsetStr;
			try {
				$data = $this->dbObj->run_query($sql, $this->pkeyField);
			}
			catch(Exception $e) {
				throw new exception(__METHOD__ .":: failed to retrieve records, DETAILS::: ". $e->getMessage());
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid filter (". $filter .")");
		}
		return($data);
	}//end get_records_using_custom_filter()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Update a single record with the given changes.
	 * 
	 * @recId			(int) ID to update.
	 * @updates			(array) field=>value list of changes.
	 * 
	 * @RETURN (int)	SUCCESS: (int) is the number of records updated (should always be 1)
	 * @EXCEPTION		FAIL: exception indicates the error.
	 */
	public function update_record($recId, array $updates, $removeEmptyVals=true, $appendToUpdateString=null) {
		if(is_numeric($recId) && $recId >= 0 && is_array($updates) && count($updates) > 0) {
			$updateString = $this->gfObj->string_from_array($updates, 'update', null, $this->cleanStringArr, $removeEmptyVals);
			if(is_null($updateString) || !strlen($updateString) || strlen($updateString) < 3) {
				throw new exception(__METHOD__ .":: no update string created (". $updateSTring ."), contents::: ". $this->gfObj->debug_var_dump($updates,0));
			}
			else {
				$sql = 'UPDATE '. $this->tableName .' SET '
					. $updateString . $appendToUpdateString
					.' WHERE '. $this->pkeyField .'='. $recId;
				try {
					$retval = $this->dbObj->run_update($sql, true);
				}
				catch(Exception $e) {
					throw new exception(__METHOD__ .":: failed to update record (". $recId ."), DETAILS::: ". $e->getMessage());
				}
			}
		}
		else {
			throw new exception(__METHOD__ .":: failed to update record (". $recId ."), invalid recordId (". $recId ."), or no data in array::: ". $this->gfObj->debug_var_dump($updates,0));
		}
		return($retval);
	}//end update_record()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * TODO: only allow 1 record to be deleted, or a specific number of records (add transaction logic)?
	 */
	public function delete_record($recId) {
		if(is_numeric($recId)) {
			$sql = "DELETE FROM ". $this->tableName ." WHERE ". $this->pkeyField ."=". $recId;
			try {
				$result = $this->dbObj->run_update($sql);
			}
			catch(Exception $e) {
				throw new exception(__METHOD__ .": failed to delete record, DETAILS::: ". $e->getMessage());
			}
		}
		else {
			throw new exception(__METHOD__ .":: failed to delete record, invalid data (". $recId .")");
		}
		return($result);
	}//end delete_record()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_last_query() {
		return($this->dbObj->get_last_query());
	}//end get_last_query();
	//-------------------------------------------------------------------------
}

?>
