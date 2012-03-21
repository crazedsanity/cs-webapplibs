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
 SELECT l.log_id as id, l.creation, l.event_id as lid, le.description AS event, l.details FROM cswal_log_table AS l INNER JOIN cswal_event_table AS le USING (event_id) ORDER BY log_id DESC LIMIT 25;
 */

//NOTE::: this class **REQUIRES** cs-content for its "cs_phpDB" class.


class cs_webdblogger extends cs_webapplibsAbstract {
	/** Database handle */
	public $db;
	
	/** Cache of all records in the class table */
	private $logClassCache = array();
	
	/** Cache of all records in the attribute table */
	private $attributeCache=array();
	
	/** The category_id value to use, set on class creation. */
	private $logCategoryId = null;
	
	/** Default uid (users.id) to log under when no uid is available */
	private $defaultUid = 0;
	
	/** Category to use when logging a database error */
	//TODO: make SURE this category is correct...
	private $databaseCategory = 1;
	
	/** Check to see if setup has been performed (avoids running it multiple times) **/
	private $setupComplete=false;
	
	/** Last SQL file handled */
	protected $lastSQLFile=null;
	
	/** Global functions class from cs-content */
	protected $gfObj;
	
	protected $pendingLogs;
	private $suspendLogging=false;
	
	/** List of tables keyed off an internal reference name. */
	protected $tables = array(
		'category'	=> 'cswal_category_table',
		'class'		=> 'cswal_class_table',
		'event'		=> 'cswal_event_table',
		'log'		=> 'cswal_log_table',
		'attrib'	=> 'cswal_attribute_table',
		'logAttrib'	=> 'cswal_log_attribute_table'
	);
	
	/** List of sequences keyed off an internal reference name (MUST match references above) */
	protected $seqs = array(
		'category'		=> "cswal_category_table_category_id_seq",
		'class'			=> "cswal_class_table_class_id_seq",
		'event'			=> "cswal_event_table_event_id_seq",
		'log'			=> "cswal_log_table_log_id_seq",
		'attrib'		=> "cswal_attribute_table_attribute_id_seq",
		'logAttrib'		=> "cswal_log_attribute_table_log_attribute_id_seq"
	);
	
	public $cometDebug = "NONE";
	
