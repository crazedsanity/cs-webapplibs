<?php
/*
 * Created on Mar 8, 2007
 * 
 * NOTICE::: this class was derived from the logsClass.php found in cs-projet v1.2, found
 * at URL: https://cs-project.svn.sourceforge.net/svnroot/cs-project/trunk/1.2/lib/logsClass.php
 * Last SVN Signature (from cs-project v1.2): "logsClass.php 819 2008-02-09 10:01:10Z crazedsanity"
 * 
 * SVN INFORMATION:::
 * ------------------
 * SVN Signature::::::: $Id$
 * Last Author::::::::: $Author$ 
 * Current Revision:::: $Revision$ 
 * Repository Location: $HeadURL$ 
 * Last Updated:::::::: $Date$
 * 
 * 
 * Each class that's trying to log should have an internal var statically set to indicates what category 
 * it is: this allows them to call a method within this and tell it ONLY what "class" the log should be
 * under, so this class can determine the appropriate log_event_id.  This avoids having to hard-code 
 * too many id's that might need to be changed later.  Yay, dynamic code!
 * 
 * QUERY TO GET LAST COUPLE OF LOGS::::
 * select l.log_id, l.creation::date, lclas.name as class_name, lcat.name as category_name, e.description, 
 * u.username as user, u2.username as affected_user, l.details FROM log_table AS l INNER JOIN log_event_table 
 * AS e ON (l.log_event_id=e.log_event_id) INNER JOIN user_table AS u ON (l.uid=u.uid) INNER JOIN user_table 
 * AS u2 ON (l.affected_uid=u2.uid) INNER JOIN log_category_table AS lcat ON (e.log_category_id=lcat.log_category_id) 
 * INNER JOIN log_class_table AS lclas ON (e.log_class_id=lclas.log_class_id) WHERE e.log_category_id <> 10 
 * ORDER BY log_id DESC limit 5;
 * 
 */

//NOTE::: this class **REQUIRES** cs-content for its "cs_phpDB" class.

class cs_webdblogger {
	/** Database handle */
	public $db;
	
	/** Cache of all records in the log_class_table */
	private $logClassCache = array();
	
	/** The log_category_id value to use, set on class creation. */
	private $logCategoryId = null;
	
	/** Default uid (users.id) to log under when no uid is available */
	private $defaultUid = 0;
	
	/** Category to use when logging a database error */
	private $databaseCategory = 1;
	
	/** Last encountered error. */
	private $lastError=null;
	
	/** Last result from db->numRows() */
	private $lastNumrows = null;
	
	/** Last SQL file handled */
	protected $lastSQLFile=null;
	
	/** Global functions class from cs-content */
	private $gfObj;
	
	//=========================================================================
	/**
	 * The constructor.
	 */
	public function __construct(cs_phpDB &$db, $logCategory) {
		//assign the database object.
		$this->db = $db;
		
		$this->gfObj = new cs_globalFunctions;
		
		//assign the log_category_id.
		if(strlen($logCategory)) {
			if(!is_numeric($logCategory)) {
				//attempt to retreive the logCategoryId (assuming they passed a name).
				$this->logCategoryId = $this->get_log_category_id($logCategory);
			}
			else {
				//it was numeric: set it!
				$this->logCategoryId = $logCategory;
			}
		}
		else {
			throw new exception(__METHOD__ .": FATAL: no logCategoryId passed");
		}
		
		//check for a uid in the session.
		if(is_numeric($_SESSION['uid'])) {
			//got an ID in the session.
			$this->defaultUid = $_SESSION['uid'];
		}
		
		//build our cache.
		$this->build_cache();
	}//end __construct()
	//=========================================================================
	
	
	
	//=========================================================================
	public function run_sql($sql) {
		
		if(strlen($sql)) {
			$this->lastNumrows = $this->db->exec($sql);
			$this->lastError = $this->db->errorMsg();
			
			if(!strlen($this->lastError) && $this->lastNumrows > 0) {
				$retval = TRUE;
			}
			else {
				if(strlen($this->lastError)) {
					throw new exception(__METHOD__ .": ". $this->lastError ."<BR>\nSQL::: ". $sql);
				}
				$retval = FALSE;
			}
			
		}
		else {
			throw new exception(__METHOD__ .": no sql to run (". $sql .")");
		}
		
		return($retval);
	}//end run_sql()
	//=========================================================================
	
	
	
