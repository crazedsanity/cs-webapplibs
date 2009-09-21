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
	
	private $dbLayerObj;
	private $dbType;
	public $connectParams = array();
	protected $gfObj;
	
	//=========================================================================
	public function __construct($type='pgsql') {
		if(is_null($type) || !strlen($type)) {
			$type = 'pgsql';
		}
		
		require_once(dirname(__FILE__) .'/db_types/'. __CLASS__ .'__'. $type .'.class.php');
		$className = __CLASS__ .'__'. $type;
		$this->dbLayerObj = new $className;
		$this->dbType = $type;
		
		parent::__construct(true);
		
		$this->isInitialized = TRUE;
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
			$retval = call_user_func_array(array($this->dbLayerObj, $methodName), $args);
		}
		else {
			throw new exception(__METHOD__ .': unsupported method ('. $methodName .') for database of type ('. $this->dbType .')');
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
		
		//length must be 19 as that's about the shortest valid SQL:  "select * from table"
		if(strlen($sql) >= 19) {
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
			throw new exception(__METHOD__ .": failed to insert, rows=(". $this->numRows .")... "
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
	
} // end class phpDB

?>