	//=========================================================================
	/**
	 * The constructor.
	 */
	public function __construct(cs_phpDB &$db, $logCategory=null, $checkForUpgrades=true) {
		//assign the database object.
		if(is_object($db) && get_class($db) == 'cs_phpDB') {
			$this->db = $db;
		}
		else {
			throw new exception(__METHOD__ .":: invalid database object");
		}
		
		$this->set_version_file_location(dirname(__FILE__) . '/VERSION');
		
		//Make sure the version of cs_phpDB is HIGHER THAN (not equal to) 1.0.0-ALPHA8, 
		//	which added some methods that are required.
		$mustBeHigherThan = '1.2-ALPHA8';
		if(!$this->is_higher_version($mustBeHigherThan, $this->db->get_version())) {
			throw new exception(__METHOD__ .": requires cs_phpDB of higher than v". $mustBeHigherThan,1);
		}
		
		parent::__construct(true);
		
		//see if there's an upgrade to perform...
		if($checkForUpgrades === true) {
			$this->suspendLogging = true;
			$upgObj = new cs_webdbupgrade(dirname(__FILE__) . '/VERSION', dirname(__FILE__) .'/upgrades/upgrade.xml', $db->connectParams);
			$upgObj->check_versions(true);
			$this->suspendLogging = false;
			$this->handle_suspended_logs();
		}
		
		//assign the category_id.
		if(strlen($logCategory)) {
			if(!is_numeric($logCategory)) {
				//attempt to retreive the logCategoryId (assuming they passed a name)
				$this->logCategoryId = $this->get_category_id($logCategory);
			}
			else {
				//it was numeric: set it!
				$this->logCategoryId = $logCategory;
			}
		}
		
		//check for a uid in the session.
		$this->defaultUid = $this->get_uid();
		
		
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
		try {
			$this->db->run_update($fileContents, true);
			$this->build_cache();
			$retval = TRUE;
		}
		catch(exception $e) {
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
		$sql = "SELECT class_id, lower(class_name) as name FROM ". $this->tables['class'];
		
		try {
			$data = $this->db->run_query($sql, 'name', 'class_id');
			
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
		
		//now build cache for attributes.
		$sql = "SELECT attribute_id, lower(attribute_name) AS attribute_name FROM ". $this->tables['attrib'];
		
		try {
			$data = $this->db->run_query($sql, 'attribute_name', 'attribute_id');
			
			if(is_array($data)) {
				$this->attributeCache = $data;
			}
			elseif($data == false) {
				$this->attributeCache = array();
			}
			else {
				throw new exception(__METHOD__ .": unknown data returned: ". $this->gfObj->debug_var_dump($data,0));
			}
		}
		catch(exception $e) {
			throw new exception(__METHOD__ .": error occurred while retrieving attribute cache::: ". $e->getMessage());
		}
	}//end build_cache()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Retrieve log_class_id value from the given name, or insert a new one.
	 */
	private function get_class_id($name) {
		$name = strtolower($name);
		
		//get the id.
		if(isset($this->logClassCache[$name])) {
			//set the id.
			$retval = $this->logClassCache[$name];
		}
		else {
			//create the class & then rebuild cache.
			$retval = $this->create_class($name);
			$this->build_cache();
		}
		
		return($retval);
	}//end get_class_id()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Retrieve log_event_id based on the given class name & the internal 
	 * logCategoryId value.
	 */
	function get_event_id($logClassName) {
		$sqlArr = array(
			'class_id'		=> $this->get_class_id($logClassName),
			'category_id'	=> $this->logCategoryId
		);
		$sql = "SELECT event_id FROM ". $this->tables['event'] ." WHERE " .
			$this->gfObj->string_from_array($sqlArr, 'select', NULL, 'numeric');
		
		try {
			$data = $this->db->run_query($sql);
			
			
			if($data === false) {
				//no records & no error: create one.
				$retval = $this->auto_insert_record($sqlArr['class_id']);
			}
			elseif(is_array($data) && isset($data['event_id'])) {
				$retval = $data['event_id'];
			}
			else {
				throw new exception(__METHOD__ .": invalid data returned::: ". $this->gfObj->debug_var_dump($data,0));
			}
		}
		catch(exception $e) {
			throw new exception(__METHOD__ .": failed to retrieve event_id::: ". $e->getMessage());
		}
		
		return($retval);
	}//end get_event_id()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * The primary means of building log entries: use log_dberror() to log an 
	 * error with a bit more capabilities; throws the details of the error as  
	 * an exception.
	 */
	public function log_by_class($details, $className="error", $uid=NULL, array $logAttribs=NULL) {
		
		if(is_null($details) || !strlen($details)) {
			$details = "(". __METHOD__ .": no details given)";
		}
		if($this->suspendLogging === true) {
			$this->pendingLogs[] = func_get_args();
			$retval = count($this->pendingLogs) -1;
		}
		else {
			if(count($this->pendingLogs)) {
				$this->handle_suspended_logs();
			}
			
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
				$logEventId = $this->get_event_id($className);
			}
			catch(Exception $e) {
				throw new exception(__METHOD__ .": while attempting to retrieve logEventId, encountered an " .
				"exception:::\n". $e->getMessage() ."\n\nCLASS: $className\nDETAILS: $details");
			}
			
			//check to see what uid to use.
			$myUid = $this->get_uid();
			
			//okay, setup an array of all the data we need.
			$cleanStringArr = array(
				'event_id'		=> 'numeric',
				'uid'			=> 'numeric',
				'affected_uid'	=> 'numeric',
				'details'		=> 'sql'
			);
			$sqlArr = array (
				'event_id'	=> $this->gfObj->cleanString($logEventId, 'numeric'),
				'uid'			=> $myUid,
				'affected_uid'	=> $uid,
				'details'		=> $details
			);
			
			//build, run, error-checking.
			$sql = "INSERT INTO ". $this->tables['log'] ." ". $this->gfObj->string_from_array($sqlArr, 'insert', NULL, $cleanStringArr, TRUE);
			
			try {
				$newId = $this->db->run_insert($sql, $this->seqs['log']);
				
				if(is_numeric($newId) && $newId > 0) {
					$retval = $newId;
					
					if(is_array($logAttribs) && count($logAttribs)) {
						$this->create_log_attributes($newId, $logAttribs);
					}
				}
				else {
					throw new exception(__METHOD__ .": failed to insert id or invalid return (". $this->gfObj->debug_var_dump($newId,0) .")");
				}
			}
			catch(exception $e) {
				throw new exception(__METHOD__ .": error while creating log::: ". $e->getMessage());
			}
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
		
		$className = $this->get_class_name($logClassId);
		$categoryName = $this->get_category_name($this->logCategoryId);
		
		$details = ucwords($categoryName) .": ". ucwords($className);
		
		if(strlen($details) <= 4) {
			//something bad happened (i.e. details="0: 0")
			throw new exception(__METHOD__ .": failed to recover with class_id=(". $logClassId .") " .
					"AND category_id=(". $this->logCategoryId ."), details=(". $details .")");
		}
		else {
			//create the sql array.
			$sqlArr = array (
				'class_id'		=> $logClassId,
				'category_id'	=> $this->logCategoryId,
				'description'		=> "'". $this->gfObj->cleanString($details, 'sql') ."'"
			);
			
			//now run the insert.
			$sql = 'INSERT INTO '. $this->tables['event'] .' '. $this->gfObj->string_from_array($sqlArr, 'insert');
			
			try {
				$newId = $this->db->run_insert($sql, $this->seqs['event']);
				
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
	public function get_logs(array $criteria, array $orderBy=NULL, $limit=20, $greaterThanLogId=null) {
		$originalCrit = $criteria;
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
			'class_id'			=> array('cl',	'numeric'),
			'category_id'		=> array('ca',	'numeric'),
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
				
				//set the prefixed column name.
				$prefixedName = $myFieldData[0] .'.'. $field;
				
				//clean the data.
				if($field == 'creation' && is_numeric($value)) {
					$value = $this->gfObj->cleanString($value, 'numeric');
					$cleanedData = ">= (NOW() - interval '". $value ." hours')";
				}
				else {
					$cleanedData = $this->gfObj->cleanString($value, $cleanStringArg);
				}
				
				//now add it to our array.
				$sqlArr[$prefixedName] = $cleanedData;
			}
		}
		
		
		//build the criteria.
		if(!isset($sqlArr['ca.category_id'])) {
			$sqlArr['ca.category_id'] = '>0';
			$cleanStringArr['ca.category_id'] = "select";
		}
		$critString = $this->gfObj->string_from_array($sqlArr, 'select');
		
		//check if "timeperiod" is in there (it's special)
		if(isset($criteria['timeperiod']) && isset($criteria['timeperiod']['start']) && isset($criteria['timeperiod']['end'])) {
			//add it in!
			$myTime = $criteria['timeperiod'];
			$addThis = "(l.creation >= '". $myTime['start'] ."'::date AND l.creation <= '". $myTime['end'] ."'::date + interval '1 day')";
			$critString = create_list($critString, $addThis, ' AND ');
		}
		if(!is_null($greaterThanLogId) && is_numeric($greaterThanLogId)) {
			$critString = $this->gfObj->create_list($critString, "log_id > ". $greaterThanLogId, ' AND ');
		}
		
		$orderString = $this->gfObj->string_from_array($orderBy, 'limit');
		$sql = "select " .
				"l.creation, " .
				"l.log_id, " .
				"l.uid, " .
				"cl.class_name, " .
				"ca.category_name, " .
				"ev.description, " .
				"l.details " .
			"FROM ". $this->tables['log'] ." AS l " .
				"INNER JOIN ". $this->tables['event'] ." AS ev ON (l.event_id=ev.event_id) " .
				"INNER JOIN ". $this->tables['class'] ." AS cl ON (cl.class_id=ev.class_id) " .
				"INNER JOIN ". $this->tables['category'] ." AS ca ON (ca.category_id=ev.category_id) " .
			"WHERE " . $critString . " " .
			"ORDER BY " .
				"log_id DESC " .
			"LIMIT ". $limit;
		
		try {
			//run it.
			$this->cometDebug = array(
				'criteria'			=> $criteria,
				'sql'				=> $sql
			);
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
	 * Retrieve category_id from the given name.
	 */
	private function get_category_id($catName) {
		if(strlen($catName) && is_string($catName)) {
			$catName = trim($catName);
			$sql = "SELECT category_id FROM ". $this->tables['category'] ." WHERE lower(category_name) = '". strtolower($catName) ."'";
			
			try {
				
				$data = $this->db->run_query($sql);
				
				$numrows = $this->db->numRows();
				if($numrows == 1 && is_array($data) && isset($data['category_id']) && is_numeric($data['category_id'])) {
					$retval = $data['category_id'];
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
				if($this->setupComplete === true) {
					throw new exception(__METHOD__ .": encountered error::: ". $e->getMessage());
				}
				else {
					$mySchemaFile = dirname(__FILE__) .'/setup/schema.'. $this->db->get_dbtype() .'.sql';
					if(file_exists($mySchemaFile)) {
						$this->setupComplete = true;
						$this->run_sql_file($mySchemaFile);
						
						//Create the default category.
						$this->create_log_category('Database');
						
						$retval = $this->create_log_category($catName);
					}
					else {
						throw new exception(__METHOD__ .": missing schema file (". $mySchemaFile ."), can't run setup");
					}
				}
			}
		}
		else {
			throw new exception(__METHOD__ .": category name (". $catName .") is invalid");
		}
		
		return($retval);
	}//end get_category_id()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Create a category_id based on the given name.
	 */
	private function create_log_category($catName) {
		$sql = "INSERT INTO ". $this->tables['category'] ." (category_name) VALUES ('". 
			$this->gfObj->cleanString($catName, 'sql') ."')";
		
		try {
			$newId = $this->db->run_insert($sql, $this->seqs['category']);
			
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
	private function create_class($className) {
		$sql = "INSERT INTO ". $this->tables['class'] ." (class_name) VALUES ('". 
			$this->gfObj->cleanString($className, 'sql') ."')";
		
		
		try {
			$newId = $this->db->run_insert($sql, $this->seqs['class']);
			
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
	}//end create_class()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Retrieve class name from the given id.
	 */
	private function get_class_name($classId) {
		if(is_numeric($classId)) {
			$sql = "SELECT class_name FROM ". $this->tables['class'] ." WHERE class_id=". $classId;
			
			try {
				$data = $this->db->run_query($sql);
				
				if(is_array($data) && isset($data['class_name']) && $this->db->numRows() == 1) {
					$className = $data['class_name'];
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
	}//end get_class_name()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Retrieve category name from the given ID.
	 */
	private function get_category_name($categoryId) {
		if(is_numeric($categoryId)) {
			$sql = "SELECT category_name FROM ". $this->tables['category'] ." WHERE category_id=". $categoryId;
			
			try {
				$data = $this->db->run_query($sql);
				
				if(is_array($data) && isset($data['category_name']) && $this->db->numRows() == 1) {
					$categoryName = $data['category_name'];
				}
				else {
					throw new exception(__METHOD__ .": failed to retrieve " .
							"category name::: ". $this->gfObj->debug_var_dump($data,0));
				}
			}
			catch(exception $e) {
				throw new exception(__METHOD__ .": error encountered while " .
						"retrieving category name::: ". $e->getMessage());
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid category ID (". $categoryId .")");
		}
		
		return($categoryName);
	}//end get_category_name()
	//=========================================================================
	
	
	
	//=========================================================================
	public function __get($var) {
		return($this->$var);
	}//end __get()
	//=========================================================================
	
	
	
	//=========================================================================
	public function __set($var, $newVal) {
		$res = false;
		switch($var) {
			case 'suspendLogging':
				$this->$var = $newVal;
				if($newVal === false) {
					#$this->handle_suspended_logs();
				}
				$res = true;
				break;
			
			case 'logCategory':
			case 'logCategoryId':
				$this->logCategoryId = $this->get_category_id($newVal);
				$res = true;
				break;
		}
		return($res);
	}//end __set()
	//=========================================================================
	
	
	
	//=========================================================================
	public function handle_suspended_logs() {
		$retval = 0;
		$debugThis = array();
		if($this->suspendLogging === false && count($this->pendingLogs)) {
			$myLogs = $this->pendingLogs;
			$this->build_cache();
			$this->pendingLogs = array();
			foreach($myLogs as $i=>$args) {
				//this is potentially deadly: call self recursively to log the items prevously suspended.
				$newId = call_user_func_array(array($this, 'log_by_class'), $args);
				
				$debugThis[$newId] = $args;
				$retval++;
			}
		}
		return($retval);
	}//end handle_suspended_logs()
	//=========================================================================
	
	
	
	//=========================================================================
	public function get_uid() {
		$myUid = $this->defaultUid;
		//check for a uid in the session.
		if(isset($_SESSION) && is_array($_SESSION) && isset($_SESSION['uid']) && is_numeric($_SESSION['uid'])) {
			//got an ID in the session.
			$myUid = $_SESSION['uid'];
		}
		return($myUid);
	}//end get_uid()
	//=========================================================================
	
	
	
	//=========================================================================
	private function create_attribute($attribName, $buildCache=true) {
		
		$myId = null;
		if(isset($this->attributeCache[strtolower($attribName)])) {
			$myId = $this->attributeCache[strtolower($attribName)];
		}
		else {
			$sql = "INSERT INTO ". $this->tables['attrib'] ." (attribute_name) " .
					"VALUES ('". $this->gfObj->cleanString($attribName, 'sql_insert') ."')";
			
			try {
				$myId = $this->db->run_insert($sql, $this->seqs['attrib']);
			}
			catch(exception $e) {
				throw new exception(__METHOD__ .": fatal error while creating attribute (". $attribName .")::: ". $e->getMessage());
			}
		}
		
		if($buildCache) {
			$this->build_cache();
		}
		
		return($myId);
	}//end create_attribute()
	//=========================================================================
	
	
	
	//=========================================================================
	private function create_log_attributes($logId, array $attribs) {
		$myIds = array();
		foreach($attribs as $name=>$val) {
			$insertData = array(
				'log_id'		=> $logId,
				'attribute_id'	=> $this->create_attribute($name, false),
				'value_text'	=> $val
			);
			$sql = "INSERT INTO ". $this->tables['logAttrib'] ." ". 
					$this->gfObj->string_from_array($insertData, 'insert');
			
			try {
				$myIds[$name][] = $this->db->run_insert($sql, $this->seqs['logAttrib']);
			}
			catch(exception $e) {
				throw new exception(__METHOD__ .": fatal error while creating log attribute " .
						"(". $name .")::: ". $e->getMessage());
			}
		}
		$this->build_cache();
		
	}//end create_log_attributes()
	//=========================================================================
	
	
}//end logsClass{}
?>
