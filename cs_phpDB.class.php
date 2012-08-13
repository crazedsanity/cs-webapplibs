<?php

/*
 * A class for generic PostgreSQL database access, built on PDO (http://www.php.net/manual/en/book.pdo.php)
 * 
 */

class cs_phpDB extends cs_webapplibsAbstract {
	
	public $queryList=array();
	public $dbh;
	public $sth;
	
	protected $dsn = "";
	protected $connectParams = array();
	protected $username = null;
	protected $password = null;
	protected $dbType = null;
	
	protected $gfObj;
	protected $fsObj;
	protected $logFile;
	protected $writeCommandsToFile;
	protected $numRows = -1;
	
	//=========================================================================
	/**
	 * 
	 * @param string $type
	 * @param bool $writeCommandsToFile		(change this to a string for a filename, 
	 * 											or use boolean true and it write to 
	 * 											a default filename (__CLASS__.log). 
	 * @return unknown_type
	 */
	public function __construct($dsn, $username, $password, array $driverOptions=null, $writeCommandsToFile=null) {
		try {
			parent::__construct();
			$this->gfObj = new cs_globalFunctions();
			$this->dbh = new PDO($dsn, $username, $password, $driverOptions);
			
			// Use *real* prepares (for MySQL)
			$this->dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
			$this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

			$this->dsn = $dsn;
			$this->username = $username;
			$this->password = $password;

			//break the DSN into bits...
			$tmpDsn = preg_replace('/^[aA-zZ]{2,}:/', '', $dsn);
			$tmpDsn = explode(';', $tmpDsn);
			foreach($tmpDsn as $bit) {
				$subDsnBits = explode('=', $bit);
				$this->connectParams[$subDsnBits[0]] = $subDsnBits[1];
			}
			$bits = array();
			if(preg_match('/^([aA-zZ]{2,}):/', $dsn, $bits)) {
				$this->dbType = $bits[1];
			}
			else {
				$this->gfObj->debug_print($bits,1);
				throw new exception(__METHOD__ .": unable to determine dbType");
			}


			$this->isInitialized = TRUE;

			$this->writeCommandsToFile = $writeCommandsToFile;

			if($this->writeCommandsToFile) {
				$this->logFile = __CLASS__ .".log";
				if(is_string($this->writeCommandsToFile)) {
					$this->logFile = $this->writeCommandsToFile;
				}
				$this->fsObj = new cs_fileSystem(constant('RWDIR'));
				$lsData = $this->fsObj->ls();
				if(!isset($lsData[$this->logFile])) {
					$this->fsObj->create_file($this->logFile, true);
				}
				$this->fsObj->openFile($this->logFile, 'a');	
			}
		}
		catch(PDOException $e) {
			throw new exception(__METHOD__ .": failed to connect to database: ".
					$e->getMessage());
		}
	}//end __construct()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Magic method to call methods within the database abstraction layer ($this->dbLayerObj).
	 */
	public function __call($methodName, $args) {
		if(method_exists($this->dbh, $methodName)) {
			if($methodName == 'exec') {
				//update lastQuery list... should hold the last few SQL commands.
				if(count($this->queryList) > 20) {
					array_pop($this->queryList);
				}
				array_unshift($this->queryList, $args[0]);
				
				//log it to a file.
				if($this->writeCommandsToFile) {
					$this->fsObj->append_to_file(date('D, d M Y H:i:s') . ' ('. microtime(true) . ')::: '. $args[0]);
				}
			}
			try {
				
				$retval = call_user_func_array(array($this->dbh, $methodName), $args);
			}
			catch (Exception $e) {
				#cs_debug_backtrace(1);
				throw $e;
			}
		}
		else {
			cs_debug_backtrace();
			throw new exception(__METHOD__ .': FATAL: unsupported method ('. $methodName .') for database of type ('. $this->dbType .')');
		}
		return($retval);
	}//end __call()	
	//=========================================================================
	
	
	
	//=========================================================================
	public function get_dbtype() {
		return($this->dbType);
	}//end get_dbtype()
	//=========================================================================
	
	
	
	//=========================================================================
	public function get_hostname() {
		$retval = null;
		if(isset($this->connectParams['host'])) {
			$retval = $this->connectParams['host'];
		}
		else {
			throw new exception(__METHOD__ .": HOST parameter missing");
		}
		return($this->connectParams['host']);
	}//end get_hostname()
	//=========================================================================
	
	
	
	//=========================================================================
	public function get_dsn() {
		return($this->dsn);
	}//end get_dsn()
	//=========================================================================
	
	
	
	//=========================================================================
	public function get_username() {
		return($this->username);
	}//end get_username()
	//=========================================================================
	
	
	
	//=========================================================================
	public function get_password() {
		return($this->password);
	}//end get_password()
	//=========================================================================
	
	
	
	//=========================================================================
	public function errorMsg() {
		$tRetval = $this->dbh->errorInfo();
		return($tRetval[2]);
	}//end errorMsg()
	//=========================================================================
	
	
	
