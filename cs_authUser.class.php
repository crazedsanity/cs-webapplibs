<?php

class cs_authUser extends cs_sessionDB {
	
	/** Database connection object */
	protected $db;
	
	/** cs_globalFunctions class. */
	protected $gfObj;
	
	/** Information about the logged-in user. */
	protected $userInfo=array();
	
	/** Name of cookie that will be set... */
	const COOKIE_NAME='CS_SESSID';
	
	/** Cached data from check_sid() */
	protected $isAuthenticated=NULL;
	
	/** Table name */
	protected $table = 'cs_authentication_table';
	
	/** Sequence name */
	protected $seq = 'cs_authentication_table_uid_seq';
	
	/** */
	public $separator = '-';
	
	//these MUST match the database!
	const STATUS_DISABLED	= 0;
	const STATUS_ENABLED	= 1;
	const STATUS_PENDING	= 2;
	
	//types of authentication (matches expected hash length)
	const HASH_MD5			= 32;
	const HASH_SHA1			= 40;
	const HASH_SHA256		= 64;
	const HASH_SHA512		= 128;
	const HASH_PHPDEFAULT	= PASSWORD_DEFAULT;
	const HASH_PHPBCRYPT	= PASSWORD_BCRYPT;
	
	//-------------------------------------------------------------------------
	/**
	 * @codeCoverageIgnore
	 */
	public function __construct(cs_phpDB $db, $automaticUpgrade=false, $separator='-') {
		
		$this->separator = $separator;

		if(isset($db) && is_object($db)) {
			//make sure the session has been created.
			$this->db = $db;
			parent::__construct(false, $db);
			
			if($automaticUpgrade === true) {
				$this->check_for_upgrades();
			}
			
			$this->gfObj = new cs_globalFunctions;
			$this->logger = new cs_webdblogger($this->db, "Auth", false);
			if($this->is_authenticated()) {
				$this->userInfo = $_SESSION['auth']['userInfo'];
				if(!isset($_SESSION['uid']) || $_SESSION['uid'] != $_SESSION['auth']['userInfo']['uid']) {
					$_SESSION['uid'] = $_SESSION['auth']['userInfo']['uid'];
				}
			}
			else {
				if(isset($_SESSION) && isset($_SESSION['auth']) && isset($_SESSION['auth']['userInfo'])) {
					unset($_SESSION['auth']['userInfo']);
				}
			}
		}
		else {
			throw new exception(__METHOD__ .": required database handle not passed");
		}
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * @codeCoverageIgnore
	 */
	protected function check_for_upgrades() {
		$x = new cs_webdbupgrade(
				dirname(__FILE__) . '/VERSION', 
				dirname(__FILE__) . '/upgrades/upgrade.ini', 
				$this->db
		);
		$x->check_versions(true);
	}
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * @codeCoverageIgnore
	 */
	protected function do_log($details, $type=NULL, $uid=null) {
		if(is_null($type) || strlen($type) < 3) {
			$type = "info";
		}
		try {
			$this->logger->log_by_class($details, $type, $uid);
		}
		catch(Exception $ex) {
			throw new exception(__METHOD__ .": failed to create log::: ". $ex->getMessage());
		}
	}//end do_log()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function is_authenticated() {
		$retval = parent::is_authenticated();
		return($retval);
	}//end is_authenticated()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function check_sid() {
		$retval = parent::is_authenticated();
		return($retval);
	}//end check_sid()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function login($username, $password) {
		$retval = 0;
		try {
			$sql = "SELECT uid, username, user_status_id, date_created, last_login, 
					email, passwd, user_status_id FROM cs_authentication_table WHERE 
					username=:username AND user_status_id=1";

			// NOTE::: in linux, do this:::: echo -e "username-password\c" | sha1sum
			// (without the "\c" or the switch, the sum won't match)
			$sumThis = array('username' => $username, 'passwd' => $password);
			$params = array(
				'username' => $username
			);
			$numRecords = $this->db->run_query($sql, $params);
		} catch (Exception $e) {
			$this->do_log(__METHOD__ . ": Exception encountered::: ");
			throw new exception(__METHOD__ . ": DETAILS::: " . $e->getMessage());
		}
		try {
			if ($numRecords == 1) {
				
				$data = $this->db->get_single_record();
				
				$retval = password_verify($password, $data['passwd']);
				
				if ((bool) $retval) {
					$this->userInfo = $data;
					$this->update_auth_data($this->userInfo);
					
					/*
					 * NOTE::: this assumes that there's already a record in the 
					 * session table... this would probably need to be revisited 
					 * in the event that authentication is implemented without 
					 * database storage for sessions.
					 */
					$updateRes = $this->updateUid($data['uid'], $this->sid);
					if ($updateRes == 0) {
						$insertRes = parent::doInsert($this->sid, $_SESSION, $this->uid);
						$this->do_log(__METHOD__ . ": inserted new session record, updateRes=(" . $updateRes . "), insertRes=(" . $insertRes . ")", 'debug');
					}
					
					$this->do_log("Successfully logged-in (" . $retval . ")");
				}
			} else {
				$this->do_log("Authentication failure, unknown username (" . $username . ")");
			}
			
			if ($retval == 1) {
				$this->do_cookie();
			}
			$this->do_log("Return value from check_sid() was (" . $retval . ")", 'debug');
		} catch (Exception $e) {
			throw new exception(__METHOD__ . ": DETAILS: " . $e->getMessage());
		}
		
		return($retval);
	}//end login()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function update_passwd($uid, $newPass) {
		$retval = false;
		if(is_numeric($uid)) {
			$params = array(
				'passwd'	=> password_hash($newPass, PASSWORD_DEFAULT),
			);
			$retval = $this->update_user_info($params, $uid);
		}
		else {
			throw new InvalidArgumentException(__METHOD__ .": invalid ID or password");
		}
		
		return $retval;
	}//end update_passwd
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * @codeCoverageIgnore
	 */
	protected function do_cookie() {
		if(defined('UNITTEST_ACTIVE')) {
			$createCookieRes = false;
		}
		else {
			$cookieExpiration = '120 days';
			if(defined('SESSION_MAX_TIME')) {
				$cookieExpiration = constant('SESSION_MAX_TIME');
			}
			$createCookieRes = $this->create_cookie(self::COOKIE_NAME, $this->sid, $cookieExpiration, '/', '.crazedsanity.com');
			#$this->do_log("Result of creating cookie: ". $createCookieRes, 'debug');
		}
		return($createCookieRes);
	}//end do_cookie()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_user_data($uidOrUsername, $onlyUserStatus=self::STATUS_ENABLED) {
		$retval = null;
		if((is_numeric($uidOrUsername) && $uidOrUsername > 0) || (!is_numeric($uidOrUsername) && strlen($uidOrUsername) > 0)) {
			$condition = 'uid=:uid';
			if(!is_numeric($uidOrUsername)) {
				$condition = 'username=:uid';
			}
			$sql = "SELECT * FROM cs_authentication_table 
				WHERE ". $condition ." AND user_status_id=:status";
			$params = array(
						'uid'		=> $uidOrUsername,
						'status'	=> $onlyUserStatus,
					);
			$numrows = $this->db->run_query(
					$sql,
					$params
			);
			
			if($numrows == 1) {
				$retval = $this->db->get_single_record();
			}
			else {
				//
				$details = __METHOD__ .": failed to retrieve a single user (". $numrows .") for uid=(". $uidOrUsername .") with user_status_id=(". $onlyUserStatus .")";
				$this->do_log($details, 'exception in code');
				throw new exception($details);
			}
		}
		else {
			$details = __METHOD__ .": invalid uid (". $uidOrUsername .")";
			$this->do_log($details, 'exception in code');
			throw new exception($details);
		}
		
		return($retval);
	}//end get_user_data()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	protected function update_auth_data(array $data) {
		$_SESSION['auth']['userInfo'] = $data;
		$_SESSION['uid'] = $data['uid'];
	}//end update_auth_data()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function logout_sid() {
		$_SESSION = array();
		$retval = $this->logout($this->sid);
		return($retval);
	}//end logout_sid()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	//TODO: this should be in cs_sessionDB, not here!
	public function checkin($sid=null) {
		$retval = NULL;
		if($this->is_authenticated()) {
			$sql = "UPDATE cswal_session_table SET last_updated=NOW(), " .
				"num_checkins=num_checkins+1 WHERE session_id=:sid;";
			$params = array(
				'sid'			=> $this->sid
			);
			if(!is_null($sid)) {
				$params['sid'] = $sid;
			}
			$retval = $this->db->run_query($sql, $params);
		}
		$this->logout_inactive_sessions();
		
		return($retval);
	}//end checkin()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	private function logout_inactive_sessions() {
		$retval = parent::sessdb_gc();
		return($retval);
	}//end logout_inactive_sessions()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function get_user_info($index=null) {
		$retval = null;
		if(!is_null($this->userInfo) && is_array($this->userInfo)) {
			if(!is_null($index)) {
				$retval = $this->userInfo[$index];
			}
			else {
				$retval = $this->userInfo;
			}
		}
		else {
			throw LogicException(__METHOD__ .": no user data available");
		}
		
		return $retval;
	}
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function update_user_info(array $data, $uid) {
		$retval = false;
		if(is_array($data) && count($data) > 0 && !isset($data['uid'])) { //can't change UID
			
			$updateSql = '';
			$params = array();
			foreach($data as $f=>$v) {
				$updateSql = cs_global::create_list($updateSql, $f .'=:'. $f, ', ');
				$params[$f] = $v;
			}
			
			$sql = 'UPDATE '. $this->table .' SET '. $updateSql .' WHERE uid=:uid';
			$params['uid'] = $uid;
			
			try {
				$numRows = $this->db->run_update($sql, $params);

				if($numRows == 1) {
					$retval = true;
				}
				else {
					throw new LogicException(__METHOD__ .": failed to update user information");
				}
			}
			catch(Exception $ex) {
				throw new ErrorException(__METHOD__ .": error while trying to update user information: ". $ex->getMessage());
			}
		}
		else {
			throw new InvalidArgumentException(__METHOD__ .": invalid user info");
		}
		
		return $retval;
	}
	//-------------------------------------------------------------------------
}//end authUser{}

