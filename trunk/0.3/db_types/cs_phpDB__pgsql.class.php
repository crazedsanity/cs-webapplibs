<?php

/*
 * A class for generic PostgreSQL database access.
 * 
 * SVN INFORMATION:::
 * SVN Signature:::::::: $Id$
 * Last Committted Date: $Date$
 * Last Committed Path:: $HeadURL$
 * 
 * //////////////////////
 * ORIGINATION INFO:
 * 		Author: Trevin Chow (with contributions from Lee Pang, wleepang@hotmail.com)
 * 		Email: t1@mail.com
 * 		Date: February 21, 2000
 * 		Last Updated: August 14, 2001
 * 
 * 		Description:
 *  		Abstracts both the php function calls and the server information to POSTGRES
 *  		databases.  Utilizes class variables to maintain connection information such
 *  		as number of rows, result id of last operation, etc.
 * 
 * /////////////////////
 * 
 * TODO: option to not use layered transactions
 * TODO: rollbackTrans() in layered transaction causes abort when final layer is committed/aborted
 * TODO: stop sending queries to backend when transction is bad/aborted.
 * TODO: commit/abort specific layer requests (i.e. if there's 8 layers & the first is named "x", calling commitTrans("x") will cause the whole transaction to commit & all layers to be destroyed.
 */


class cs_phpDB__pgsql extends cs_phpDBAbstract {

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
		$required = array('host', 'port', 'dbname', 'user', 'password');
		
