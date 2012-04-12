<?php

class cs_authUser extends cs_session {
	
	/** Database connection object */
	protected $dbObj;
	
	/** cs_globalFunctions class. */
	protected $gfObj;
	
	/** Information about the logged-in user. */
	protected $userInfo=array();
	
	/** Name of cookie that will be set... */
	const COOKIE_NAME='CS_SESSID';
	
	/** Cached data from check_sid() */
	protected $isAuthenticated=NULL;
	
	//-------------------------------------------------------------------------
	public function __construct() {

		//make sure the session has been created.
		parent::__construct(self::COOKIE_NAME);
		
		$parameters = array(
			'host'		=> $GLOBALS['DB_PG_HOST'],
			'dbname'	=> $GLOBALS['DB_PG_DBNAME'],
			'port'		=> $GLOBALS['DB_PG_PORT'],
			'user'		=> $GLOBALS['DB_PG_DBUSER'],
			'password'	=> $GLOBALS['DB_PG_DBPASS'],
		);
		
		$this->dbObj = new cs_phpDB('pgsql');
		$this->dbObj->connect($parameters);
		
		$this->gfObj = new cs_globalFunctions;
		$this->logger = new cs_webdblogger($this->dbObj, "Auth", false);
		if($this->is_authenticated()) {
			$this->userInfo = $_SESSION['auth']['userInfo'];
			if(!isset($_SESSION['uid']) || $_SESSION['uid'] != $_SESSION['auth']['userInfo']['uid']) {
				$_SESSION['uid'] = $_SESSION['auth']['userInfo']['uid'];
			}
		}
		else {
			unset($_SESSION['auth']['userInfo']);
		}
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function do_log($details, $type=NULL) {
		if(is_null($type) || strlen($type) < 3) {
			$type = "info";
		}
		try {
			$this->logger->log_by_class($details, $type);
		}
		catch(Exception $ex) {
			throw new exception(__METHOD__ .": failed to create log::: ". $ex->getMessage());
		}
	}//end do_log()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function is_authenticated() {
		return($this->check_sid());
	}//end is_authenticated()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function check_sid() {
		//check the database to see if the sid is valid.
		$sql = "SELECT * FROM cswal_session_table WHERE session_id='". $this->sid ."'";
		$numrows = $this->run_sql($sql);
		
		$retval = false;
		if($numrows == 1) {
			$retval = true;
		}
		$this->isAuthenticated = $retval;
		
		if($retval && !strlen($_SESSION['auth']['userInfo']['username'])) {
			//something broke, and username isn't set.
			if(is_numeric($_SESSION['auto']['userInfo']['uid']) && $_SESSION['auto']['userInfo']['uid'] > 0) {
				$this->do_log("Username not set, pulling from the database...", 'error');
				$this->update_auth_data($this->get_user_data($_SESSION['auto']['userInfo']['uid']));
			}
			else {
				//something went wrong, log 'em out.
				$this->do_log("Username not set, couldn't find UID in session, so logging them out...", 'error');
				$this->logout_sid();
				$retval = false;
				$this->isAuthenticated = $retval;
			}
		}
		#if($retval) {
			$this->do_cookie();
		#}
		
