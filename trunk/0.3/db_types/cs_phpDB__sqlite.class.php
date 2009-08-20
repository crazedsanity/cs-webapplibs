<?php

/*
 * A class for generic SQLite database access.
 * 
 * SVN INFORMATION:::
 * SVN Signature:::::::: $Id$
 * Last Committted Date: $Date$
 * Last Committed Path:: $HeadURL$
 * 
 */


class cs_phpDB__sqlite extends cs_phpDBAbstract {

	/** Internal result set pointer. */
	protected $result = NULL;
	
	/** Internal error code. */
	protected $errorCode = 0;
	
	/** Status of the current transaction. */
	protected $transStatus = NULL;
	
	/** Whether there is a transaction in progress or not. */
	protected $inTrans = FALSE;
	
	/** Holds the last query performed. */
	protected $lastQuery = NULL;
	
	/** List of queries that have been run */
	protected $queryList=array();
	
	/** How many seconds to wait for a query before cancelling it. */
	protected $timeOutSeconds = NULL;
	
	/** Internal check to determine if a connection has been established. */
	protected $isConnected=FALSE;
	
	/** Internal check to determine if the parameters have been set. */
	protected $paramsAreSet=FALSE;
	
	/** Resource handle. */
	protected $connectionID = -1;
	
	/** Name of the database */
	protected $dbname;
	
	/** Directory that is readable + writable, which contains the SQLite db file. */
	protected $rwDir;
	
	/** cs_globalFunctions object, for string stuff. */
	protected $gfObj;
	
	/** Internal check to ensure the object has been properly created. */
	protected $isInitialized=FALSE;
	
	/** List of prepared statements, indexed off the name, with the sub-array being fieldname=>dataType. */
	protected $preparedStatements = array();
	
	/** Set to TRUE to save all queries into an array. */
	protected $useQueryList=FALSE;
	
	/** array that essentially remembers how many times beginTrans() was called. */
	protected $transactionTree = NULL;
	
	////////////////////////////////////////////
	// Core primary connection/database function
	////////////////////////////////////////////
	
	
	//=========================================================================
	public function __construct() {
		parent::__construct();
	}//end __construct()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Set appropriate parameters for database connection
	 */
	public function set_db_info(array $params){
		$this->sanity_check();
		$required = array('rwDir', 'dbname');
		
		$requiredCount = 0;
		foreach($params as $index=>$value) {
			if(property_exists($this, $index) && in_array($index, $required)) {
				$this->$index = $value;
				$requiredCount++;
			}
			else {
				throw new exception(__METHOD__. ": property (". $index .") does " .
					"not exist or isn't allowed");
			}
		}
		
		if($requiredCount == count($required)) {
			$this->paramsAreSet = TRUE;
		}
		else {
			throw new exception(__METHOD__ .": required count (". $requiredCount 
				.") does not match required number of fields (". count($required) .")");
		}
	}//end set_db_info()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Standard method to close connection.
	 */
	function close() {
		$this->isConnected = FALSE;
		$retval = null;
		if($this->connectionID != -1) {
			sqlite_close($this->dbConnObj);
			$retval = TRUE;
		}
		else {
			throw new exception(__METHOD__ .": Failed to close connection: connection is invalid");
		}
		
		return($retval);
	}//end close()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Connect to the database
	 */
	function connect(array $dbParams=NULL, $forceNewConnection=FALSE){
		$this->sanity_check();
		$retval = NULL;
		$connectError = NULL;
		
		$this->set_db_info($dbParams);
		
		if($this->paramsAreSet === TRUE && $this->isConnected === FALSE) {
			
			$dbFile = $this->rwDir .'/'. $this->dbname;
			$this->connectionID = sqlite_open($dbFile, $connectError);
			
			if(is_resource($this->connectionID)) {
				$this->errorCode=0;
				$this->isConnected = TRUE;
				$retval = $this->connectionID;
			}
			else {
				throw new exception(__METHOD__ .": FATAL ERROR: ". $connectError);
			}
		}
		else {
			throw new exception(__METHOD__ .": paramsAreSet=(". $this->paramsAreSet ."), isConnected=(". $this->isConnected .")");
		}
		
		return($retval);
	}//end connect()
	//=========================================================================
	
	
	
