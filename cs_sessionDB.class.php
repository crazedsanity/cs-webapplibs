<?php

class cs_sessionDB extends cs_session {
	/** Database object */
	protected $db;
	
	/** Database-logging object */
	protected $logger = null;
	
	/** Category for the logger */
	protected $logCategory = "DB Sessions";
	
	/** table name in database */
	const tableName = 'cswal_session_table';
	
	/** Name of primary key column in database */
	const tablePKey = 'session_id';
	
	/** static value to test if it's been initialized, to help with optimization */
	static $initialized = false;
	
	/** Helps with file-based logging: will differentiate between separate page calls */
	static $runDate;
	
	/** Filesystem Object for handling file-based logging */
	protected $fsObj = null;
	
	/** stored value of the last exception encountered */
	public $lastException = null;
	
	//-------------------------------------------------------------------------
	/**
	 * The constructor.
	 * 
	 * @param $createSession	(mixed,optional) determines if a session will be started or not; if
	 * 								this parameter is non-null and non-numeric, the value will be 
	 * 								used as the session name.
	 */
	public function __construct($automaticUpgrades=true, cs_phpDB $db=null) {
		
		self::$runDate = microtime();
		
		
		$this->db = $db;
		
		if($automaticUpgrades === true) {
			$x = new cs_webdbupgrade(dirname(__FILE__) .'/VERSION', dirname(__FILE__) .'/upgrades/upgrade.ini', $this->db);
			$x->check_versions();
		}
		
		//create a logger (this will automatically cause any upgrades to happen).
		$this->logger = new cs_webdblogger($this->db, 'Session DB', false);
		
		$createSession = true;
		if(self::$initialized == true) {
			$createSession = false;
		}
		
		if(self::$initialized !== true) {
			//now tell PHP to use this class's methods for saving the session.
			$setRes = session_set_save_handler(
				array($this, 'sessdb_open'),
				array($this, 'sessdb_close'),
				array($this, 'sessdb_read'),
				array($this, 'sessdb_write'),
				array($this, 'sessdb_destroy'),
				array($this, 'sessdb_gc')
			);

			if($setRes == TRUE) {
				self::$initialized = true;
			}
			else {
				$gf = new cs_globalFunctions;
				$id = session_id();
				cs_debug_backtrace(1);
				$this->exception_handler(__METHOD__ .": failed to set session save handler, session_id=(". $id ."), result=(". strip_tags($gf->debug_var_dump($setRes,0)) .')', true);
			}
		}
		
		// the following prevents unexpected effects when using objects as save handlers
		register_shutdown_function('session_write_close');
		$this->gfObj = new cs_globalFunctions;
		
		try {
			parent::__construct($createSession);
		}
		catch(Exception $e) {
			$this->flogger($e->getMessage());
		}
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * @codeCoverageIgnore
	 */
	protected function connectDb($dsn=null,$user=null,$password=null) {
		if(isset($this->db)) {
			$db = $this->db;
		}
		else {
			if(is_null($dsn)) {
				if(defined('SESSION_DB_DSN')) {
					$dsn = constant('SESSION_DB_DSN');
				}
				else {
					throw new exception(__METHOD__ .": missing DSN setting");
				}
			}

			if(is_null($user)) {
				if(defined('SESSION_DB_USER')) {
					$user = constant('SESSION_DB_USER');
				}
				else {
					throw new exception(__METHOD__ .": missing user setting");
				}
			}

			if(is_null($password)) {
				if(defined('SESSION_DB_PASSWORD')) {
					$pass = constant('SESSION_DB_PASSWORD');
				}
				else {
					throw new exception(__METHOD__ .": missing password setting");
				}
			}

			try {
				$db = new cs_phpDB($dsn, $user, $pass);
			}
			catch(Exception $e) {
				$this->exception_handler($e->getMessage());
			}
		}
		
		return($db);
	}//end connectDb()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Determines if the appropriate table exists in the database.
	 */
	public function sessdb_table_exists() {
		try {
			$this->db->run_query("SELECT * FROM ". self::tableName .
					" ORDER BY ". self::tablePKey ." LIMIT 1");
			$exists = true;
		}
		catch(exception $e) {
			$this->exception_handler(__METHOD__ .": exception while trying to detect table::: ". $e->getMessage());
			$exists = false;
		}
		
		return($exists);
	}//end sessdb_table_exists()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function is_valid_sid($sid) {
		$isValid = false;
		try {
			$sql = "SELECT * FROM " . self::tableName . " WHERE session_id=:sid";
			$this->db->run_query($sql, array('sid' => $sid));
			$numrows = $this->db->numRows();
			if ($numrows == 1) {
				$isValid = true;
			} elseif ($numrows > 0 || $numrows < 0) {
				$this->exception_handler(__METHOD__ . ": invalid numrows returned (" . $numrows . ")", true);
			}
		} catch (exception $e) {
			$this->exception_handler(__METHOD__ . ": invalid sid (" . $sid . ")");
		}
		
		return($isValid);
	}//end is_valid_sid()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Open the session (doesn't really do anything)
	 */
	public function sessdb_open($savePath=null, $sessionName=null) {
		return(true);
	}//end sessdb_open()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Close the session (call the "gc" method)
	 */
	public function sessdb_close() {
		return($this->sessdb_gc(null));
	}//end sessdb_close()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Read information about the session.  If there is no data, it MUST return 
	 * an empty string instead of NULL.
	 */
	public function sessdb_read($sid) {
		$retval = '';
		try {
			$sql = "SELECT * FROM ". self::tableName ." WHERE session_id=:sid";
			$numRows = $this->db->run_query($sql, array('sid'=>$sid));
			
			if($numRows == 1) {
				$data = $this->db->get_single_record();
				$retval = $data['session_data'];
			}
		}
		catch(exception $e) {
			//no throwing exceptions...

			$this->exception_handler(__METHOD__ .": failed to read::: ". $e->getMessage());
		}
		return($retval);
	}//end sessdb_read()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function doInsert($sid, $data, $uid=null) {
		if(is_array($data)) {
			$data = serialize($data);
		}
		
