<?php

class cs_sessionDB extends cs_session {

	protected $db;
	
	protected $logger = null;
	
	protected $logCategory = "DB Sessions";
	
	//-------------------------------------------------------------------------
	/**
	 * The constructor.
	 * 
	 * @param $createSession	(mixed,optional) determines if a session will be started or not; if
	 * 								this parameter is non-null and non-numeric, the value will be 
	 * 								used as the session name.
	 */
	function __construct() {
		
		
		//map some constants to connection parameters.
		//NOTE::: all constants should be prefixed...
		$constantPrefix = 'SESSION_DB_';
		$params = array('host', 'port', 'dbname', 'user', 'password');
		foreach($params as $name) {
			$value = null;
			$constantName = $constantPrefix . strtoupper($name);
			if(defined($constantName)) {
				$value = constant($constantName);
			}
			$dbParams[$name] = $value;
		}
		$this->db = new cs_phpDB(constant('DBTYPE'));
		$this->db->connect($dbParams);
		
		$this->tableName = 'cswal_session_store_table';
		$this->tablePKey = 'session_store_id';
		$this->sequenceName = 'cswal_session_store_table_session_store_id_seq';
		
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
	/**
	 * Determines if the appropriate table exists in the database.
	 */
	public function sessdb_table_exists() {
		try {
			$test = $this->db->run_query("SELECT * FROM ". $this->tableName .
					" ORDER BY ". $this->tablePKey ." LIMIT 1");
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
	private function load_table() {
		$filename = dirname(__FILE__) .'/schema/db_session_schema.'. $this->db->get_dbtype() .'.sql';
		if(file_exists($filename)) {
			try {
				$this->db->run_update(file_get_contents($filename),true);
			}
			catch(exception $e) {
				$this->exception_handler(__METHOD__ .": failed to load required table " .
						"into your database automatically::: ". $e->getMessage(), true);
			}
		}
		else {
			$this->exception_handler(__METHOD__ .": while attempting to load required " .
					"table into your database, discovered you have a missing schema " .
					"file (". $filename .")", true);
		}
	}//end load_table()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function is_valid_sid($sid) {
		$isValid = false;
		if(strlen($sid) >= 20) {
			try {
				$sql = "SELECT * FROM ". $this->tableName ." WHERE session_id='". 
						$sid ."'";
				$this->db->run_query($sql);
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
			$sql = "SELECT * FROM ". $this->tableName ." WHERE session_id='". 
				$sid ."'";
			$data = $this->db->run_query($sql);
			
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
	public function sessdb_write($sid, $data) {
		if(is_string($sid) && strlen($sid) >= 20) {
			$data = array(
				'session_data'	=> $data
			);
			$cleanString = array(
				'session_data'		=> 'sql',
				'uid'			=> 'numeric'
			);
			
			$afterSql = "";
			if($this->is_valid_sid($sid)) {
				$type = 'update';
				$sql = "UPDATE ". $this->tableName ." SET ";
				$afterSql = "WHERE session_id='". $sid ."'";
				$data['last_updated'] = 'NOW()';
				$secondArg = false;
			}
			else {
				$type = 'insert';
				$sql = "INSERT INTO ". $this->tableName ." ";
				$data['session_id'] = $sid;
				$secondArg = $this->sequenceName;
			}
			
			$sql .= $this->gfObj->string_from_array($data, $type, null, $cleanString) .' '. $afterSql;
			try {
				$funcName = 'run_'. $type;
				$res = $this->db->$funcName($sql, $secondArg);
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
			$sql = "DELETE FROM ". $this->tableName ." WHERE session_id='". $sid ."'";
			$numDeleted = $this->db->run_update($sql, true);
			
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
		
		$dateFormat = 'Y-m-d H:i:s';
		$strftimeFormat = '%Y-%m-%d %H:%M:%S';
		$nowTime = date($dateFormat);
		$excludeCurrent = true;
		if(defined('SESSION_MAX_TIME') || defined('SESSION_MAX_IDLE')) {
			$maxFreshness = null;
			if(defined('SESSION_MAX_TIME')) {
				$date = strtotime('- '. constant('SESSION_MAX_TIME'));
				$maxFreshness = "date_created < '". strftime($strftimeFormat, $date) ."'";
				$excludeCurrent=false;
			}
			if(defined('SESSION_MAX_IDLE')) {
				
				$date = strtotime('- '. constant('SESSION_MAX_IDLE'));
				$addThis = "last_updated < '". strftime($strftimeFormat, $date) ."'";
				$maxFreshness = $this->gfObj->create_list($maxFreshness, $addThis, ' OR ');
			}
		}
		elseif(is_null($maxLifetime) || !is_numeric($maxLifetime) || $maxLifetime <= 0) {
			//pull it from PHP's ini settings.
			$maxLifetime = ini_get("session.gc_maxlifetime");
			$interval = $maxLifetime .' seconds';
			
			$dt1 = strtotime($nowTime .' - '. $interval);
			$maxFreshness = "last_updated < '". date($dateFormat, $dt1) ."'";
		}
		
		
		
		try {
			//destroy old sessions, but don't complain if nothing is deleted.
			$sql = "DELETE FROM ". $this->tableName ." WHERE ". $maxFreshness;
			if(strlen($this->sid) && $excludeCurrent === false) {
				$sql .= " AND session_id != '". $this->sid ."'";
			}
			$numCleaned = $this->db->run_update($sql, true);
			
			#if($numCleaned > 0) {
			#	$this->do_log("cleaned (". $numCleaned .") old sessions, " .
			#			"excludeCurrent=(". $this->gfObj->interpret_bool($excludeCurrent) .")" .
			#			", maxFreshness=(". $maxFreshness .")", "debug");
			#}
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
			$newDB = clone $this->db;
			$newDB->reconnect(true);
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