	//=========================================================================
	final public function run_sql_file($filename) {
		if(!is_object($this->fsObj)) {
			if(class_exists('cs_fileSystem')) {
				$fsObj = new cs_fileSystem;
			}
			else {
				throw new exception(__METHOD__ .": required library (cs_fileSystem) not found");
			}
		}
		
		$this->lastSQLFile = $filename;
		
		$fileContents = $fsObj->read($filename);
		$this->db->beginTrans(__METHOD__);
		try {
			$this->run_sql($fileContents);
			$this->db->commitTrans();
			$retval = TRUE;
		}
		catch(exception $e) {
			$this->db->rollbackTrans();
			$retval = FALSE;
		}
		
		return($retval);
	}//end run_sql_file()
	//=========================================================================
	
	
	
	//=========================================================================
	private function build_cache() {
		//build query, run it, check for errors.
		$sql = "SELECT log_class_id, lower(name) as name FROM log_class_table";
		$numrows = $this->db->exec($sql);
		$dberror = $this->db->errorMsg();
		
		if(!strlen($dberror) && $numrows == 0) {
			$this->logClassCache = array();
		}
		elseif(strlen($dberror) || $numrows < 0) {
			//something bad happened.
			throw new exception(__METHOD__ .": not enough data ($numrows) or database error:::\n$dberror");
		}
		else {
			//got it.
			$this->logClassCache = $this->db->farray_nvp('name', 'log_class_id');
		}
	}//end build_cache()
	//=========================================================================
	
	
	
	//=========================================================================
	private function get_log_class_id($name) {
		$name = strtolower($name);
		
		//get the id.
		if(isset($this->logClassCache[$name])) {
			//set the id.
			$retval = $this->logClassCache[$name];
		}
		else {
			//not available.  Try to create a new one & refresh the cache.
			$retval = $this->create_log_class($name);
			$this->build_cache();
		}
		
		return($retval);
	}//end get_log_class_id()
	//=========================================================================
	
	
	
	//=========================================================================
	function get_log_event_id($logClassName) {
		$sqlArr = array(
			'log_class_id'		=> $this->get_log_class_id($logClassName),
			'log_category_id'	=> $this->logCategoryId
		);
		$sql = "SELECT log_event_id FROM log_event_table WHERE " .
			$this->gfObj->string_from_array($sqlArr, 'select', NULL, 'numeric');
		$numrows = $this->db->exec($sql);
		$dberror = $this->db->errorMsg();
		
		if(!strlen($dberror) && $numrows == 0 && is_numeric($sqlArr['log_class_id'])) {
			//no records & no error: create one.
			$retval = $this->auto_insert_record($sqlArr['log_class_id']);
		}
		elseif(strlen($dberror) || $numrows !== 1) {
			//database error... DIE.
			throw new exception(__METHOD__ .": database error:::\n$dberror\nSQL:::$sql");
		}
		else {
			//get the data & return it.
			$data = $this->db->farray();
			$retval = $data[0];
		}
		
		return($retval);
	}//end get_log_event_id()
	//=========================================================================
	
	
	