	//=========================================================================
	/** 
	 * Run sql queries
	 * 
	 * TODO: re-implement query logging (setting debug, logfilename, etc).
	 */
	function exec($query) {
		$this->lastQuery = $query;
		if($this->useQueryList) {
			$this->queryList[] = $query;
		}
		$returnVal = false;
		
		if($this->connectionID != -1) {
			$this->result = @sqlite_query($this->connectionID, $query);

			if($this->result !== false) {
				if (eregi("^[[:space:]]*select", $query)) {
					//If we didn't have an error and we are a select statement, move the pointer to first result
					$numRows = $this->numRows();
					$returnVal = $numRows;
				}
				else {
					//We got something other than an update. Use numAffected
					$returnVal = $this->numAffected();
				}
			}
 		}
		return($returnVal);
	}//end exec()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Returns any error caused by the last executed query.
	 * 
	 * @return NULL			OK: no error
	 * @return (string)		FAIL: contains error returned from the query.
	 */
	function errorMsg($setMessage=NULL,$logError=NULL) {
		$this->sanity_check();
		if ($this->connectionID < 0 || !is_resource($this->connectionID)) {
			$retVal = "Failed to open connection to database (". $this->dbname .")";
		} else {
			$errorCode = sqlite_last_error($this->connectionID);
			if($errorCode === 0) {
				$retVal = NULL;
			}
			else {
				$retVal = sqlite_error_string($errorCode);
			}
		}

		return($retVal);
	}//end errorMsg()
	//=========================================================================
	
	
	
	
	///////////////////////
	// Result set related
	///////////////////////
	
	
	
	//=========================================================================
	/**
	 * Return the current row as an object.
	 */
	function fobject() {
		$this->sanity_check();
		if($this->result == NULL || $this->row == -1) {
			$retval = NULL;
		}
		else {
			//NOTE::: this function isn't documented (as of 2008-06-04)... maybe broken.
			$retval = sqlite_fetch_object($this->result);
		}
		
		return($retval);
	}
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Fetch the current row as an array containing fieldnames AND numeric indexes.
	 */
	function farray(){
		if($this->result == NULL || $this->row == -1) {
			$retval = NULL;
		}
		else {
			$retval = sqlite_fetch_array($this->result);
		}
		
		return($retval);
	}//end farray()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Similar to farray(), except all indexes are non-numeric, and the entire 
	 * result set is retrieved: if only one row is available, no numeric index 
	 * is set, unless $numbered is TRUE.
	 * 
	 * TODO: clean this up!
	 */
	function farray_fieldnames($index=NULL, $numbered=NULL, $unsetIndex=NULL) {
		$this->sanity_check();
		$retval = NULL;
		
		//before we get too far, let's make sure there's something there.
		if($this->numRows() <= 0) {
			$retval = 0;
		}
		else {
			$loopThis = $this->fetch_all();
			$retval = array();
			foreach($loopThis as $num=>$record) {
				if(!is_null($index)) {
					if(!isset($record[$index])) {
						throw new exception(__METHOD__ .": index (". $index .") doesn't exist in array::: ". $this->gfObj->debug_print($record,0));
					}
				}
				else {
					$index = $num;
				}
				
				$subRecordArr = array();
				foreach($record as $subIndex=>$subValue) {
					if(!is_numeric($subIndex)) {
						$subRecordArr[$subIndex] = $subValue;
					}
				}
				
				if($unsetIndex) {
					unset($subRecordArr[$index]);
				}
				$retval[$record[$index]] = $subRecordArr;
			}
			
			if(!$numbered && count($retval) == 1) {
				//just give back the single record...
				$keys = array_keys($retval);
				$retval = $retval[$keys[0]];
			}
		}
		return($retval);
	}//end farray_fieldnames()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Retrieve the entire result set, with the final array containing 
	 * name=>value pairs.
	 */
	function farray_nvp($name, $value) {
		if((!$name) OR (!$value)) {
			$retval = 0;
		}
		else {
			$data = $this->fetch_all();
			$retval = array();
			foreach($data as $key=>$data) {
				$retval[$data[$name]] = $data[$value];
			}
		}

		return($retval);
	}//end farray_nvp()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Similar to farray_fieldnames(), but only returns the NUMERIC indexes
	 */
	function farray_numbered($index, $unsetIndex=NULL) {
		$this->sanity_check();
		$retval = NULL;
		
		//before we get too far, let's make sure there's something there.
		if($this->numRows() <= 0) {
			$retval = 0;
		}
		else {
			$loopThis = $this->fetch_all();
			$retval = array();
			foreach($loopThis as $num=>$record) {
				if(!is_null($index)) {
					if(!isset($record[$index])) {
						throw new exception(__METHOD__ .": index (". $index .") doesn't exist in array::: ". $this->gfObj->debug_print($record,0));
					}
					if($unsetIndex) {
						unset($record[$index]);
					}
				}
				else {
					$index = $num;
				}
				
				$subRecordArr = array();
				foreach($record as $subIndex=>$subValue) {
					if(is_numeric($subIndex)) {
						$subRecordArr[$subIndex] = $subValue;
					}
				}
				$retval[$record[$index]] = $subRecordArr;
			}
		}
		
		return($retval);
	}//end farray_numbered()
	//=========================================================================
	
	
	