		$requiredCount = 0;
		foreach($required as $index) {
			if(isset($params[$index]) || $index == 'password') {
				$this->$index = $params[$index];
				$requiredCount++;
			}
			else {
				$this->gfObj->debug_print($params,1);
				throw new exception(__METHOD__. ": property (". $index .") missing");
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
			$retval = pg_close($this->connectionID);
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
		
		if($this->paramsAreSet === TRUE && $this->isConnected === FALSE) {
			
			$myConnArr = array(
				'host'		=> $this->host,
				'port'		=> $this->port,
				'dbname'	=> $this->dbname,
				'user'		=> $this->user,
				'password'	=> $this->password
			);
			
			//make it into a string separated by spaces, don't clean anything, remove null elements
			$connStr = $this->gfObj->string_from_array($myConnArr, 'url', " ");
			
			//start output buffer for displaying error.
			ob_start();
			if($forceNewConnection) {
				$connID = pg_connect($connStr, PGSQL_CONNECT_FORCE_NEW);
			}
			else {
				$connID =pg_connect($connStr);
			}
			$connectError = ob_get_contents();
			ob_end_clean();
			
			if(is_resource($connID)) {
				$this->errorCode=0;
				$this->connectionID = $connID;
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
		
		if(($this->get_transaction_status() != -1) && ($this->connectionID != -1)) {
			$this->result = @pg_query($this->connectionID, $query);

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
			$retVal = pg_last_error($this->connectionID);
		}

		return($retVal);
	}//end errorMsg()
	//=========================================================================
	
	
	
	//=========================================================================
	public function ping() {
		return(pg_ping($this->connectionID));
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
			$retval = pg_fetch_object($this->result, $this->row);
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
			$retval = pg_fetch_array($this->result,$this->row);
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
				$retval = pg_fetch_row($this->result, $this->row);
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
			do {
				$temp = $this->farray();
				foreach($temp as $key=>$value) {
					//remove the numbered indexes.
					if(is_string($key)) {
						$tArr[$key] = $value;
					}
				}
				$newArr[$x] = $tArr;
				$x++;
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
			$this->affectedRows = pg_affected_rows($this->result);
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
			$this->numrows = pg_num_rows($this->result);
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
			$retval = pg_num_fields($this->result);
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
		if($this->result == NULL) {
			$retval = NULL;
		}
		else {
			$tOid = pg_last_oid($this->result);
			$retval = $tOid;
			
			if(($doItForMe) AND (eregi("^insert", $this->last_query))) {
				//attempt to parse the insert statement, then select 
				// all fields (unless $field is set) from it.
				$t = split(" into ", strtolower($this->last_query));
				$t = split(" ", $t[1]);
				$t = split("\(", $t[0]);
				$table = $t[0];
				
				//now we have the table. 
				if(!$field) {
					$field = "*";
				}
				$query = "SELECT $field FROM $table WHERE OID=$tOid";
				$this->exec($query);
				$dberror = $this->errorMsg(1,1,1,"lastOID(): ");
				
				if(!$dberror) {
					$res = $this->farray();
					if(is_string($field)) {
						$retval = $res[0];
					}
				}
			}
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
			$retval = pg_field_name($this->result, $fieldnum);
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
	function beginTrans($transName=NULL) {
		$transStatus = $this->get_transaction_status(TRUE);
		if(!$this->inTrans) {
			//not in a transaction.  Set up the transaction tree properly.
			$this->transactionTree = array();
		}
		else {
			if($this->inTrans && is_null($this->transactionTree)) {
				$transLevel = $this->get_transaction_level();
				//transaction started without using beginTrans()...
				$this->transactionTree = array();
				$this->gfObj->debug_print(__METHOD__ .": transaction already started, transStatus=(". $transStatus ."), transLevel=(". $transLevel .")");
				$this->transactionTree[] = "Already started...";
			}
		}
		
		if(is_null($transName)) {
			$transName = time(TRUE);
		}
		$this->transactionTree[] = $transName;
		$transLevel = $this->get_transaction_level();
		return($this->exec("BEGIN"));
	}//end beginTrans()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Commit a transaction.
	 */
	function commitTrans() {
		$retval = $this->get_transaction_status();
		$lastTransLayer = array_pop($this->transactionTree);
		$transLevel = $this->get_transaction_level();
		if($transLevel == 0) {
			if($retval > 1) {
				$retval = 1;
			}
			$this->exec("COMMIT");
			
			//check to see if there was an error (deferred constraints are checked at commit time)
			if(strlen($this->errorMsg())) {
				$retval = 0;
			}
		}
		$this->get_transaction_status();
		return($retval);
	}//end commitTrans()
	//=========================================================================
	
	
	
	//=========================================================================
	// returns true/false
	function rollbackTrans() {
		$retval = $this->exec("ABORT");
		$this->get_transaction_status();
		return($retval);
	}//end rollbackTrans()
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
		$myStatus = pg_transaction_status($this->connectionID);
		$text = 'unknown';
		switch($myStatus) {
			case PGSQL_TRANSACTION_IDLE: {
				//No query in progress: it's idle.
				$goodOrBadValue = 1;
				$text = 'idle';
				$this->inTrans = FALSE;
			}
			break;
			
			
			case PGSQL_TRANSACTION_ACTIVE: {
				//there's a command in progress.
				$goodOrBadValue = 2;
				$text = 'processing';
			}
			break;
			
			
			case PGSQL_TRANSACTION_INTRANS: {
				//connection idle within a valid transaction block.
				$goodOrBadValue = 1;
				$text = 'valid transaction';
				$this->inTrans = TRUE;
			}
			break;
			
			
			case PGSQL_TRANSACTION_INERROR: {
				//connection idle within a broken transaction.
				$goodOrBadValue = 0;
				$text = 'failed transaction';
				$this->inTrans = TRUE;
			}
			break;
			
			
			case PGSQL_TRANSACTION_UNKNOWN:
			default: {
				//the connection is bad.
				$goodOrBadValue = -1;
				$text = 'bad connection';
			}
			break;
		}
		
		//do they want text or the good/bad number?
		$retval = $text;
		$this->transactionStatus = $goodOrBadValue;
		if($goodOrBad) {
			//they want the number.
			$retval = $goodOrBadValue;
		}
		
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
	 * Create a prepared statement.
	 */
	public function create_prepared_statement($planName,array $fieldToType, $statement) {
		$retval = FALSE;
		//store the mappings.
		$this->preparedStatements[$planName] = $fieldToType;
		
		//TODO: check that the string in "$statement" has the same number of "${n}" as are in "$fieldToType".
		
		$dataTypeString = "";
		foreach($fieldToType as $field => $type) {
			$dataTypeString = $this->gfObj->create_list($dataTypeString, $type, ", ");
		}
		
		$sql = "PREPARE ". $planName ."(". $dataTypeString .") AS ". $statement;
		
		$myNumrows = $this->exec($sql);
		$myDberror = $this->errorMsg();
		
		if(!strlen($myDberror)) {
			$retval = TRUE;
		}
		else {
			throw new exception(__METHOD__ .": failed to create prepared statement '". $planName ."'... dberror::: ". $myDberror ."\n\nSQL::: ". $sql);
		}
		
		return($retval);
	}//end create_prepared_statement()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Run a statement prepared by this object.
	 * 
	 * NOTE: determination of rows affected (numAffected) vs. rows returned (numRows) 
	 * must be done by the user via the referenced methods.
	 */
	public function run_prepared_statement($name, array $data) {
		$retval = FALSE;
		if(is_array($this->preparedStatements[$name]) && count($data) == count($this->preparedStatements[$name])) {
			$this->result = pg_execute($this->connectionID, $name, $data);
			$dberror = $this->errorMsg();
			
			if(!strlen($dberror)) {
				$retval = TRUE;
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid statement name (". $name ."), or incorrect number of elements");
		}
		
		return($retval);
	}//end run_prepared_statement()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Starts a copy command.
	 * 
	 * TODO: implement safeguards so they can only put a line until the copy is ended.
	 */
	public function start_copy($tableName, array $fields) {
		$retval = FALSE;
		$copyStmt = "COPY ". $tableName ." (". $this->gfObj->string_from_array($fields, NULL, ", ") . ") FROM stdin;";
		$this->exec($copyStmt);
		if(!strlen($this->errorMsg())) {
			//TODO: set something here so that NOTHING ELSE can be done except put_line() and end_copy().
			$this->copyInProgress = TRUE;
			$retval = TRUE;
		}
		else {
			$this->end_copy();
			$retval = FALSE;
		}
		
		return($retval);
	}//end start_copy()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Used to send a line to the COPY in progress (only if it was initiated by 
	 * the internal start_copy() method).
	 * 
	 * NOTE: the "end-of-copy" line, '\.', should NEVER be sent here.
	 */
	public function put_line($line) {
		$retval = FALSE;
		if($this->copyInProgress === TRUE) {
			$myLine = trim($line);
			$myLine .= "\n";
			
			$retval = pg_put_line($this->connectionID, $myLine);
		}
		else {
			throw new exception(__METHOD__ .": cannot send line if no copy is in progress");
		}
		
		return($retval);
	}//end put_line()
	//=========================================================================
	
	
	
	//=========================================================================
	public function end_copy() {
		if($this->copyInProgress === TRUE) {
			//send the end-of-copy line...
			$this->put_line("\\.\n");
		}
		
		$retval = pg_end_copy($this->connectionID);
		
		return($retval);
	}//end end_copy()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Determines how many times a transaction has been started.  Starting 
	 * multiple transactions does NOT protect the outer transaction from 
	 * problems that occur in the inner transaction.  In fact, it does the 
	 * opposite: it protects the code from committing too early (which might 
	 * destroy something that depending on the transaction).
	 */
	public function get_transaction_level() {
		if(is_array($this->transactionTree)) {
			$retval = count($this->transactionTree);
		}
		else {
			$retval = 0;
		}
		
		return($retval);
	}//end get_transaction_level()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Simple way to determine if the current connection is inside a 
	 * transaction or not.
	 */
	public function is_in_transaction() {
		$retval = 0;
		if($this->inTrans || $this->get_transaction_level() != 0) {
			$retval = TRUE;
		}
		return($retval);
	}//end is_in_transaction()
	//=========================================================================
	
	
	
	//=========================================================================
	public function get_currval($sequence) {
		if(is_string($sequence) && strlen($sequence) >= 5) {
			$numrows = $this->exec("SELECT currval('". $sequence ."')");
			$dberror = $this->errorMsg();
			
			if($numrows == 1 && !strlen($dberror)) {
				$data = $this->farray();
				$retval = $data[0];
			}
			else {
				throw new exception(__METHOD__ .": invalid rows (". $numrows .") or database error (". $dberror .")");
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid sequence name (". $sequence .")");
		}
		
		return($retval);
	}//end get_currval()
	//=========================================================================
	
	
	
	//=========================================================================
	public function lastID($sequence) {
		
		if(strlen($sequence)) {
			$retval = $this->get_currval($sequence);
		}
		else {
			throw new exception(__METHOD__ .": no sequence specified (required by ". $this->dbType .")");
		}
		
		return($retval);
	}//end lastID()
	//=========================================================================
	
	
	
} // end class phpDB

?>
