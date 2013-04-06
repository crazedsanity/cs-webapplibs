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
	
	//-------------------------------------------------------------------------
	/**
	 * The constructor.
	 * 
	 * @param $createSession	(mixed,optional) determines if a session will be started or not; if
	 * 								this parameter is non-null and non-numeric, the value will be 
	 * 								used as the session name.
	 */
	public function __construct() {
		
		date_default_timezone_set('America/Chicago');
		cs_sessionDB::$runDate = microtime();
		

		$this->db = $this->connectDb();
		
		$x = new cs_webdbupgrade(dirname(__FILE__) .'/VERSION', dirname(__FILE__) .'/upgrades/upgrade.xml', $this->db);
		$x->check_versions();
		
		//create a logger (this will automatically cause any upgrades to happen).
		$this->logger = new cs_webdblogger($this->db, 'Session DB', true);
		
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
				throw new exception(__METHOD__ .": failed to set session save handler, session_id=(". $id ."), result=(". strip_tags($gf->debug_var_dump($setRes,0)) .')');
			}
		}
		
		// the following prevents unexpected effects when using objects as save handlers
		register_shutdown_function('session_write_close');
		
		try {
			parent::__construct($createSession);
		}
		catch(Exception $e) {
			$this->flogger($e->getMessage());
		}
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function connectDb($dsn=null,$user=null,$password=null) {
$this->flogger("started...");
		
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
$this->flogger("Connected database");
		}
		catch(Exception $e) {
			$this->exception_handler($e->getMessage());
		}
		
		return($db);
	}//end connectDb()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Determines if the appropriate table exists in the database.
	 */
	public function sessdb_table_exists() {
$this->flogger("started...");
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
$this->flogger("started...");
		$isValid = false;
		if(strlen($sid) >= 20) {
			try {
				$sql = "SELECT * FROM ". self::tableName ." WHERE session_id=:sid";
				$this->db->run_query($sql, array('sid'=>$sid));
				$numrows = $this->db->numRows();
				if($numrows == 1) {
					$isValid = true;
				}
				elseif($numrows > 0 || $numrows < 0) {
					$this->exception_handler(__METHOD__ .": invalid numrows returned (". $numrows .")",true);
				}
			}
			catch(exception $e) {
				$this->exception_handler(__METHOD__ .": invalid sid (". $sid .")");
			}
		}
		
		return($isValid);
	}//end is_valid_sid()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Open the session (doesn't really do anything)
	 */
	public function sessdb_open($savePath, $sessionName) {
		return(true);
	}//end sessdb_open()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Close the session (call the "gc" method)
	 */
	public function sessdb_close() {
$this->flogger("started...");
		#$this->sessdb_write(session_id(), serialize($_SESSION));
		return($this->sessdb_gc(0));
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
$this->flogger("Got a record... DATA::: ". $this->gfObj->debug_print($data,0));
				$retval = $data['session_data'];
			}
			else {
$this->flogger("no records found for sid=(". $sid .")");
			}
		}
		catch(exception $e) {
			//no throwing exceptions...

			$this->exception_handler(__METHOD__ .": failed to read::: ". $e->getMessage());
		}
$this->flogger("done, retval=(". $retval .")");
		return($retval);
	}//end sessdb_read()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function doInsert($sid, $data, $uid=null) {
$this->flogger("started...");
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

			$this->db->run_query($sql, $sqlData);
		}
		catch(Exception $e) {
			$this->exception_handler($e->getMessage());
		}
		
		return($sid);
	}//end doInsert()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function doUpdate($sid, $data=null, $uid=null) {
