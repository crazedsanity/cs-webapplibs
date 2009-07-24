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

require_once(constant('LIBDIR') .'/cs-versionparse/cs_version.abstract.class.php');

class cs_webdblogger extends cs_versionAbstract {
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
	
	/** Last SQL file handled */
	protected $lastSQLFile=null;
	
	/** Global functions class from cs-content */
	protected $gfObj;
	
	//=========================================================================
	/**
	 * The constructor.
	 */
	public function __construct(cs_phpDB &$db, $logCategory) {
		//assign the database object.
		$this->db = $db;
		
		
		$this->set_version_file_location(dirname(__FILE__) . '/VERSION');
		
		//Make sure the version of cs_phpDB is HIGHER THAN (not equal to) 1.0.0-ALPHA8, 
		//	which added some methods that are required.
		$mustBeHigherThan = '1.2-ALPHA8';
		if(!$this->is_higher_version($mustBeHigherThan, $this->db->get_version())) {
			throw new exception(__METHOD__ .": requires cs_phpDB of higher than v". $mustBeHigherThan,1);
		}
		
		$this->gfObj = new cs_globalFunctions;
		
		//assign the log_category_id.
		if(strlen($logCategory)) {
			if(!is_numeric($logCategory)) {
				//attempt to retreive the logCategoryId (assuming they passed a name)
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
	/**
	 * Execute the entire contents of the given file (with absolute path) as SQL.
	 */
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
			$this->db->run_update($fileContents);
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
	/**
	 * Build internal cache to avoid extra queries.
	 */
	private function build_cache() {
		//build query, run it, check for errors.
		$sql = "SELECT log_class_id, lower(name) as name FROM log_class_table";
		
		try {
			$data = $this->db->run_query($sql, 'name', 'log_class_id');
			
			if(is_array($data)) {
				$this->logClassCache = $data;
			}
			elseif($data == false) {
				$this->logClassCache = array();
			}
			else {
				throw new exception(__METHOD__ .": unknown data returned: ". $this->gfObj->debug_var_dump($data,0));
			}
		}
		catch(exception $e) {
			throw new exception(__METHOD__ .": failed to build internal class cache::: ". $e->getMessage());
		}
	}//end build_cache()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Retrieve log_class_id value from the given name, or insert a new one.
	 */
	private function get_log_class_id($name) {
		$name = strtolower($name);
		
		//get the id.
		if(isset($this->logClassCache[$name])) {
			//set the id.
			$retval = $this->logClassCache[$name];
		}
		else {
			//create the class & then rebuild cache.
			$retval = $this->create_log_class($name);
			$this->build_cache();
		}
		
		return($retval);
	}//end get_log_class_id()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Retrieve log_event_id based on the given class name & the internal 
	 * logCategoryId value.
	 */
	function get_log_event_id($logClassName) {
		$sqlArr = array(
			'log_class_id'		=> $this->get_log_class_id($logClassName),
			'log_category_id'	=> $this->logCategoryId
		);
		$sql = "SELECT log_event_id FROM log_event_table WHERE " .
			$this->gfObj->string_from_array($sqlArr, 'select', NULL, 'numeric');
		
		try {
			$data = $this->db->run_query($sql);
			
			
			if($data === false) {
				//no records & no error: create one.
				$retval = $this->auto_insert_record($sqlArr['log_class_id']);
			}
			elseif(is_array($data) && isset($data['log_event_id'])) {
				$retval = $data['log_event_id'];
			}
			else {
				throw new exception(__METHOD__ .": invalid data returned::: ". $this->gfObj->debug_var_dump($data,0));
			}
		}
		catch(exception $e) {
			throw new exception(__METHOD__ .": failed to retrieve log_event_id::: ". $e->getMessage());
		}
		
		return($retval);
	}//end get_log_event_id()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * The primary means of building log entries: use log_dberror() to log an 
	 * error with a bit more capabilities; throws the details of the error as  
	 * an exception.
	 */
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
		
		try {
			$newId = $this->db->run_insert($sql, 'log_table_log_id_seq');
			
			if(is_numeric($newId) && $newId > 0) {
				$retval = $newId;
			}
			else {
				throw new exception(__METHOD__ .": failed to insert id or invalid return (". $this->gfObj->debug_var_dump($newId,0) .")");
			}
		}
		catch(exception $e) {
			throw new exception(__METHOD__ .": error while creating log::: ". $e->getMessage());
		}
		
		return($retval);
	}//end log_by_class()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Logs an error like log_by_class(), but also throws an exception.
	 */
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
			
			try {
				$newId = $this->db->run_insert($sql, 'log_event_table_log_event_id_seq');
				
				if(is_numeric($newId) && $newId > 0) {
					$retval = $newId;
				}
				else {
					throw new exception(__METHOD__ .": unable to insert id or bad return::: ". $this->gfObj->debug_var_dump($newId,0));
				}
			}
			catch(exception $e) {
				throw new exception(__METHOD__ .": failed to create record::: ". $e->getMessage());
			}
		}
		
		return($retval);
	}//end auto_insert_record()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Retrieves logs with the given criteria.
	 */
	public function get_logs(array $criteria, array $orderBy=NULL, $limit=20) {
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
		
		try {
			//run it.
			$data = $this->db->run_query($sql, 'log_id');
			
			$retval = array();
			if(is_array($data)) {
				$retval = $data;
			}
		}
		catch(exception $e) {
			throw new exception(__METHOD__ .": failed to retrieve logs::: ". $e->getMessage());
		}
		
		return($retval);
	}//end get_logs()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Uses arbitrary criteria to retrieve the last X log entries.
	 */
	public function get_recent_logs($numEntries=null) {
		if(!is_numeric($numEntries) || $numEntries < 1) {
			$numEntries = 20;
		}
		
		//set the criteria so we only get the last few entries.
		$retval = $this->get_logs(array(), NULL, $numEntries);
		return($retval);
	}//end get_recent_logs()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Retrieve log_category_id from the given name.
	 */
	private function get_log_category_id($catName) {
		if(strlen($catName) && is_string($catName)) {
			$catName = trim($catName);
			$sql = "SELECT log_category_id FROM log_category_table WHERE lower(name) = '". strtolower($catName) ."'";
			
			try {
				
				$data = $this->db->run_query($sql);
				
				$numrows = $this->db->numRows();
				if($numrows == 1 && is_array($data) && isset($data['log_category_id']) && is_numeric($data['log_category_id'])) {
					$retval = $data['log_category_id'];
				}
				elseif($data === false) {
					$retval = $this->create_log_category($catName);
				}
				elseif($numrows > 1) {
					throw new exception(__METHOD__ .": found too many records (". $numrows .")");
				}
				else {
					throw new exception(__METHOD__ .": unknown error (bad data in array?)");
				}
			}
			catch(exception $e) {
				throw new exception(__METHOD__ .": encountered error::: ". $e->getMessage());
			}
		}
		else {
			throw new exception(__METHOD__ .": log_category name (". $catName .") is invalid");
		}
		
		return($retval);
	}//end get_log_category_id()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Create a log_category_id based on the given name.
	 */
	private function create_log_category($catName) {
		$sql = "INSERT INTO log_category_table (name) VALUES ('". 
			$this->gfObj->cleanString($catName, 'sql') ."')";
		
		try {
			$newId = $this->db->run_insert($sql, 'log_category_table_log_category_id_seq');
			
			if(is_numeric($newId) && $newId > 0) {
				$retval = $newId;
			}
			else {
				throw new exception(__METHOD__ .": invalid data returned for " .
						"category::: ". $this->gfObj->debug_var_dump($newId,0));
			}
		}
		catch(exception $e) {
			throw new exception(__METHOD__ .": error encountered while trying to " .
					"create category::: ". $e->getMessage());
		}
		
		return($retval);
	}//end create_log_category()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Create a log_class_id based on the given name.
	 */
	private function create_log_class($className) {
		$sql = "INSERT INTO log_class_table (name) VALUES ('". 
			$this->gfObj->cleanString($className, 'sql') ."')";
		
		
		try {
			$newId = $this->db->run_insert($sql, 'log_class_table_log_class_id_seq');
			
			if(is_numeric($newId) && $newId > 0) {
				$retval = $newId;
			}
			else {
				throw new exception(__METHOD__ .": failed to insert class or invalid " .
						"id::: ". $this->gfObj->debug_var_dump($newId,0));
			}
		}
		catch(exception $e) {
			throw new exception(__METHOD__ .": error encountered while creating log " .
					"class::: ". $e->getMessage());
		}
		
		return($retval);
	}//end create_log_class()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Retrieve class name from the given id.
	 */
	private function get_log_class_name($classId) {
		if(is_numeric($classId)) {
			$sql = "SELECT name FROM log_class_table WHERE log_class_id=". $classId;
			
			try {
				$data = $this->db->run_query($sql);
				
				if(is_array($data) && isset($data['name']) && $this->db->numRows() == 1) {
					$className = $data['name'];
				}
				else {
					throw new exception(__METHOD__ .": failed to retrieve class " .
							"name, or invalid return data::: ". $this->gfObj->debug_print($data,0));
				}
			}
			catch(exception $e) {
				throw new exception(__METHOD__ .": error encountered while " .
						"retrieving class name::: ". $e->getMessage());
			}
			
		}
		else {
			throw new exception(__METHOD__ .": invalid class ID (". $classId .")");
		}
		
		return($className);
	}//end get_log_class_name()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Retrieve category name from the given ID.
	 */
	private function get_log_category_name($categoryId) {
		if(is_numeric($categoryId)) {
			$sql = "SELECT name FROM log_category_table WHERE log_category_id=". $categoryId;
			
			try {
				$data = $this->db->run_query($sql);
				
				if(is_array($data) && isset($data['name']) && $this->db->numRows() == 1) {
					$categoryName = $data['name'];
				}
				else {
					throw new exception(__METHOD__ .": failed to retrieve " .
							"category name::: ". $this->gfObj->debug_var_dump($data,0));
				}
			}
			catch(exception $e) {
				throw new exception(__METHOD__ .": error encountered while " .
						"retrieving class name::: ". $e->getMessage());
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
