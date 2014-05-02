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
	const userTable = 'cs_authentication_table';
	
	
	
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
			$upgObj = new cs_webdbupgrade(dirname(__FILE__) . '/VERSION', dirname(__FILE__) .'/upgrades/upgrade.ini', $db);
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
			$data = $this->db->farray_nvp('name', 'class_id');
			
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
			$data = $this->db->get_single_record();
			
			if($numRows == 0) {
				//no records & no error: create one.
				$retval = $this->auto_insert_record($params['classId']);
			}
			elseif($numRows == 1 && is_array($data) && isset($data['event_id'])) {
				$retval = $data['event_id'];
			}
			else {
				throw new exception("invalid data returned::: ". $this->gfObj->debug_var_dump($data,0));
			}
		}
		catch(exception $e) {
			throw new exception(__METHOD__ .": failed to retrieve event_id, DETAILS::: ". $e->getMessage());
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
				$newId = $this->db->run_insert($sql, $sqlArr, self::eventTableSeq);
				
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
				elseif($numrows == 0 || $data == array() || $data === false) {
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
			$newId = $this->db->run_insert($sql, array('categoryName' => $catName), self::categoryTableSeq);
			
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
				$numRows = $this->db->run_query($sql, array('classId'=>$classId));
				$data = $this->db->get_single_record();
				
				if(is_array($data) && isset($data['class_name']) && $numRows == 1) {
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
				$myId = $this->db->run_insert($sql, array('attribName'=>$attribName), self::attribTableSeq);
			}
			catch(exception $e) {
				throw new exception(__METHOD__ .": fatal error while creating attribute (". $attribName .")::: ". $e->getMessage());
			}
			$this->build_cache();
			$buildCache = false;
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
				$myIds[$name][] = $this->db->run_insert($sql, $insertData, self::logAttribTableSeq);
			}
			catch(exception $e) {
				throw new exception(__METHOD__ .": fatal error while creating log attribute " .
						"(". $name .")::: ". $e->getMessage());
			}
		}
		$this->build_cache();
		
	}//end create_log_attributes()
	//=========================================================================
	
	
	
	//=========================================================================
	public function get_logs($criteria, array $pagination=null) {
		//TODO: allow array for $criteria, so more complex operations can be done
		$_crit = "";
		if(!is_null($criteria) && is_string($criteria) && strlen($criteria) > 0) {
			$_crit = strtolower($criteria);
		}
		
		$_orderBy = " ORDER BY log_id DESC";
		$_limit = " LIMIT 100";
		$_offset = "";
		if(!is_null($pagination) && is_array($pagination) && count($pagination) > 0) {
			foreach($pagination as $k=>$v) {
				switch(strtolower($k)) {
					case "order":
						$_orderBy = " ORDER BY ". $v;
						break;
					
					case "limit":
						if(is_numeric($v)) {
							$_limit = " LIMIT ". $v;
						}
						elseif(is_null($v)) {
							$_limit = "";
						}
						else {
							throw new InvalidArgumentException(__METHOD__ .": non-numeric argument for limit (". $v .")");
						}
						break;
					
					case "offset":
						if(is_numeric($v)) {
							$_offset = "OFFSET ". $v;
						}
						elseif(is_null($v)) {
							$_offset = "";
						}
						else {
							throw new InvalidArgumentException(__METHOD__ .": non-numeric argument for offset (". $v .")");
						}
						break;
					
					default:
						throw new InvalidArgumentException(__METHOD__ .": invalid index '". $k ."'");
				}
			}
		}
		
		//TODO: handle more complex scenarios, like time periods.
		$sql = "SELECT 
			l.*, cl.class_name, ca.category_name, ev.description, u.username, u.email
			FROM ". self::logTable ." AS l 
				INNER JOIN ". self::eventTable ." AS ev ON (l.event_id=ev.event_id) 
				INNER JOIN ". self::classTable ." AS cl ON (cl.class_id=ev.class_id) 
				INNER JOIN ". self::categoryTable ." AS ca ON (ca.category_id=ev.category_id)
				INNER JOIN ". self::userTable ." AS u ON (l.uid=u.uid)";
		
		$params = array();
		if(strlen($_crit)) {
			$sql .= " WHERE l.details LIKE :search::text
				OR cl.class_name LIKE :search2::text
				OR ca.category_name LIKE :search3::text
				OR ev.description LIKE :search4::text";
			$params['search'] = "%". $_crit ."%";
			$params['search2'] = "%". $_crit ."%";
			$params['search3'] = "%". $_crit ."%";
			$params['search4'] = "%". $_crit ."%";
		}
		$sql .= $_orderBy . $_limit;
		
		
		try {
//cs_global::debug_print(__METHOD__ .": SQL::: ". $sql,1);
			$numrows = $this->db->run_query($sql, $params);
			
			$retval = array();
			if($numrows > 0) {
				$retval = $this->db->farray_fieldnames();
			}
		} catch (Exception $ex) {
			throw new ErrorException(__METHOD__ .": failed to retrieve logs, details::: ". $ex->getMessage() . "\n\nSQL::: ". $sql ."\n\nPARAMS::: ". cs_global::debug_print($params));
		}
		
		return $retval;
	}
	//=========================================================================
}