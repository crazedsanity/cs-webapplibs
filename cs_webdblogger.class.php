<?php
/*
 * Created on Mar 8, 2007
 * 
 * NOTICE::: this class was derived from the logsClass.php found in cs-projet v1.2, found
 * at URL: https://cs-project.svn.sourceforge.net/svnroot/cs-project/trunk/1.2/lib/logsClass.php
 * Last SVN Signature (from cs-project v1.2): "logsClass.php 819 2008-02-09 10:01:10Z crazedsanity"
 * 
 * Each class that's trying to log should have an internal var statically set to indicates what category 
 * it is: this allows them to call a method within this and tell it ONLY what "class" the log should be
 * under, so this class can determine the appropriate log_event_id.  This avoids having to hard-code 
 * too many id's that might need to be changed later.  Yay, dynamic code!
 * 
 * QUERY TO GET LAST COUPLE OF LOGS::::
 SELECT l.log_id as id, l.creation, l.event_id as lid, le.description AS event, l.details FROM cswal_log_table AS l INNER JOIN cswal_event_table AS le USING (event_id) ORDER BY log_id DESC LIMIT 25;
 */


class cs_webdblogger extends cs_webapplibsAbstract {
	/** Database handle */
	public $db;
	
	/** Cache of all records in the class table */
	protected $logClassCache = array();
	
	/** Cache of all records in the attribute table */
	protected $attributeCache=array();
	
	/** The category_id value to use, set on class creation. */
	protected $logCategoryId = null;
	
	/** Default uid (users.id) to log under when no uid is available */
	protected $defaultUid = 0;
	
	/** Category to use when logging a database error */
	//TODO: make SURE this category is correct...
	protected $databaseCategory = 1;
	
	/** Check to see if setup has been performed (avoids running it multiple times) **/
	protected $setupComplete=false;
	
	/** Last SQL file handled */
	protected $lastSQLFile=null;
	
	/** Global functions class from cs-content */
	protected $gfObj;
	
	/**  */
	protected $fsObj;
	
	protected $pendingLogs;
	protected $suspendLogging=false;
	
	/** List of tables keyed off an internal reference name. */
	const categoryTable = 'cswal_category_table';
	const classTable = 'cswal_class_table';
	const eventTable = 'cswal_event_table';
	const logTable = 'cswal_log_table';
	const attribTable = 'cswal_attribute_table';
	const logAttribTable = 'cswal_log_attribute_table';
	
	
	
	/** List of sequences keyed off an internal reference name (MUST match references above) */
	const categoryTableSeq = 'cswal_category_table_category_id_seq';
	const classTableSeq = 'cswal_class_table_class_id_seq';
	const eventTableSeq = 'cswal_event_table_event_id_seq';
	const logTableSeq = 'cswal_log_table_log_id_seq';
	const attribTableSeq = 'cswal_attribute_table_attribute_id_seq';
	const logAttribTableSeq = 'cswal_log_attribute_table_log_attribute_id_seq';
	
	public $cometDebug = "NONE";
	
	//=========================================================================
	/**
	 * The constructor.
	 */
	public function __construct(cs_phpDB $db, $logCategory=null, $checkForUpgrades=true) {
		//assign the database object.
		if(is_object($db)) {
			$this->db = $db;
		}
		else {
			throw new exception(__METHOD__ .":: invalid database object");
		}
		
		$this->set_version_file_location(dirname(__FILE__) . '/VERSION');
		
		$mustBeHigherThan = '1.5.0';
		if(!$this->is_higher_version($mustBeHigherThan, $this->db->get_version())) {
			throw new exception(__METHOD__ .": requires cs_phpDB of higher than v". $mustBeHigherThan,1);
		}
		
		parent::__construct(true);
		
		//see if there's an upgrade to perform...
		if($checkForUpgrades === true) {
			$this->suspendLogging = true;
			$upgObj = new cs_webdbupgrade(dirname(__FILE__) . '/VERSION', dirname(__FILE__) .'/upgrades/upgrade.xml', $db);
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
				$this->fsObj = new cs_fileSystem;
			}
			else {
				throw new exception(__METHOD__ .": required library (cs_fileSystem) not found");
			}
		}
		
		$this->lastSQLFile = $filename;
		