$this->flogger("started...");
		$updateFields = array(
			'session_data'	=> $data,
			'session_id'	=> $sid
		);
		if(!is_null($uid) && strlen($uid)) {
			$updateFields['uid'] = $uid;
		}
		elseif(!is_null($uid) && !strlen($uid)) {
			// no data in UID; it was specifically supplied empty, make it null in DB
			$updateFields['uid'] = null;
		}
		
		if(is_null($data)) {
			unset($updateFields['session_data']);
		}
		
		$sql = 'UPDATE '. self::tableName .' SET'.
			$this->create_sql_update_string($updateFields) .
			' WHERE session_id=:session_id';
		
		try {
			$retval = $this->db->run_update($sql, $updateFields);
		}
		catch(Exception $e) {
			$this->exception_handler($e->getMessage());
		}
		
		return($retval);
	}//end doUpdate()
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
	public function sessdb_destroy($sid) {
		try {
			$sql = "DELETE FROM ". self::tableName ." WHERE session_id=:session_id";
			$params = array('session_id'=>$sid);
			$numDeleted = $this->db->run_update($sql, $params);
			
			if($numDeleted > 0) {
				$this->do_log("Destroyed session_id (". $sid .")", 'deleted');
			}
		}
		catch(exception $e) {
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
	 * TODO: make the usage of $maxLifetime make sense (or remove it)
	 */
	public function sessdb_gc($maxLifetime=null) {
$this->flogger("started...");
		$retval = -1;
		$dateFormat = 'Y-m-d H:M:S';
		$strftimeFormat = '%Y-%m-%d %H:%M:%S';
		$nowTime = date($dateFormat);
		$excludeCurrent = true;
		$params = array();
		
		if(defined('SESSION_MAX_TIME') || defined('SESSION_MAX_IDLE')) {
			$maxFreshness = null;
			if(defined('SESSION_MAX_TIME')) {
				//date_created < '2012-12-01 22:01:45''
				$date = strtotime('- '. constant('SESSION_MAX_TIME'));
				$params ['dateCreated'] = strftime($strftimeFormat, $date);
				$maxFreshness = "date_created < :dateCreated";	//". strftime($strftimeFormat, $date) ."'";
				$excludeCurrent=false;
			}
			if(defined('SESSION_MAX_IDLE')) {
				
				$date = strtotime('- '. constant('SESSION_MAX_IDLE'));
				$params['lastUpdated'] = strftime($strftimeFormat, $date);
				
				$addThis = "last_updated < :lastUpdated";	//'". strftime($strftimeFormat, $date) ."'";
				
				$maxFreshness = $this->gfObj->create_list($maxFreshness, $addThis, ' OR ');
			}
		}
		elseif(is_null($maxLifetime) || !is_numeric($maxLifetime) || $maxLifetime <= 0) {
			//pull it from PHP's ini settings.
			$maxLifetime = ini_get("session.gc_maxlifetime");
			$interval = $maxLifetime .' seconds';
			
			$dt1 = strtotime($nowTime .' - '. $interval);
			$params['lastUpdated'] = date($dateFormat, $dt1);
			$maxFreshness = "last_updated < :lastUpdated";//'". date($dateFormat, $dt1) ."'";
		}
		
		
		
		try {
			//destroy old sessions, but don't complain if nothing is deleted.
			$sql = "DELETE FROM ". self::tableName ." WHERE ". $maxFreshness;
			if(strlen($this->sid) && $excludeCurrent === false) {
				$params['session_id'] = $this->sid;
				$sql .= " AND session_id != :session_id";
			}
			$retval = $this->db->run_update($sql, $params);
		}
		catch(exception $e) {
			$this->exception_handler(__METHOD__ .": exception while cleaning: ". $e->getMessage());
		}
		
		return($retval);
		
	}//end sessdb_gc()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function do_log($message, $type) {
$this->flogger("started...");
		
		try {
			//check if the logger object has been created.
			if(!is_object($this->logger)) {
				$newDB = $this->connectDb();
				$this->logger = new cs_webdblogger($newDB, $this->logCategory);
			}
			$retval = $this->logger->log_by_class("SID=(". $this->sid .") -- ". $message,$type);
		}
		catch(Exception $e) {
			$this->flogger($e->getMessage());
		}
		
		return($retval);
		
	}//end do_log()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function exception_handler($message, $throwException=false) {
$this->flogger($message);
		try {
			$message .= "\n\nBACKTRACE:::\n". cs_debug_backtrace(0);
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
	protected function flogger($msg) {
		$msg = strip_tags($msg);
		if(!is_object($this->fsObj)) {
			$this->fsObj = new cs_fileSystem(constant('RWDIR'));
			$this->fsObj->openFile('sessionDebug.log', 'a');
		}
		
		try {
			
			$fullBt = debug_backtrace();
			$bt = $fullBt[1];
			
			$method = $bt['method'];
			$class = $bt['class'];
			$file = $bt['file'];
			if(!isset($bt['method'])) {
				$method = $fullBt[0]['method'];
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
$this->flogger("started...");
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
	public function create_sql_update_string(array $fields) {
		$retval = "";
		if(count($fields)) {
			foreach($fields as $k=>$v) {
				//$retval = $k .'=:'. $v;
				$retval = $this->gfObj->create_list($retval, $k .'=:'. $k, ', ');
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


}//end cs_session{}