	//=========================================================================
	function fetch_all($index=NULL, $numbered=NULL,$unsetIndex=1) {
		$this->sanity_check();
		$retval = NULL;
		
		//before we get too far, let's make sure there's something there.
		if($this->numRows() <= 0) {
			$retval = 0;
		}
		else {
			$retval = sqlite_fetch_all($this->result);
		}
		return($retval);
	}//end farray_fieldnames()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Returns the number of tuples affected by an insert/delete/update query.
	 * NOTE: select queries must use numRows()
	 */
	function numAffected() {
		if($this->result == null) {
			$retval = 0;
		} else {
			$this->affectedRows = sqlite_changes($this->connectionID);
			$retval = $this->affectedRows;
		}
		
		return($retval);
	}//end numAffected()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Returns the number of rows in a result (from a SELECT query).
	 */
	function numRows() {
		if ($this->result == null) {
			$retval = 0;
		}
		else {
			$this->numrows = sqlite_num_rows($this->result);
			$retval = $this->numrows;
		}
		
		return($retval);
	}//end numRows()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * wrapper for numAffected()
	 */
	function affectedRows(){
		return($this->numAffected());
	}//end affectedRows()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Get the number of fields in a result.
	 */
	// get the number of fields in a result
	function num_fields() {
		if($this->result == null) {
			$retval = 0;
		}
		else {
			$retval = sqlite_num_fields($this->result);
		}
		return($retval);	
	}//end num_fields()
	//=========================================================================
	
	
	
	//=========================================================================
	function column_count() {
		return($this->numFields());
	}//end column_count()
	//=========================================================================
	
	
	
	//=========================================================================
	/** 
	 * get last OID (object identifier) of last INSERT statement
	 */
	function lastOID($doItForMe=0, $field=NULL) {
		if($this->connectionID == NULL) {
			throw new exception(__METHOD__ .": no connectionID to use (". $this->connectionID .")");
		}
		else {
			$retval = sqlite_last_insert_rowid($this->connectionID);
		}
		return($retval);
	}//end lastOID()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * get result field name of the given field number.
	 */
	// get result field name
	function fieldname($fieldnum) {
		if($this->result == NULL) {
			$retval =NULL;
		}
		else {
			$retval = sqlite_field_name($this->result, $fieldnum);
		}
		
		return($retval);
	}//end fieldname()
	//=========================================================================
	
	
	
	
	////////////////////////
	// Transaction related
	////////////////////////
	
	
	
	
	//=========================================================================
	/**
	 * Start a transaction.
	 */
	function beginTrans() {
		return($this->exec("BEGIN TRANSACTION"));
	}//end beginTrans()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Commit a transaction.
	 */
	function commitTrans() {
		return($this->exec("COMMIT TRANSACTION"));
	}//end commitTrans()
	//=========================================================================
	
	
	
	//=========================================================================
	// returns true/false
	function rollbackTrans() {
		$retval = $this->exec("ROLLBACK TRANSACTION");
		return($retval);
	}//end rollbackTrans()
	//=========================================================================
	
	
	
	////////////////////////
	// SQL String Related
	////////////////////////
	
	
	
	//=========================================================================
	public function is_connected() {
		$retval = FALSE;
		if(is_resource($this->connectionID) && $this->isConnected === TRUE) {
			$retval = TRUE;
		}
		
		return($retval);
	}//end is_connected()
	//=========================================================================
	
	
	
} // end class phpDB

?>
