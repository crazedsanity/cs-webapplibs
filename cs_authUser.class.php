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
	public function __construct(cs_phpDB $db) {

		if(isset($db) && is_object($db)) {
			//make sure the session has been created.
			parent::__construct(self::COOKIE_NAME);



			$this->dbObj = $db;
			$x = new cs_webdbupgrade(dirname(__FILE__) . '/VERSION', dirname(__FILE__) .'/upgrades/upgrade.xml', $db);
			$x->check_versions(true);

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
		}
		else {
			throw new exception(__METHOD__ .": required database handle not passed");
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
		//check the database to see if the sid is valid. (TODO: join to auth table and ensure they're still enabled)
		$sql = "SELECT * FROM cswal_session_table WHERE session_id=:sid";
		$numrows = $this->dbObj->run_query($sql, array('sid'=>$this->sid));
		
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
			$sql = "SELECT * FROM cs_authentication_table WHERE username=:username " .
				"AND passwd=:password AND user_status_id=1";
			
			// NOTE::: in linux, do this:::: echo -e "username-password\c" | md5sum
			// (without the "\c" or the switch, the sum won't match)
			$sumThis = $username .'-'. $password;
			$params = array(
				'username'		=> $username,
				'password'		=> md5($sumThis)
			);
			$numrows = $this->dbObj->run_query($sql, $params);
			$retval = $numrows;
			
			if($numrows == 1) {
				$data = $this->dbObj->get_single_record();
				$this->userInfo = $data;
				$this->update_auth_data($this->userInfo);
				$insertData = array(
					'sid'	=> $this->sid,
					'uid'	=> $data['uid']
				);
				
				$sql = 'INSERT INTO cswal_session_table (session_id, date_created, uid, last_updated) '.
					' VALUES (:sid, NOW(), :uid, NOW())';
				$retval = $this->dbObj->run_query($sql, $insertData);
				
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
			$sql = "SELECT * FROM cs_authentication_table WHERE uid=:uid" 
					." AND user_status_id=1";
			$numrows = $this->dbObj->run_query($sql, array('uid'=> $uid));
			
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
			$sql = "DELETE FROM cswal_session_table WHERE session_id=:sid";
			$retval = $this->dbObj->run_query($sql, array('sid'=>$this->sid));
			$dropCookieRes = $this->drop_cookie(self::COOKIE_NAME);
			$this->do_log("Logged-out user with result (". $retval ."), removed cookie (". self::COOKIE_NAME .") with result (". $dropCookieRes .")", 'debug');
		}
		$this->check_sid(FALSE);
		
		return($retval);
	}//end logout_sid()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function checkin() {
		$retval = NULL;
		if($this->is_authenticated()) {
			$sql = "UPDATE cswal_session_table SET last_updated=NOW(), " .
				"num_checkins=num_checkins+1 WHERE session_id=:sid;";
			$params = array(
				'sid'			=> $this->sid
			);
			$retval = $this->dbObj->run_query($sql, $params);
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
		$sql = "DELETE FROM cswal_session_table WHERE last_updated  < (NOW() - interval ':maxIdle')";
		$numrows = $this->dbObj->run_query($sql, array($maxIdle));
		
		if($numrows < 0 || !is_numeric($numrows)) {
			$details = __METHOD__ .": invalid numrows (". $numrows .")";
			$this->do_log($details, 'exception in code');
			throw new exception($details);
		}
		
		return($numrows);
	}//end logout_inactive_sessions()
	//-------------------------------------------------------------------------
}//end authUser{}