	//=========================================================================
	public function numRows() {
		return ($this->numRows);
	}//end numRows()
	//=========================================================================
	
	
	
	//=========================================================================
	public function numAffected() {
		return ($this->numRows);
	}//end numAffected()
	//=========================================================================
	
	
	
	//=========================================================================
	public function get_lastQuery() {
		$retval = null;
		if(is_object($this->sth)) {
			$retval = $this->sth->queryString;
		}
		else {
			throw new exception(__METHOD__ .': statement handle not set');
		}
		return($retval);
	}
	//=========================================================================
	
	
	
	//=========================================================================
	public function run_query($sql, array $params=null, array $driverOptions=array()) {
		try {
			$this->sth = $this->dbh->prepare($sql, $driverOptions);
			// TODO: throw an exception on error (and possibly if there were no rows returned)
			$this->numRows = $this->sth->execute($params); 
		}
		catch(PDOException $px) {
cs_debug_backtrace(1);
#$this->gfObj->debug_print($this,1);
			throw new exception(__METHOD__ .": ". $px->getMessage());
		}
		return($this->numRows);
	}//end run_query()
	//=========================================================================
	
	
	
	//=========================================================================
	public function run_insert($sql, array $params=null, $seqName, array $driverOptions=array()) {
		$numRows = $this->run_query($sql, $params, $driverOptions);
		$retval = null;
		if($numRows > 0) {
			// TODO: throw exception on error
			$retval = $this->dbh->lastInsertId($seqName);
		}
		else {
			throw new exception(__METHOD__ .': no rows created');
		}
		return($retval);
	}//end run_insert()
	//=========================================================================
	
	
	
	//=========================================================================
	public function run_update($sql, array $params=null, array $driverOptions=array()) {
		return($this->run_query($sql, $params, $driverOptions));
	}//end run_update()
	//=========================================================================
	
	
	
	//=========================================================================
	/***
	 * 
	 * "numbered" is special: when there's only one result and numbered=false, 
	 *		the returned array will just be all the fields for that first record.
	 * "unsetIndex" means that the index field will be removed from the record's 
	 *		array.
	 */
	public function farray_fieldnames($index=null) {
		$retval = null;
		if(is_object($this->sth)) {
			$oData = $this->sth->fetchAll(PDO::FETCH_ASSOC);
			$retval = array();
			
			if($this->numRows > 0) {
				$retval = $oData;
				foreach($oData as $rowData) {
					if(!is_null($index)) {
						if(isset($rowData[$index])) {
							if(!isset($retval[$index])) {
								$retval[$rowData[$index]] = $rowData;
							}
							else {
								throw new exception(__METHOD__ .': duplicate records exist for index=('. $index .'), first duplicate was ('. $rowData[$index] .')');
							}
						}
						else {
							throw new exception(__METHOD__ .': record does not contain column "'. $index .'"');
						}
					}
					else {
						$retval = $oData;
					}
				}
			}
			else {
				// no rows!
				$retval = array();
			}
		}
		else {
			throw new exception(__METHOD__ .': statement handle was not created');
		}
		return($retval);
	}//end farray_fieldnames()
	//=========================================================================
	
	
	
	//=========================================================================
	public function farray() {
		
	}
	//=========================================================================
	
	
	
	//=========================================================================
	public function get_single_record() {
		$retval = array();
		if(is_object($this->sth)) {
			$retval = $this->sth->fetchAll(PDO::FETCH_ASSOC);
			if(is_array($retval) && count($retval)) {
				$retval = $retval[0];
			}
		}
		else {
			throw new exception(__METHOD__ .': statement handle was not created');
		}
		
		return($retval);
	}//end get_single_record()
	//=========================================================================
	
	
	
	//=========================================================================
	public function farray_nvp($name, $value) {
		$myData = $this->farray_fieldnames();
		$retval = array();
		foreach($myData as $i=>$rowData) {
			if(isset($rowData[$name]) && isset($rowData[$value])) {
				if(!isset($retval[$name])) {
					$tKey = $rowData[$name];
					$tVal = $rowData[value];
					$retval[$tKey] = $tVal;
				}
				else {
					throw new exception(__METHOD__ .': duplicate values for column ('. $name .') found for record #'. $i);
				}
			}
			else {
				throw new exception(__METHOD__ .': missing name ('. $name .') or value ('. $value .') from dataset');
			}
		}
		return($retval);
	}//end farray_nvp()
	//=========================================================================
	
	
	
	//=========================================================================
	public function run_sql_file($sqlFile) {
		$retval = $this->dbh->exec(file_get_contents($sqlFile));
		
		return($retval);
	}
	//=========================================================================
	
	// wrapper methods (for backwards-compatibility)
	public function beginTrans() {return($this->dbh->beginTransaction());}
	public function commitTrans() {return($this->dbh->commit());}
	public function rollbackTrans() {return($this->dbh->rollback());}
	
	/***
	 * Test
	 */
	public function get_transaction_status() {return($this->dbh->inTransaction());}
	
	
	
	



} // end class phpDB

?>