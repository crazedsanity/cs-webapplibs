<?php

class cs_sessionDB extends cs_session {

	protected $db;
	
	protected $logger = null;
	
	protected $logCategory = "DB Sessions";
	
	const tableName = 'cswal_session_table';
	const tablePKey = 'session_id';
	
	//-------------------------------------------------------------------------
	/**
	 * The constructor.
	 * 
	 * @param $createSession	(mixed,optional) determines if a session will be started or not; if
	 * 								this parameter is non-null and non-numeric, the value will be 
	 * 								used as the session name.
	 */
	public function __construct() {
		
		$this->db = $this->connectDb();
		
		//create a logger (this will automatically cause any upgrades to happen).
		$this->logger = new cs_webdblogger($this->db, 'Session DB', true);
		
		//now tell PHP to use this class's methods for saving the session.
		session_set_save_handler(
			array(&$this, 'sessdb_open'),
			array(&$this, 'sessdb_close'),
			array(&$this, 'sessdb_read'),
			array(&$this, 'sessdb_write'),
			array(&$this, 'sessdb_destroy'),
			array(&$this, 'sessdb_gc')
		);
		
		
		parent::__construct(true);
		
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function connectDb($dsn=null,$user=null,$password=null) {
		
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
		
		$db = new cs_phpDB($dsn, $user, $pass);
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
			$data = $this->db->run_query($sql, array('sid'=>$sid));
			
			if($this->db->numRows() == 1) {
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
	protected function doInsert($sid, $data) {
		if(is_array($data)) {
			$data = serialize($data);
		}
		$sql = 'INSERT INTO '. self::tableName .' (session_id, session_data) VALUES (:sid, :data)';
		
		$this->db->run_query($sql, array('sid'=>$sid, 'data'=>$data));
		
		return($sid);
	}//end doInsert()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function doUpdate($sid, $data) {
		$sql = 'UPDATE '. self::tableName .' SET'.
			' session_data=:data'.
			', last_updated=NOW()'.
			' WHERE session_id=:sid';
		$updateFields = array(
			'data'			=> $data,
			'session_id'	=> $sid
		);
		
		$retval = $this->db->run_update($sql, $updateFields);
		
		return($retval);
	}//end doUpdate()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function updateUid($uid, $sid) {
		$sql = 'UPDATE '. self::tableName .' SET uid=:uid WHERE sid=:sid';
		
		$params = array(
			'uid'	=> $uid,
			'sid'	=> $sid
		);
		
		$retval = $this->db->run_query($sql, $params);
		
		return($retval);
	}//end updateUid()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function updateIp($ip) {
		$sql = 'UPDATE '. self::tableName .' SET ip=:ip WHERE sid=:sid';
		
		$params = array(
			'ip'	=> $ip,
			'sid'	=> $sid
		);
		
		$retval = $this->db->run_query($sql, $params);
		
		return($retval);
	}//end updateIp()
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
			$sql = "DELETE FROM ". self::tableName ." WHERE session_id=:sid";
			$params = array('sid'=>$sid);
			$numDeleted = $this->db->run_update($sql, $params);
			
			if($numDeleted > 0) {
				$this->do_log("Destroyed session_id (". $sid .")", 'deleted');
			}
		}
		catch(exception $e) {
			//do... nothing?
		}
		return(true);
	}//end sessdb_destroy()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Define maximum lifetime (in seconds) to store sessions in the database. 
	 * Anything that is older than that time will be purged (gc='garbage collector').
	 */
	public function sessdb_gc($maxLifetime=null) {
		
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
				$sql .= " AND session_id != '". $this->sid ."'";
			}
			$this->db->run_update($sql, $params);
		}
		catch(exception $e) {
			$this->exception_handler(__METHOD__ .": exception while cleaning: ". $e->getMessage());
		}
		
		return(true);
		
	}//end sessdb_gc()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function do_log($message, $type) {
		
		//check if the logger object has been created.
		if(!is_object($this->logger)) {
			$newDB = $this->connectDb();
			$this->logger = new cs_webdblogger($newDB, $this->logCategory);
		}
		
		return($this->logger->log_by_class("SID=(". $this->sid .") -- ". $message,$type));
		
	}//end do_log()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function exception_handler($message, $throwException=false) {
		$logId = $this->do_log($message, 'exception in code');
		if($throwException === true) {
			//in this class, it is mostly useless to throw exceptions, so by default they're not thrown.
			throw new exception($message);
		}
		return($logId);
	}//end exception_handler()
	//-------------------------------------------------------------------------


}//end cs_session{}
?>