		$fileContents = $this->fsObj->read($filename);
		try {
			$this->db->exec($fileContents);
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
	protected function build_cache() {
		//build query, run it, check for errors.
		$sql = "SELECT class_id, lower(class_name) as name FROM ". self::classTable;
		
		try {
			$this->db->run_query($sql);
			$data = $this->db->farray_fieldnames();
			
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
		$sql = "SELECT attribute_id, lower(attribute_name) AS attribute_name FROM ". self::attribTable;
		
		try {
			$this->db->run_query($sql);
			$data = $this->db->farray_nvp('attribute_name', 'attribute_id');
			
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
	protected function get_class_id($name) {
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
	public function get_event_id($logClassName) {
		$params = array(
			'classId'		=> $this->get_class_id($logClassName),
			'categoryId'	=> $this->logCategoryId
		);
		$sql = "SELECT event_id FROM ". self::eventTable ." WHERE " .
			"class_id=:classId AND category_id=:categoryId";
		
		try {
			$numRows = $this->db->run_query($sql, $params);
			$data = $this->db->farray_fieldnames();
			
			
			if($numRows == 0) {
				//no records & no error: create one.
				$retval = $this->auto_insert_record($params['class_id']);
			}
			elseif($numRows == 1 && is_array($data) && isset($data['event_id'])) {
				$retval = $data['event_id'];
			}
			else {
//$this->gfObj->debug_print($sql, 1);
//$this->gfObj->debug_print($params,1);
//$this->gfObj->debug_print($numRows,1);
//$this->gfObj->debug_print($data,1);
//$this->gfObj->debug_print($params,1);
cs_debug_backtrace(1);
				throw new exception("invalid data returned::: ". $this->gfObj->debug_var_dump($data,0));
			}
		}
		catch(exception $e) {
cs_debug_backtrace(1);
			throw new exception(__METHOD__ .": failed to retrieve event_id, numrows=(". $numRows ."), DETAILS::: ::: ". $e->getMessage());
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
			$params = array (
				'eventId'	=> $this->gfObj->cleanString($logEventId, 'numeric'),
				'uid'			=> $myUid,
				'affectedUid'	=> $uid,
				'details'		=> $details
			);
			
			//build, run, error-checking.
			$sql = "INSERT INTO ". self::logTable ." (event_id, uid, affected_uid, details) ". 
					" VALUES (:eventId, :uid, :affectedUid, :details)";
			
			try {
				$this->db->run_query($sql, $params);
				$newId = $this->db->lastInsertId(self::logTableSeq);
				
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
		return($retval);
	}//end log_dberror()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Attempts to auto-recover if a class was requested that doesn't exist.
	 */
	protected function auto_insert_record($logClassId) {
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
				'classId'		=> $logClassId,
				'categoryId'	=> $this->logCategoryId,
				'description'	=> $details
			);
			
			//now run the insert.
			$sql = 'INSERT INTO '. self::eventTable .' (class_id, category_id, description) '. 
					'VALUES (:classId, :categoryId, :description)';
			
			try {
				$this->db->run_query($sql, $$sqlArr);
				$newId = $this->db->lastInsertId(self::eventTableSeq);
				
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
	
	
	//TODO: create methods for log retrieval that are parameterized...
	
	
	
	//=========================================================================
	/**
	 * Retrieve category_id from the given name.
	 */
	protected function get_category_id($catName) {
		if(strlen($catName) && is_string($catName)) {
			$catName = trim(strtolower($catName));
			$sql = "SELECT * FROM ". self::categoryTable ." WHERE lower(category_name) = :catName";
			
			try {
				
				$numrows = $this->db->run_query($sql, array('catName'=>$catName));
				$data = $this->db->get_single_record();
				
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
					#cs_debug_backtrace(1);
					$this->gfObj->debug_print(__METHOD__ .": numrows=(". $numrows ."), DATA::: ". $this->gfObj->debug_print($data,0),1);
					throw new exception(__METHOD__ .": unknown error (bad data in array?)");
				}
			}
			catch(exception $e) {
				if($this->setupComplete === true) {
					throw new exception(__METHOD__ .": encountered error::: ". $e->getMessage());
				}
				else {
					// TODO: re-implement database-specific check (requires implementing cs_phpDB::get_dbtype())
					$mySchemaFile = dirname(__FILE__) .'/setup/schema.pgsql.sql';
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
	protected function create_log_category($catName) {
		$sql = "INSERT INTO ". self::categoryTable ." (category_name) ".
				" VALUES (:categoryName)";
		try {
			$this->db->run_query($sql, array('categoryName' => $catName));
			$newId = $this->db->lastInsertId(self::categoryTableSeq);
			
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
	protected function create_class($className) {
		$sql = "INSERT INTO ". self::classTable ." (class_name) VALUES ".
				"(:className)";
		
		
		try {
			$this->db->run_query($sql, array('className'=>$className));
			$newId = $this->db->lastInsertId(self::classTableSeq);
			
			if(is_numeric($newId) && $newId > 0) {
				$retval = $newId;
				$this->logClassCache[strtolower($className)] = $retval;
			}
			else {
				cs_debug_backtrace(1);
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
	protected function get_class_name($classId) {
		if(is_numeric($classId)) {
			$sql = "SELECT class_name FROM ". self::classTable ." WHERE class_id=:classId";
			
			try {
				$this->db->run_query($sql, array('classId'=>$classId));
				$data = $this->db->get_single_record();
				
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
	protected function get_category_name($categoryId) {
		if(is_numeric($categoryId)) {
			$sql = "SELECT category_name FROM ". self::categoryTable ." WHERE category_id=:categoryId";
			
			try {
				$this->db->run_query($sql, array('categoryId'=>$categoryId));
				$data = $this->db->get_single_record();
				
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
			foreach($myLogs as $args) {
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
		//TODO: use a configurable location for the uid
		if(isset($_SESSION) && is_array($_SESSION) && isset($_SESSION['uid']) && is_numeric($_SESSION['uid'])) {
			//got an ID in the session.
			$myUid = $_SESSION['uid'];
		}
		return($myUid);
	}//end get_uid()
	//=========================================================================
	
	
	
	//=========================================================================
	protected function create_attribute($attribName, $buildCache=true) {
		
		$myId = null;
		if(isset($this->attributeCache[strtolower($attribName)])) {
			$myId = $this->attributeCache[strtolower($attribName)];
		}
		else {
			$sql = "INSERT INTO ". self::attribTable ." (attribute_name) " .
					"VALUES (:attribName)";
			
			try {
				$this->db->run_query($sql, array('attribName'=>$attribName));
				$myId = $this->db->lastInsertId(self::attribTableSeq);
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
	protected function create_log_attributes($logId, array $attribs) {
		$myIds = array();
		foreach($attribs as $name=>$val) {
			$insertData = array(
				'logId'			=> $logId,
				'attributeId'	=> $this->create_attribute($name, false),
				'valueText'		=> $val
			);
			$sql = "INSERT INTO ". self::logAttribTable ." (log_id, ".
					"attribute_id, value_text) VALUES (:logId, :attributeId, :valueText)";
			
			try {
				$this->db->run_query($sql, $insertData);
				$myIds[$name][] = $this->db->lastInsertId(self::logAttribTableSeq);
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