		return($retval);
	}//end check_sid()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function login($username, $password) {
		if($this->is_authenticated()) {
			$this->do_log("User (". $username .") is already logged in, can't login again", 'error');
			$retval = false;
		}
		else {
			$sql = "SELECT * FROM cs_authentication_table WHERE username='". $username ."' " .
				"AND passwd='". md5($username .'-'. $password) ."' AND user_status_id=1";
			
			$numrows = $this->run_sql($sql);
			$retval = $numrows;
			
			if($numrows == 1) {
				$data = $this->dbObj->farray_fieldnames();
				$this->userInfo = $data;
				$this->update_auth_data($this->userInfo);
				$insertData = array(
					'session_id'	=> $this->sid,
					'date_created'	=> "NOW()",
					'uid'			=> $data['uid'],
					'ip'			=> $_SERVER['REMOTE_ADDR']
				);
				
				$sql = 'INSERT INTO cswal_session_table '. $this->gfObj->string_from_array($insertData, 'insert', NULL, 'sql');
				$retval = $this->run_sql($sql);
				
				$this->run_sql("UPDATE cs_authentication_table SET last_login='NOW()' WHERE uid=". $data['uid']);
				$this->do_log("Successfully logged-in (". $retval .")");
			}
			
			if($retval == 1) {
				$this->do_cookie();
			}
			$retval = $this->check_sid();
			$this->do_log("Return value from check_sid() was (". $retval .")", 'debug');
		}
		
		return($retval);
	}//end login()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function do_cookie() {
		$cookieExpiration = '120 days';
		if(defined('SESSION_MAX_TIME')) {
			$cookieExpiration = constant('SESSION_MAX_TIME');
		}
		$createCookieRes = $this->create_cookie(self::COOKIE_NAME, $this->sid, $cookieExpiration, '/', '.crazedsanity.com');
		#$this->do_log("Result of creating cookie: ". $createCookieRes, 'debug');
		return($createCookieRes);
	}//end do_cookie()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function get_user_data($uid) {
		if(is_numeric($uid) && $uid > 0) {
			$sql = "SELECT * FROM cs_authentication_table WHERE uid=". $uid 
					." AND user_status_id=1";
			$numrows = $this->run_sql($sql);
			
			if($numrows == 1) {
				$retval = $this->dbObj->farray_fieldnames();
			}
			else {
				//
				$details = __METHOD__ .": failed to retrieve a single user (". $numrows .") for uid=(". $uid .")";
				$this->do_log($details, 'exception in code');
				throw new exception($details);
			}
		}
		else {
			$details = __METHOD__ .": invalid uid (". $uid .")";
			$this->do_log($details, 'exception in code');
			throw new exception($details);
		}
		
		return($retval);
	}//end get_user_data()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function update_auth_data(array $data) {
		$_SESSION['auth']['userInfo'] = $data;
	}//end update_auth_data()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function logout_sid() {
		$retval = false;
		if($this->isAuthenticated) {
			$sql = "DELETE FROM cswal_session_table WHERE session_id='". $this->sid ."'";
			$retval = $this->run_sql($sql);
			$dropCookieRes = $this->drop_cookie(self::COOKIE_NAME);
			$this->do_log("Logged-out user with result (". $retval ."), removed cookie (". self::COOKIE_NAME .") with result (". $dropCookieRes .")", 'debug');
		}
		$this->check_sid(FALSE);
		
		return($retval);
	}//end logout_sid()
	//-------------------------------------------------------------------------
	
	
	//-------------------------------------------------------------------------
	private function run_sql($sql) {
		$numrows = $this->dbObj->exec($sql);
		$dberror = $this->dbObj->errorMsg();
		
		if(strlen($dberror) || !is_numeric($numrows) || $numrows < 0) {
			throw new exception(__METHOD__ .": invalid numrows (". $numrows .") or database error: ". $dberror ."<BR>\nSQL: ". $sql);
		}
		else {
			$retval = $numrows;
		}
		
		return($retval);
	}//end run_sql()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function checkin() {
		$retval = NULL;
		if($this->is_authenticated()) {
			$sql = "UPDATE cswal_session_table SET last_updated='NOW()', " .
				"num_checkins=num_checkins+1 WHERE session_id='". $this->sid ."';";
			$retval = $this->run_sql($sql);
		}
		$this->logout_inactive_sessions();
		
		return($retval);
	}//end checkin()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_user_info($index) {
		$retval = NULL;
		if(isset($this->userInfo[$index])) {
			$retval = $this->userInfo[$index];
		}
		return($retval);
	}//end get_user_info()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	private function logout_inactive_sessions() {
		$maxIdle = '3 days';
		if(defined('SESSION_MAX_IDLE')) {
			$maxIdle = constant('SESSION_MAX_IDLE');
		}
		$sql = "DELETE FROM cswal_session_table WHERE last_updated  < (NOW() - interval '". $maxIdle ."')";
		$numrows = $this->run_sql($sql);
		
		if($numrows < 0 || !is_numeric($numrows)) {
			$details = __METHOD__ .": invalid numrows (". $numrows .")";
			$this->do_log($details, 'exception in code');
			throw new exception($details);
		}
		
		return($numrows);
	}//end logout_inactive_sessions()
	//-------------------------------------------------------------------------
}//end authUser{}