	//=========================================================================
	public function log_by_class($details, $className="error", $uid=NULL) {
		//make sure there's a valid class name.
		if(!strlen($className) || is_null($className)) {
			$className = 'error';
		}
		
		//make sure we've got a uid to log under.
		if(is_null($uid) || !is_numeric($uid)) {
			//set it.
			$uid = $this->defaultUid;
		}
		
		//determine the log_event_id.
		try {
			$logEventId = $this->get_log_event_id($className);
		}
		catch(Exception $e) {
			throw new exception(__METHOD__ .": while attempting to retrieve logEventId, encountered an " .
			"exception:::\n". $e->getMessage() ."\n\nCLASS: $className\nDETAILS: $details");
		}
		
		//check to see what uid to use.
		$myUid = $_SESSION['user_ID'];
		if(!is_numeric($myUid)) {
			//use the internal default uid.
			$myUid = $this->defaultUid;
		}
		
		//okay, setup an array of all the data we need.
		$cleanStringArr = array(
			'log_event_id'	=> 'numeric',
			'uid'			=> 'numeric',
			'affected_uid'	=> 'numeric',
			'details'		=> 'sql'
		);
		$sqlArr = array (
			'log_event_id'	=> $this->gfObj->cleanString($logEventId, 'numeric'),
			'uid'			=> $myUid,
			'affected_uid'	=> $uid,
			'details'		=> $details
		);
		
		//build, run, error-checking.
		$sql = "INSERT INTO log_table ". $this->gfObj->string_from_array($sqlArr, 'insert', NULL, $cleanStringArr, TRUE);
		$numrows = $this->db->exec($sql);
		$dberror = $this->db->errorMsg();
		
		if(strlen($dberror) || $numrows !== 1) {
			//bad.
			throw new exception(__METHOD__ .": no records created ($numrows) or database error:::\n$dberror\n$sql");
		}
		else {
			//good to go.
			$retval = $this->get_last_inserted_id('log_table_log_id_seq');
		}
		
		return($retval);
	}//end log_by_class()
	//=========================================================================
	
	
	
	//=========================================================================
	public function log_dberror($details, $uid=NULL, $skipCurrentCatLog=FALSE) {
		//set the error for the current category.
		if(!$skipCurrentCatLog && ($this->logCategoryId !== $this->databaseCategory)) {
			//yep, log it!
			$this->log_by_class($details, 'error', $uid);
		}
		
		//now log the database error.
		$originalCategoryId = $this->logCategoryId;
		$this->logCategoryId = $this->databaseCategory;
		$retval = $this->log_by_class($details, 'error', $uid);
		$this->logCategoryId = $originalCategoryId;
		
		throw new exception(__METHOD__ .": encountered error::: $details");
		
		//give 'em the result.
		return($retval);
	}//end log_dberror()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Attempts to auto-recover if a class was requested that doesn't exist.
	 */
	private function auto_insert_record($logClassId) {
		//generate a default name
		
		$className = $this->get_log_class_name($logClassId);
		$categoryName = $this->get_log_category_name($this->logCategoryId);
		
		$details = ucwords($categoryName) .": ". ucwords($className);
		
		if(strlen($details) <= 4) {
			//something bad happened (i.e. details="0: 0")
			throw new exception(__METHOD__ .": failed to recover with log_class_id=(". $logClassId .") " .
					"AND log_category_id=(". $this->logCategoryId ."), details=(". $details .")");
		}
		else {
			//create the sql array.
			$sqlArr = array (
				'log_class_id'		=> $logClassId,
				'log_category_id'	=> $this->logCategoryId,
				'description'		=> "'". $this->gfObj->cleanString($details, 'sql') ."'"
			);
			
			//now run the insert.
			$sql = 'INSERT INTO log_event_table '. $this->gfObj->string_from_array($sqlArr, 'insert');
			$numrows = $this->db->exec($sql);
			$dberror = $this->db->errorMsg();
			
			if(strlen($dberror) || $numrows !== 1) {
				//terrible.  So close to auto-recovery.
				throw new exception(__METHOD__ .": unable to recover, numrows=($numrows), dberror:::\n$dberror\n$sql");
			}
			else {
				//got it.  Retrieve the id.
				$retval = $this->get_last_inserted_id('log_event_table_log_event_id_seq');
			}
		}
		
		return($retval);
	}//end auto_insert_record()
	//=========================================================================
	
	
	
