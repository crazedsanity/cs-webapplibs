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
	 * @dbObj			(object) Connected instance of cs_phpDB{}.
	 * @tableName		(str) Name of table inserting/updating.
	 * @seqName			(str) Name of sequence, used with PostgreSQL for retrieving the last inserted ID.
	 * @pkeyField		(str) Name of the primary key field, for performing updates & retrieving specific records.
	 * @cleanStringArr	(array) Array of {fieldName}=>{dataType} for allowing updates & creating records.
	 */
    function __construct(cs_phpDB $dbObj, $tableName, $seqName, $pkeyField, array $cleanStringArr) {
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
	 * @$data			(array) field=>value pairs of data to be inserted.
	 * 
	 * @RETURN (int)	SUCCESS: the (int) is the last inserted ID.
	 * @EXCEPTION		FAIL: exception indicates the error.
	 */
	protected function create_record(array $data) {
		$sql = 'INSERT INTO '. $this->tableName .' '
			. $this->gfObj->string_from_array($data, 'insert', null, $this->cleanStringArr, true);
		try {
			$newId = $this->dbObj->run_insert($sql, $this->seqName);
		}
		catch(Exception $e) {
			throw new exception(__METHOD__ .":: failed to create record, DETAILS::: ". $e->getMessage());
		}
		return($newId);
	}//end create_record()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Retrieve a record based on a given ID, such as was returned from create_record().
	 * 
	 * @$recId			(int) ID to retrieve.
	 * 
	 * @RETURN (array)	SUCCESS: list of field=>value of data from database.
	 * @EXCEPTION		FAIL: exception indicates the error.
	 */
	protected function get_record_by_id($recId) {
		if(is_numeric($recId)) {
			try {
				$data = $this->get_records(array($this->pkeyField => $recId));
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
	 * Retrieves a number of records based on arguments.
	 * 
	 * @$filter 		(array) Field=>value list of filters (i.e. 'my_id'=>1)
	 * @orderBy			(str) Field to order by; can contain "DESC" or "ASC".
	 * @limit			(int) How many max records to display.
	 * @offset			(int) Offset by this number of records.
	 * 
	 * @RETURN (array)	SUCCESS: Primary index is the record ID, sub-array is same as returned by get_record_by_id().
	 * @EXCEPTION		FAIL: exception indicates error.
	 */
	protected function get_records(array $filter=null, $orderBy=null, $limit=null, $offset=null) {
		$limitOffsetStr = '';
		if(is_numeric($limit) && $limit > 0) {
			$limitOffsetStr = ' LIMIT '. $limit;
			
			//While it appears to be acceptable to provide an offset without a limit, it seems ridiculous to me.
			if(is_numeric($offset) && $offset > 0) {
				$limitOffsetStr .= ' OFFSET '. $offset;
			}
		}
		
		$orderByStr = '';
		if(is_string($orderBy) && strlen($orderBy)) {
			$orderByStr = ' ORDER BY '. $orderBy;
		}
		
		$filterStr = '';
		if(is_array($filter) && count($filter) > 0) {
			$filterStr = ' WHERE '. $this->gfObj->string_from_array($filter, 'select', null, $this->cleanStringArr, true);
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
	/**
	 * Update a single record with the given changes.
	 * 
	 * @recId			(int) ID to update.
	 * @updates			(array) field=>value list of changes.
	 * 
	 * @RETURN (int)	SUCCESS: (int) is the number of records updated (should always be 1)
	 * @EXCEPTION		FAIL: exception indicates the error.
	 */
	protected function update_record($recId, array $updates) {
		if(is_numeric($recId) && $recId >= 0 && is_array($updates) && count($updates) > 0) {
			$sql = 'UPDATE '. $this->tableName .' SET '
				. $this->gfObj->string_from_array($updates, 'update', null, $this->cleanStringArr, true)
				.' WHERE '. $this->pkeyField .'='. $recId;
			try {
				$retval = $this->dbObj->run_update($sql, true);
			}
			catch(Exception $e) {
				throw new exception(__METHOD__ .":: failed to update record (". $recId ."), DETAILS::: ". $e->getMessage());
			}
		}
		else {
			throw new exception(__METHOD__ .":: failed to update record (". $recId ."), DETAILS::: ". $e->getMessage());
		}
		return($retval);
	}//end update_record()
	//-------------------------------------------------------------------------
}

?>