		$sqlData = array(
			'session_id'	=> $sid,
			'session_data'	=> $data
		);
		if(!is_null($uid) && strlen($uid)) {
			$sqlData['uid'] = $uid;
		}
		elseif(!is_null($uid) && !strlen($uid)) {
			// no data in UID; it was specifically supplied empty, make it null in DB
			$sqlData['uid'] = null;
		}
		
		try {
			$sql = 'INSERT INTO '. self::tableName .' '. $this->create_sql_insert_string($sqlData);

			$rowsInserted = $this->db->run_query($sql, $sqlData);
			if($rowsInserted != 1) {
				throw new exception(__METHOD__ .": failed to insert data, rows inserted=(". $rowsInserted .")");
			}
		}
		catch(Exception $e) {
			$this->exception_handler($e->getMessage());
		}
		
		return($sid);
	}//end doInsert()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function doUpdate($sid, $data, $uid=null) {
		$retval = null;
		$updateFields = array(
			'session_data'	=> $data,
			'session_id'	=> $sid,
		);
		
		if(!is_null($uid) && strlen($uid)) {
			$updateFields['uid'] = $uid;
		}
		elseif(!is_null($uid) && !strlen($uid)) {
			// no data in UID; it was specifically supplied empty, make it null in DB
			$updateFields['uid'] = null;
		}
		
		if(is_null($data)) {
			// Avoids accidentally removing session data.
			unset($updateFields['session_data']);
		}
		
		$sql = 'UPDATE '. self::tableName .' SET num_checkins=(num_checkins+1), last_updated=NOW()';
		
		$addThis = $this->create_sql_update_string($updateFields, array('session_id' => 'varchar(40)'));
		if(strlen($addThis)) {
			$sql .= ', '. $addThis;
		}
		
		$sql .= ' WHERE session_id=:session_id';
		
		try {
			$retval = $this->db->run_update($sql, $updateFields);
		}
		catch(Exception $e) {
			$gf = new cs_globalFunctions;
			$message = $e->getMessage() . " --- SQL::: ". $sql . " -- PARAMETERS::: ". strip_tags($gf->debug_print($updateFields,0));
			$this->exception_handler($message);
			$retval = $message;
		}
		
		return($retval);
	}//end doUpdate()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function checkin($sid) {
		return($this->doUpdate($sid,null));
	}//end checkin()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function logout($sid) {
		$retval = -1;
		$sql = "UPDATE ". self::tableName ." SET uid=NULL, session_data=NULL WHERE 
				session_id=:session_id::varchar(40)";
		
		$updateFields = array('session_id' => $sid);
		
		try {
			$retval = $this->db->run_update($sql, $updateFields);
		}
		catch(Exception $e) {
			$this->exception_handler($e->getMessage());
		}
		
		return($retval);
	}//end logout()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function updateUid($uid, $sid) {
		$sql = 'UPDATE '. self::tableName .' SET uid=:uid WHERE session_id=:session_id';
		
		$params = array(
			'uid'			=> $uid,
			'session_id'	=> $sid
		);
		
		try {
			$retval = $this->db->run_query($sql, $params);
		}
		catch(Exception $e) {
			$this->exception_handler($e->getMessage());
		}
		
		return($retval);
	}//end updateUid()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function sessdb_write($sid, $data) {
		if(is_string($sid) && strlen($sid) >= 20) {
			$type = "insert";
			try {
				if($this->is_valid_sid($sid)) {
					$type = "update";
					$res = $this->doUpdate($sid, $data);
				}
				else {
					$type = "insert";
					$res = $this->doInsert($sid, $data);
				}
				#$this->do_log(__METHOD__ .": finished (". $type ."), DATA::: ". $this->gfObj->debug_print($data,0), 'debug');
			}
			catch(exception $e) {
				$this->exception_handler(__METHOD__ .": failed to perform action (". $type ."), sid=(". $sid ."), sid length=(". strlen($sid) ."), validSid=(". $this->is_valid_sid($sid) .")::: ". $e->getMessage());
			}
		}
		else {
			$this->exception_handler(__METHOD__ .": invalid sid (". $sid ."), DATA::: ". $this->gfObj->debug_print($data,0));
		}
		
		return(true);
	}//end sessdb_write()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function sessdb_destroy($sid, $uid=null) {
		try {
			$logUid = null;
			if (is_null($uid)) {
				$num = $this->db->run_query("SELECT * FROM " . self::tableName . " WHERE session_id=:id", array('id' => $sid));
				if ($num > 0) {
					$record = $this->db->get_single_record();
					$logUid = $record['uid'];
				}
			}
			$sql = "DELETE FROM " . self::tableName . " WHERE session_id=:session_id";
			$params = array('session_id' => $sid);
			$this->db->run_update($sql, $params);
			$this->do_log("Destroyed session_id (" . $sid . ")", 'deleted', $logUid);
		} catch(exception $e) {
			//do... nothing?
			$this->exception_handler($e->getMessage());
		}
		return(true);
	}//end sessdb_destroy()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Define maximum lifetime (in seconds) to store sessions in the database. 
	 * Anything that is older than that time will be purged (gc='garbage collector').
	 * 
	 * @return int
	 */
	public function sessdb_gc() {
		$condition = "NOW() - interval '". ini_get('session.gc_maxlifetime') ." seconds'";
		if (defined('SESSION_MAX_IDLE')) {
			$idle = constant('SESSION_MAX_IDLE');
			if(is_numeric($idle)) {
				$condition = "NOW() - interval '". $idle ." seconds'";
			}
			else {
				$condition = "NOW() - interval '". $idle ."'";
			}
		}
		
		//retrieve all the expired sessions so their expiration can be logged appropriately.
		$sql = "SELECT * FROM ". self::tableName ." WHERE last_updated < ". $condition;
		
		try {
			$numRows = $this->db->run_query($sql);
			$retval = 0;
			
			if($numRows > 0) {
				$sessionsToExpire = $this->db->farray_fieldnames('session_id');
				
				foreach($sessionsToExpire as $id=>$data) {
					$this->do_log("Expiring session, condition=(". $condition ."), UID=(". $data['uid'] ."), date_created=(". $data['date_created'] ."), last_updated=(". $data['last_updated'] ."), DATA::: ". $data['session_data'], $data['uid']);
					$this->sessdb_destroy($id);
					$retval++;
				}
			}
		} catch (Exception $ex) {
			$this->exception_handler(__METHOD__ .": exception while retrieving records for cleaning: ". $ex->getMessage() ."\nSQL: ". $sql);
		}
		
		return($retval);
		
	}//end sessdb_gc()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * 
	 * @param type $message
	 * @param type $type
	 * @return type
	 */
	protected function do_log($message, $type, $uid=null) {
		$retval = null;
		try {
			//check if the logger object has been created.
			if(!is_object($this->logger)) {
				$newDB = $this->connectDb();
				$this->logger = new cs_webdblogger($newDB, $this->logCategory);
			}
			$retval = $this->logger->log_by_class("SID=(". $this->sid .") -- ". $message,$type, $uid);
		}
		catch(Exception $e) {
			$this->flogger($e->getMessage());
		}
		
		return($retval);
		
	}//end do_log()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function exception_handler($message, $throwException=false) {
		$logId = null;
		try {
			$this->lastException = $message;
			if(function_exists('cs_debug_backtrace')) {
				$message .= "\n\nBACKTRACE:::\n". cs_debug_backtrace(0);
			}
			$this->flogger($message);
			$logId = $this->do_log($message, 'exception in code');
			if($throwException === true) {
				//in this class, it is mostly useless to throw exceptions, so by default they're not thrown.
				throw new exception($message);
			}
		}
		catch(Exception $e) {
			$this->flogger($e->getMessage());
		}
		return($logId);
	}//end exception_handler()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * @codeCoverageIgnore
	 */
	protected function flogger($msg) {
		$msg = strip_tags($msg);
		if(!is_object($this->fsObj)) {
			$this->fsObj = new cs_fileSystem(constant('RWDIR'));
			$this->fsObj->openFile('sessionDebug.log', 'a');
		}
		
		try {
			
			$fullBt = debug_backtrace();
			$bt = $fullBt[1];
			
			@$method = $bt['method'];
			$class = $bt['class'];
			$file = $bt['file'];
			if(!isset($bt['method'])) {
				@$method = $fullBt[0]['method'];
				$class = $fullBt[0]['class'];
				$file = $fullBt[0]['file'];
			}
			
			$msg = '['. cs_sessionDB::$runDate .'] '. cs_get_where_called() . " -- ". $msg;
			
			
			$msg .= "\n-----------------------\n". strip_tags(cs_debug_backtrace(0));
			$msg .= "\n====================================\n";
			
			$this->fsObj->append_to_file($msg);
		}
		catch(Exception $e) {
			//nothing can be done at this point... ugh.
		}
	}//end flogger()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function is_authenticated() {
		$retval = false;
		$parentRes = false;
		
		try {
			if(parent::is_authenticated()) {
				$parentRes = true;
				$retval = $this->is_valid_sid($this->sid);
			}
		}
		catch(Exception $e) {
			$this->exception_handler($e->getMessage());
		}
		
		return ($retval);
	}//end is_authenticated()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function create_sql_update_string(array $fields, array $castFields = null) {
		$retval = "";
		if(count($fields)) {
			foreach($fields as $k=>$v) {
				//$retval = $k .'=:'. $v;
				//handle casting to a certain type, e.g. :session_id::varchar(40)
				$k2 = $k;
				if(isset($castFields[$k])) {
					$k2 = $k .'::'. $castFields[$k];
				}
				$retval = $this->gfObj->create_list($retval, $k .'=:'. $k2, ', ');
			}
			$retval = " ". $retval;
		}
		else {
			throw new exception(__METHOD__ .": unable to create update string, no fields given");
		}
		
		return($retval);
	}//end create_sql_update_string()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function create_sql_insert_string(array $data) {
		#$retval = "";
		if(is_array($data) && count($data)) {
			$fields = "";
			$values = "";
			foreach($data as $k=>$v) {
				$fields = $this->gfObj->create_list($fields, $k, ", ");
				$values = $this->gfObj->create_list($values, ':'. $k, ", ");
			}
			if(strlen($fields) && strlen($values)) {
				$retval = ' ('. $fields .') VALUES ('. $values .')';
			}
			else {
				throw new exception(__METHOD__ .": no fields (". $fields .") or values (". $values .") created... ". $this->gfObj->debug_print($data,0));
			}
		}
		else {
			throw new Exception(__METHOD__ .": unable to create insert string, no fields given");
		}
		
		return($retval);
	}//end create_sql_insert_string()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_recently_active_sessions($seconds=600) {
		if(is_null($seconds) || !is_numeric($seconds)) {
			$seconds = 10;
		}
		$sql = "SELECT session_id, uid, date_created, last_updated, num_checkins
			FROM cswal_session_table WHERE last_updated > NOW() - interval '". $seconds
				." seconds'";
		try {
			$retval = $this->db->run_query($sql);
		}
		catch(Exception $ex) {
			throw new exception($ex);
		}
		
		return($retval);
	}//end get_recently_active_sessions()
	//-------------------------------------------------------------------------


}//end cs_session{}
