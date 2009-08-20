<?php

/*
 * A class for generic MySQL database access.
 * 
 * SVN INFORMATION:::
 * SVN Signature:::::::: $Id$
 * Last Committted Date: $Date$
 * Last Committed Path:: $HeadURL$
 * 
 */


class cs_phpDB__mysql extends cs_phpDBAbstract {

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
	
	/** Hostname or IP to connect to */
	protected $host;
	
	/** Port to connect to (default for Postgres is 5432) */
	protected $port;
	
	/** Name of the database */
	protected $dbname;
	
	/** Username to connect to the database */
	protected $user;
	
	/** password to connect to the database */
	protected $password;
	
	/** Row counter for looping through records */
	protected $row = -1;
	
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
		$required = array('host', 'dbname', 'user', 'password');
		
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
			$retval = mysql_close($this->connectionID);
			$this->transStatus = null;
			$this->inTrans=null;
			$this->transactionTree=null;
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
		if(is_array($dbParams)) {
			$this->set_db_info($dbParams);
		}
		
		if($this->paramsAreSet === TRUE) {
			
			//start output buffer for displaying error.
			ob_start();
			$connID = mysql_connect($this->host, $this->user, $this->password, $forceNewConnection);
			if(!$connID) {
				$connectError = mysql_error();
			}
			else {
				mysql_select_db($this->dbname);
				$connectError = ob_get_contents();
			}
			ob_end_clean();
			
			if(is_resource($connID)) {
				$this->errorCode=0;
				$this->connectionID = $connID;
				$this->isConnected = TRUE;
				$retval = $this->connectionID;
			}
			else {
				if(is_bool($connID) && !strlen($connectError)) {
					$connectError = "generic connection failure";
				}
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
	function get_hostname() {
		$this->sanity_check();
		return($this->host);
	}//end get_hostname()
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
		
		$this->result = mysql_query($query, $this->connectionID);
		
		if($this->result !== false) {
			if (eregi("^[[:space:]]*select", $query)) {
				//If we didn't have an error and we are a select statement, move the pointer to first result
				$numRows = $this->numRows();
				if($numRows > 0) {
					$this->move_first();
				}
				$returnVal = $numRows;
				
			}
			else {
				//We got something other than an update. Use numAffected
				$returnVal = $this->numAffected();
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
		if ($this->connectionID < 0) {
			//TODO: implement MySQL version (error codes may vary)...
			switch ($this->errorCode) {
				//###############################################
				case -1:
				$retVal = "FATAL ERROR - CONNECTION ERROR: RESOURCE NOT FOUND";
				break;
				//###############################################
	
				//###############################################
				case -2:
				$retVal = "FATAL ERROR - CLASS ERROR: FUNCTION CALLED WITHOUT PARAMETERS";
				break;
				//###############################################
				
				//###############################################
				case -3:
				$retVal = "Query exceeded maximum timeout (". $this->timeoutSeconds .")";
				break;
				//###############################################
	
				//###############################################
				default:
				$retVal = null;
				//###############################################
			}
		} else {
			//TODO: implement MySQL version..
			$retVal = mysql_error($this->connectionID);
		}

		return($retVal);
	}//end errorMsg()
	//=========================================================================
	
	
	
	//=========================================================================
	public function ping() {
		return(mysql_ping($this->connectionID));
	}//end ping()
	//=========================================================================
	
	
	
	
	////////////////////
	// Cursor movement
	////////////////////
	
	
	
	
	//=========================================================================
	/**
	 * move pointer to first row of result set
	 */
	function move_first() {
		$this->sanity_check();
		if($this->result == NULL) {
			$retval = FALSE;
		}
		else {
			$this->set_row(0);
			$retval = TRUE;
		}
		
		return($retval);
	}//end move_first()
	//=========================================================================
	
	
	
	//=========================================================================
	/** 
	 * move pointer to last row of result set
	 */
	function move_last() {
		$this->sanity_check();
		if($this->result == NULL) {
			$retval = FALSE;
		}
		else {
			$this->set_row($this->numRows()-1);
			$retval = TRUE;
		}
		
		return($retval);
	}//end move_list()
	//=========================================================================
	
	
	
	//=========================================================================
	/** 
	 * point to the next row, return false if no next row
	 */
	function move_next() {
		$this->sanity_check();
		// If more rows, then advance row pointer
		if($this->row < $this->numRows()-1) {
			$this->set_row($this->row +1);
			$retval = TRUE;
		}
		else {
			$retval = FALSE;
		}
		
		return($retval);
	}//end move_next()
	//=========================================================================
	
	
	
	//=========================================================================
	/** 
	 * point to the previous row, return false if no previous row
	 */
	function move_previous() {
		// If not first row, then advance row pointer
		if ($this->row > 0) {
			$this->set_row($this->row -1);
			return true;
		}
		else return false;
	}//end move_previous()
	//=========================================================================
	
	
	
	//=========================================================================
	// point to the next row, return false if no next row
	function next_row() {
		// If more rows, then advance row pointer
		if ($this->row < $this->numRows()-1) {
				$this->set_row($this->row +1);
				return true;
		}
		else return false;
	}//end next_row()
	//=========================================================================
	
	
	
	//=========================================================================
	// can be used to set a pointer to a perticular row
	function set_row($row){
		if(is_numeric($row)) {
			$this->row = $row;
			if(!mysql_data_seek($this->result, $this->row)) {
				throw new exception(__METHOD__ .": failed to seek row (". $this->row .")");
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid data for row (". $row .")");
		}
		return($this->row);
	}//end set_row();
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
			//TODO: implement MySQL version..
			$retval = mysql_fetch_object($this->result, $this->row);
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
			$retval = mysql_fetch_array($this->result);
		}
		
		return($retval);
	}//end farray()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Another way to retrieve a single row (useful for loops).
	 */
	function frow(){
		$this->sanity_check();
		if($this->numRows() <= 0) {
			$retval = NULL;
		}
		else {
			if($this->result == null || $this->row == -1) {
				$retval = NULL;
			}
			else {
			//TODO: implement MySQL version..
				$retval = mysql_fetch_row($this->result, $this->row);
			}
		}
		
		return($retval);
	}//end frow()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Similar to farray(), except all indexes are non-numeric, and the entire 
	 * result set is retrieved: if only one row is available, no numeric index 
	 * is set, unless $numbered is TRUE.
	 * 
	 * TODO: clean this up!
	 */
	function farray_fieldnames($index=NULL, $numbered=NULL,$unsetIndex=1) {
		$this->sanity_check();
		$retval = NULL;
		
		//before we get too far, let's make sure there's something there.
		if($this->numRows() <= 0) {
			$retval = 0;
		}
		else {		
			//keep any errors/warnings from printing to the screen by using OUTPUT BUFFERS.
			ob_start();
			
			$x = 0;
			$newArr = array();
			$tArr = array();
			do {
				$temp = $this->farray();
				if(is_array($temp) && count($temp)) {
					foreach($temp as $key=>$value) {
						//remove the numbered indexes.
						if(is_string($key)) {
							$tArr[$key] = $value;
						}
					}
					$newArr[$x] = $tArr;
					$x++;
				}
				else {
					throw new exception(__METHOD__ .": no data retrieved from farray()...");
				}
			}
			while($this->next_row());
			
			if($index) {
				foreach($newArr as $row=>$contents) { //For each of the returned sets of information
					foreach($contents as $fieldname=>$value) { //And now for each of the items in that set
						if($fieldname == $index) {
							//The index for the new array will be this fieldname's value
							$arrayKey = $value;
						}
						
						$tempContent[$fieldname] = $value;
						//don't include the "index" field in the subarray; that always seems to end badly.
						if ($unsetIndex) {
							unset($tempContent[$index]);
						}
					}
					
					if (!isset($tempArr[$arrayKey])) {
						//Make sure we didn't already set this in the array. If so, then we don't have a unique variable to use for the array index. 
						$tempArr[$arrayKey] = $tempContent;
					}
					else {
						//TODO: bigtime cleaning... should only return at the bottom of the method.
						$retval = 0;
						break;
					}
					$arrayKey = NULL; //Blank this out after using it, just in case we don't find one in the next iteration
				}
	
				if (count($tempArr) != count($newArr)) {
					$details = "farray_fieldnames(): Array counts don't match.<BR>\n"
						."FUNCTION ARGUMENTS: index=[$index], numbered=[$numbered], unsetIndex=[$unsetIndex]<BR>\n"
						."LAST QUERY: ". $this->lastQuery;
					throw new exception(__METHOD__ .": $details");
				}
				$newArr = $tempArr;
			}
			//this is where, if there's only one row (and the planets align just the way 
			//	I like them to), there's no row w/ a sub-array...  This is only done 
			//	if $index is NOT set...
			if(($this->numRows() == 1) AND (!$index) AND (!$numbered)) {
				$newArr = $newArr[0];
			}
			$retval = $newArr;
			ob_end_clean();
		}
		return($retval);
	}//end farray_fieldnames()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Uses farray_fieldnames() to retrieve the entire result set, but the final 
	 * array is contains name=>value pairs.
	 */
	function farray_nvp($name, $value) {
		if((!$name) OR (!$value)) {
			$retval = 0;
		}
		else {	
			$tArr = $this->farray_fieldnames(NULL,1);
			if(!is_array($tArr)) {
				$retval = 0;
			}
			else {
				//loop through it & grab the proper info.
				$retval = array();
				foreach($tArr as $row=>$array) {
					$tKey = $array[$name];
					$tVal = $array[$value];
					$retval[$tKey] = $tVal;
				}
			}
		}

		//return the new array.
		return($retval);
	}//end farray_nvp()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Similar to farray_fieldnames(), but only returns the NUMERIC indexes
	 */
	function farray_numbered() {
		do {
			$temp = $this->frow();
			$retArr[] = $temp[0];
		}
		while($this->next_row());
		
		return($retArr);
	}//end farray_numbered()
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
			//TODO: implement MySQL version..
			$this->affectedRows = mysql_affected_rows($this->connectionID);
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
		if ($this->result == null || !is_resource($this->result)) {
			$retval = 0;
		}
		else {
			$this->numrows = mysql_num_rows($this->result);
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
	/**
	 * Get the number of fields in a result.
	 */
	// get the number of fields in a result
	function num_fields() {
		if($this->result == null) {
			$retval = 0;
		}
		else {
			$retval = mysql_num_fields($this->result);
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
	 * get last ID of last INSERT statement
	 */
	function lastID() {
		$retval = mysql_insert_id($this->connectionID);
		return($retval);
	}//end lastID()
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
			//TODO: implement MySQL version..
			$retval = mysql_field_name($this->result, $fieldnum);
		}
		
		return($retval);
	}//end fieldname()
	//=========================================================================
	
	
	////////////////////////
	// SQL String Related
	////////////////////////
	
	
	
	//=========================================================================
	/**
	 * Gives textual explanation of the current status of our database 
	 * connection.
	 * 
	 * @param $goodOrBad		(bool,optional) return good/bad status.
	 * 
	 * @return (-1)				(FAIL) connection is broken
	 * @return (0)				(FAIL) error was encountered (transient error)
	 * @return (1)				(PASS) useable
	 * @return (2)				(PASS) useable, but not just yet (working 
	 * 								on something)
	 */
	function get_transaction_status($goodOrBad=TRUE) {
		//TODO: implement MySQL version..
		$retval = false;
		return($retval);
	}//end get_transaction_status()
	//=========================================================================
	
	
	
	//=========================================================================
	public function is_connected() {
		$retval = FALSE;
		if(is_resource($this->connectionID) && $this->isConnected === TRUE) {
			$retval = TRUE;
		}
		
		return($retval);
	}//end is_connected()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Simple way to determine if the current connection is inside a 
	 * transaction or not.
	 */
	public function is_in_transaction() {
		$retval=0;
		return($retval);
	}//end is_in_transaction()
	//=========================================================================
	
	
	
	//=========================================================================
	public function select_db($dbName) {
		if(mysql_select_db($dbName, $this->connectionID)) { 
			$this->dbname = $dbName;
		}
		else {
			throw new exception(__METHOD__ .": failed to select db (". $dbName .")");
		}
	}//end select_db()
	//=========================================================================
	
	
	
	//=========================================================================
	public function beginTrans() {
		$this->exec('BEGIN;SET autocommit=0;');
		return(true);
	}//end beginTrans()
	//=========================================================================
	
	
	
	//=========================================================================
	public function commitTrans() {
		$this->exec('COMMIT');
		return(true);
	}//end commitTrans()
	//=========================================================================
	
	
	
	//=========================================================================
	public function rollbackTrans() {
		$this->exec('ROLLBACK');
		return(true);
	}//end rollbackTrans()
	//=========================================================================
	
} // end class phpDB

?>