	//=========================================================================
	public function get_logs(array $criteria, array $orderBy=NULL, $limit=20, $excludeNavigation=TRUE) {
		//set a default for the limit.
		if(!is_numeric($limit) || $limit < 1) {
			//set it again.
			$limit = 20;
		}
		
		if(is_null($orderBy) || count($orderBy) < 1) {
			//set it.
			$orderBy = array(
				'log_id DESC'
			);
		}
		
		//set the fields that can be used, along with what alias for the table & cleaning type to use on the data.
		$allowedCritFields = array(
			'log_class_id'		=> array('cl',	'numeric'),
			'log_category_id'	=> array('ca',	'numeric'),
			'uid'				=> array('l',	'numeric'),
			'affected_uid'		=> array('l',	'numeric'),
			'creation'			=> array('l',	'sql')
		);
		
		//loop through the data to create our cleaned, prefixed array of criteria.
		$sqlArr = array();
		foreach($criteria AS $field => $value) {
			//is this field in the allowed list?
			if(isset($allowedCritFields[$field])) {
				//grab data for this field.
				$myFieldData = $allowedCritFields[$field];
				$cleanStringArg = $myFieldData[1];
				
				//clean the data.
				if($field == 'creation' && is_numeric($value)) {
					$value = $this->gfObj->cleanString($value, 'numeric');
					$cleanedData = ">= (NOW() - interval '". $value ." hours')";
				}
				else {
					$cleanedData = $this->gfObj->cleanString($value, $cleanStringArg);
				}
				
				//set the prefixed column name.
				$prefixedName = $myFieldData[0] .'.'. $field;
				
				//now add it to our array.
				$sqlArr[$prefixedName] = $cleanedData;
			}
		}
		
		
		//build the criteria.
		$sqlArr['ca.log_category_id'] = '>0';
		$critString = $this->gfObj->string_from_array($sqlArr, 'select');
		
		//check if "timeperiod" is in there (it's special)
		if(isset($criteria['timeperiod']) && isset($criteria['timeperiod']['start']) && isset($criteria['timeperiod']['end'])) {
			//add it in!
			$myTime = $criteria['timeperiod'];
			$addThis = "(l.creation >= '". $myTime['start'] ."'::date AND l.creation <= '". $myTime['end'] ."'::date + interval '1 day')";
			$critString = create_list($critString, $addThis, ' AND ');
		}
		
		$orderString = $this->gfObj->string_from_array($orderBy, 'limit');
		$sql = "select " .
				"l.creation, " .
				"l.log_id, " .
				"l.uid, " .
				"cl.name AS class_name, " .
				"ca.name AS category_name, " .
				"ev.description, " .
				"l.details " .
			"FROM log_table AS l " .
				"INNER JOIN log_event_table AS ev ON (l.log_event_id=ev.log_event_id) " .
				"INNER JOIN log_class_table AS cl ON (cl.log_class_id=ev.log_class_id) " .
				"INNER JOIN log_category_table AS ca ON (ca.log_category_id=ev.log_category_id) " .
			"WHERE " . $critString . " " .
			"ORDER BY " .
				"log_id DESC " .
			"LIMIT ". $limit;
		
		//run it.
		$numrows = $this->db->exec($sql);
		$dberror = $this->db->errorMsg();
		
		$retval = array();
		if(strlen($dberror) || $numrows < 0) {
			//log the problem, and make sure it's not logged twice.
			$this->log_dberror(__METHOD__ .": no rows ($numrows) or database error:::\n". $dberror, NULL, TRUE);
		}
		elseif($numrows > 0) {
			//retrieve the data.
			$retval = $this->db->farray_fieldnames('log_id', NULL, 0);
		}
		
		return($retval);
	}//end get_logs()
	//=========================================================================
	
	
	
	//=========================================================================
	public function get_recent_logs() {
		//set the criteria so we only get the last few entries.
		$retval = $this->get_logs(array(), NULL, 20);
		return($retval);
	}//end get_recent_logs()
	//=========================================================================
	
	
	
	//=========================================================================
	public function get_reports($startPeriod, $endPeriod, array $extraCrit=NULL) {
		//build the query.
		$timePeriod = array(
			'start' => $startPeriod,
			'end'	=> $endPeriod
		);
		
		$criteria = $timePeriod;
		if(is_array($extraCrit)) {
			$criteria = $extraCrit;
		}
		$criteria['timeperiod'] = $timePeriod;
		$criteria['log_class_id'] = 6;
		
		$myLogs = $this->get_logs($criteria,NULL,10);
		
		return($myLogs);
	}//end get_reports()
	//=========================================================================
	
	
	
