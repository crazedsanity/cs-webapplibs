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

abstract class cs_phpDBAbstract {

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
	
	
	
	//Define some abstract methods so they MUST be provided in order for things to work.
	abstract public function set_db_info(array $params);
	abstract public function close();
	abstract public function connect(array $dbParams=NULL, $forceNewConnection=FALSE);
	abstract public function exec($query);
	abstract public function errorMsg($setMessage=null, $logError=null);
	abstract public function fobject();
	abstract public function farray();
	abstract public function farray_fieldnames($index=null, $numbered=null,$unsetIndex=1);
	abstract public function farray_nvp($name, $value);
	abstract public function farray_numbered();
	abstract public function numAffected();
	abstract public function numRows();
	abstract public function is_connected();
	
	
	//=========================================================================
    public function __construct() {
    	$this->gfObj = new cs_globalFunctions;
    	$this->isInitialized = true;
    }//end __construct()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Make sure the object is sane.
	 */
	final protected function sanity_check() {
		if($this->isInitialized !== TRUE) {
			throw new exception(__METHOD__ .": not properly initialized");
		}
	}//end sanity_check()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Disconnect from the database (calls internal "close()" method).
	 */
	public function disconnect() {
		return($this->close());
	}//end disconnect()
	//=========================================================================
	
	
	
	//=========================================================================
	public function affectedRows() {
		return($this->numAffected());
	}//end affectedRows()
	//=========================================================================
	
	
	
	//=========================================================================
	public function currRow() {
		return($this->row);
	}//end currRow()
	//=========================================================================
	
	
	
	//=========================================================================
	public function querySafe($string) {
		return($this->gfObj->cleanString($string,"query"));
	}//end querySafe()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Make it SQL safe.
	 */
	public function sqlSafe($string) {
		return($this->gfObj->cleanString($string,"sql"));
	}//end sqlSafe()
	//=========================================================================
	
	
	
}
?>