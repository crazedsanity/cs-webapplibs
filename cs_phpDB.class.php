<?php

/*
 * A class for generic PostgreSQL database access.
 * 
 * SVN INFORMATION:::
 * SVN Signature:::::::: $Id$
 * Last Committted Date: $Date$
 * Last Committed Path:: $HeadURL$
 * 
 */

///////////////////////
// ORIGINATION INFO:
// 		Author: Trevin Chow (with contributions from Lee Pang, wleepang@hotmail.com)
// 		Email: t1@mail.com
// 		Date: February 21, 2000
// 		Last Updated: August 14, 2001
//
// 		Description:
//  		Abstracts both the php function calls and the server information to POSTGRES
//  		databases.  Utilizes class variables to maintain connection information such
//  		as number of rows, result id of last operation, etc.
//
///////////////////////

class cs_phpDB extends cs_webapplibsAbstract {
	
	public $queryList=array();
	private $dbLayerObj;
	private $dbType;
	public $connectParams = array();
	protected $gfObj;
	protected $fsObj;
	protected $logFile;
	protected $writeCommandsToFile;
	
	//=========================================================================
	/**
	 * 
	 * @param string $type
	 * @param bool $writeCommandsToFile		(change this to a string for a filename, 
	 * 											or use boolean true and it write to 
	 * 											a default filename (__CLASS__.log). 
	 * @return unknown_type
	 */
	public function __construct($type='pgsql', $writeCommandsToFile=null) {
		
		if(is_null($type) || !strlen($type) || !is_string($type)) {
			$type = 'pgsql';
		}
		
		require_once(dirname(__FILE__) .'/db_types/'. __CLASS__ .'__'. $type .'.class.php');
		$className = __CLASS__ .'__'. $type;
		$this->dbLayerObj = new $className;
		$this->dbType = $type;
		
		parent::__construct(true);
		
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
	}//end __construct()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Magic method to call methods within the database abstraction layer ($this->dbLayerObj).
	 */
	public function __call($methodName, $args) {
		if(method_exists($this->dbLayerObj, $methodName)) {
			if($methodName == 'connect' && is_array($args[0])) {
				//capture the connection parameters.
				$this->connectParams = $args[0];
			}
			elseif($methodName == 'exec') {
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
			$retval = call_user_func_array(array($this->dbLayerObj, $methodName), $args);
		}
		else {
			throw new exception(__METHOD__ .': uninitialized ('. $this->isInitialized .'), no database layer ('. is_object($this->dbLayerObj) .'), or unsupported method ('. $methodName .') for database of type ('. $this->dbType .')');
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
	/**
	 * Performs queries which require results.  Passing $indexField returns a 
	 * complex array indexed from that field; passing $valueField will change 
	 * it to a name=>value formatted array.
	 * 
	 * NOTE:: when using an index field, be sure it is guaranteed to be unique, 
	 * 	i.e. it is a primary key!  If duplicates are found, the database class 
	 * 	will throw an exception!
	 */
	public function run_query($sql, $indexField=null, $valueField=null) {
		
		$retval = array();
		
		//length must be 15 as that's about the shortest valid SQL:  "select * from x"
		if(strlen($sql) >= 15) {
			$this->exec($sql);
			
			$numRows = $this->numRows();
			$dbError = $this->errorMsg();
			if($numRows > 0 && !strlen($dbError)) {
				if(strlen($indexField) && (is_null($valueField) || !strlen($valueField))) {
					//return a complex array based on a given field.
					$retval = $this->farray_fieldnames($indexField, null, 0);
				}
				elseif(strlen($indexField) && strlen($valueField)) {
					//return an array as name=>value pairs.
					$retval = $this->farray_nvp($indexField, $valueField);
				}
				else {
					$retval = $this->farray_fieldnames();
				}
			}
			elseif($numRows == 0 && !strlen($dbError)) {
				$retval = false;
			}
			else {
				throw new exception(__METHOD__ .": no rows (". $numRows .") or dbError::: ". $dbError ."<BR>\nSQL::: ". $sql);
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid length SQL (". $sql .")");
		}
		
		return($retval);
	}//end run_query()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Handles performing the insert statement & returning the last inserted ID.
	 */
	public function run_insert($sql, $sequence='null') {
		
		$this->exec($sql);
		
		if($this->numAffected() == 1 && !strlen($this->errorMsg())) {
			//retrieve the ID just created.
			$retval = $this->lastID($sequence);
		}
		else {
			//something broke...
			throw new exception(__METHOD__ .": failed to insert, rows=(". $this->numRows() .")... "
				."ERROR::: ". $this->errorMsg() ."\n -- SQL:::: ". $sql);
		}
		
		return($retval);
	}//end run_insert()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Performs the update & returns how many rows were affected.
	 */
	public function run_update($sql, $zeroIsOk=false) {
		$this->exec($sql);
		
		$dberror = $this->errorMsg();
		$numAffected = $this->numAffected();
		
		if(strlen($dberror)) {
			throw new exception(__METHOD__ .": error while running update::: ". $dberror ." -- SQL::: ". $sql);
		}
		elseif($numAffected==0 && $zeroIsOk == false) {
			throw new exception(__METHOD__ .": no rows updated (". $numAffected ."), SQL::: ". $sql);
		}
		
		return($numAffected);
	}//end run_update()
	//=========================================================================
	
	
	
	//=========================================================================
	public function reconnect($forceNewConnection=TRUE) {
		if(is_array($this->connectParams) && count($this->connectParams)) {
			$this->dbLayerObj->connect($this->connectParams, $forceNewConnection);
		}
		else {
			throw new exception(__METHOD__ .": no connection parameters stored");
		}
	}//end reconnect()
	//=========================================================================
	

	//=========================================================================
	/**
	 * Execute the entire contents of the given file (with absolute path) as SQL.
	 */
	public function run_sql_file($filename) {
		$fsObj = new cs_fileSystem(dirname($filename));
		
		$this->lastSQLFile = $filename;
		
		$fileContents = $fsObj->read($filename);
		$this->run_update($fileContents, true);
		$retval = TRUE;
		
		return($retval);
	}//end run_sql_file()
	//=========================================================================
	
	
	
	//=========================================================================
	public function is_connected() {
		return($this->dbLayerObj->is_connected());
	}//end is_connected()
	//=========================================================================
	
	
	
	//=========================================================================
	public function get_last_query() {
		return($this->dbLayerObj->lastQuery);
	}//end get_last_query()
	//=========================================================================
} // end class phpDB

?>