	//=========================================================================
	private function get_log_category_id($catName) {
		if(strlen($catName) && is_string($catName)) {
			$catName = trim($catName);
			$sql = "SELECT log_category_id FROM log_category_table WHERE lower(name) = '". strtolower($catName) ."'";
			if($this->run_sql($sql)) {
				//got it!
				$data = $this->db->farray();
				$retval = $data[0];
			}
			else {
				//create the category & return the newly-inserted id.
				$retval = $this->create_log_category($catName);
			}
		}
		else {
			throw new exception(__METHOD__ .": log_category name (". $catName .") is invalid");
		}
		
		return($retval);
	}//end get_log_category_id()
	//=========================================================================
	
	
	
	//=========================================================================
	private function create_log_category($catName) {
		$sql = "INSERT INTO log_category_table (name) VALUES ('". 
			$this->gfObj->cleanString($catName, 'sql') ."')";
		if($this->run_sql($sql)) {
			//sweet.  Get the newly created record.
			$retval = $this->get_last_inserted_id('log_category_table_log_category_id_seq');
		}
		else {
			throw new exception(__METHOD__ .": failed to create new log_category (". $catName .")");
		}
		
		return($retval);
	}//end create_log_category()
	//=========================================================================
	
	
	
	//=========================================================================
	private function create_log_class($className) {
		$sql = "INSERT INTO log_class_table (name) VALUES ('". 
			$this->gfObj->cleanString($className, 'sql') ."')";
		if($this->run_sql($sql)) {
			//sweet.  Get the newly created record.
			$retval = $this->get_last_inserted_id('log_class_table_log_class_id_seq');
		}
		else {
			throw new exception(__METHOD__ .": failed to create new log_class");
		}
		
		return($retval);
	}//end create_log_class()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Returns ID of the last inserted record.  Added for future compatibility 
	 * with other database types (mysql doesn't need to know what the sequence
	 * name was, but pgsql does, future DB implementations may need different 
	 * information to do the same thing).
	 */
	private function get_last_inserted_id($sequence) {
		$myId = $this->db->lastID($sequence);
		
		if(!is_numeric($myId) || $myId < 1) {
			throw new exception(__METHOD__ .": failed to retrieve valid id (". $myId .")");
		}
		
		return($myId);
	}//end get_last_inserted_id()
	//=========================================================================
	
	
	
	//=========================================================================
	private function get_log_class_name($classId) {
		if(is_numeric($classId)) {
			$sql = "SELECT name FROM log_class_table WHERE log_class_id=". $classId;
			$res = $this->run_sql($sql);
			if($res) {
				$data = $this->db->farray();
				$className = $data[0];
				
				if(strlen($className) < 2) {
					throw new exception(__METHOD__ .": invalid class name returned (". $className .")");
				}
			}
			else {
				throw new exception(__METHOD__ .": failed to retrieve class name data for id=(". $classId .") - res=(". $res .")");
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid class ID (". $classId .")");
		}
		
		return($className);
	}//end get_log_class_name()
	//=========================================================================
	
	
	
	//=========================================================================
	private function get_log_category_name($categoryId) {
		if(is_numeric($categoryId)) {
			$sql = "SELECT name FROM log_category_table WHERE log_category_id=". $categoryId;
			if($this->run_sql($sql)) {
				$data = $this->db->farray();
				$categoryName = $data[0];
				
				if(strlen($categoryName) < 2) {
					throw new exception(__METHOD__ .": invalid category name returned (". $categoryName .")");
				}
			}
			else {
				throw new exception(__METHOD__ .": failed to retrieve category name data for id=(". $categoryId .")");
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid category ID (". $categoryId .")");
		}
		
		return($categoryName);
	}//end get_log_category_name()
	//=========================================================================
	
	
}//end logsClass{}
?>
