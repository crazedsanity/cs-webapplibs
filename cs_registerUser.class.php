<?php


/* SVN INFORMATION::::
 * --------------------------
 * $HeadURL: https://svn.crazedsanity.com/svn/main/sites/crazedsanity.com/trunk/lib/registerUser.class.php $
 * $Id: registerUser.class.php 1909 2011-07-20 00:50:57Z danf $
 * $LastChangedDate: 2011-07-19 19:50:57 -0500 (Tue, 19 Jul 2011) $
 * $LastChangedRevision: 1909 $
 * $LastChangedBy: danf $
 */

class cs_registerUser {
	
	/** Database connection object */
	private $dbObj;
	
	/** cs_globalFunctions class. */
	private $gfObj;
	
	public $debug=array();
	
	//-------------------------------------------------------------------------
	public function __construct() {
		
		$parameters = array(
			'host'		=> $GLOBALS['DB_PG_HOST'],
			'dbname'	=> $GLOBALS['DB_PG_DBNAME'],
			'port'		=> $GLOBALS['DB_PG_PORT'],
			'user'		=> $GLOBALS['DB_PG_DBUSER'],
			'password'	=> $GLOBALS['DB_PG_DBPASS'],
		);
		
		$this->dbObj = new cs_phpDB('pgsql');
		$this->dbObj->connect($parameters);
		
		$this->logger = new cs_webdblogger($this->dbObj, 'Registration');
		
		$this->gfObj = new cs_globalFunctions;
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	//-------------------------------------------------------------------------
	private function run_sql($sql) {
		$numrows = $this->dbObj->exec($sql);
		$dberror = $this->dbObj->errorMsg();
		
		if(strlen($dberror) || !is_numeric($numrows) || $numrows < 0) {
			$details = __METHOD__ .": invalid numrows (". $numrows .") or database error: ". $dberror ."<BR>\nSQL: ". $sql;
			$this->logger->log_by_class($details, 'code exception');
			throw new exception($details);
		}
		else {
			$retval = $numrows;
		}
		
		return($retval);
	}//end run_sql()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function check_username_available($username) {
		$retval = false;
		$sql = "SELECT * FROM cs_authentication_table WHERE username='".
			$this->gfObj->cleanString($username, 'sql') ."'";
		
		$check = $this->run_sql($sql);
		if($check == 0) {
			$retval = true;
		}
		$this->logger->log_by_class(__METHOD__ .": username=[". $username ."], result=(". 
				$this->gfObj->interpret_bool($retval, array(0,1)) .")", 'precheck');
		return($retval);
	}//end check_username_available()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function check_password_complexity($password, $passwordCheck) {
		$retval = array(
			'result'	=> false,
			'passcheck'	=> 0,
			'info'		=> "Password too short or not given"
		);
		if($password != $passwordCheck) {
			$retval['info'] = "Passwords don't match.";
		}
		elseif(!is_null($password) && strlen($password) <8) {
			$retval['info'] = "Password too short; must be at least 8 characters.";
		}
		elseif(!is_null($password)) {
			if($password == $passwordCheck) {
				$retval['passcheck'] = 1;
				$regexList = array(
					'one number'			=> '/[0-9]{1,}/',
					'one lowercase letter'	=> '/[a-z]{1,}/',
					'one uppercase letter'	=> '/[A-Z]{1,}/',
				);
				$passes = 0;
				$retval['info'] = "";
				foreach($regexList as $text=>$regex) {
					$passFailText="FAIL";
					if(preg_match($regex, $password)) {
						$passes++;
						$passFailText = "ok";
					}
					else {
						$retval['info'] = $this->gfObj->create_list($retval['info'], $text, " and ");
					}
				}
				if($passes == count($regexList)) {
					$retval['result'] = true;
				}
				else {
					$retval['info'] = "Password must contain at least one ". $retval['info'];
				}
			}
			else {
				$retval['info'] = "passwords don't match";
			}
		}
		$this->logger->log_by_class(__METHOD__ .": result=(". 
				$this->gfObj->interpret_bool($retval['result'], array(0,1)) ."), "
						."passcheck=(". $retval['passcheck'] .")", 'precheck');
		
		return($retval);
	}//end check_password_complexity()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function check_email_validity($emailAddr, $giveReasonOnFail=false) {
		$retval = false;
		if(strlen($emailAddr)) {
			$cleaned = $this->gfObj->cleanString($emailAddr, 'email');
			//the length assumes an email with the smallest form being 'jd@xy.com'
			$emailRegex = '/^[A-Z0-9\._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$/i';
			if($emailAddr == $cleaned && preg_match($emailRegex, $emailAddr)) {
				$retval = true;
			}
			
			if($giveReasonOnFail == true) {
				$debug = "";
				if($emailAddr != $cleaned) {
					$debug .= "cleaned does NOT match original";
				}
				if(!preg_match($emailRegex, $emailAddr)) {
					$debug .= " || regex failed";
				}
				$retval = "provided email=(". $emailAddr ."), cleaned email=(". $cleaned ."), retval=(". $retval ."), debug=(". $debug .")";
			}
		}
		else {
			$details = __METHOD__ .": no valid data provided";
			$this->logger->log_by_class($details, 'code exception');
			throw new exception($details);
		}
		
		$this->logger->log_by_class(__METHOD__ .": email=(". $emailAddr ."), retval=(". $retval .")", 'precheck');
		
		return($retval);
	}//end check_email_validity()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function register_user($username, $password, $checkPassword, $email) {
		if(strlen($username) && strlen($password) && strlen($checkPassword) && strlen($email)) {
			if($this->check_username_available($username)) {
				//check passwords.
				$passCheck = $this->check_password_complexity($password, $checkPassword);
				if($passCheck['result'] === true) {
					if($this->check_email_validity($email)) {
						//this is where we attempt to insert the user's information.
						$insertData = array(
							'username'	=> $username,
							'passwd'	=> md5($username .'-'. $password),
							'email'		=> $email
						);
						$insertSql = "INSERT INTO cs_authentication_table " 
								. $this->gfObj->string_from_array($insertData, 'insert');
						
						if($this->run_sql($insertSql) == 1) {
							$this->run_sql("SELECT currval('cs_authentication_table_uid_seq')");
							$data = $this->dbObj->farray();
							$retval = $data[0];
							
							//now let's build the activation email.
							require_once(constant('LIBDIR') ."/phpmailer/class.phpmailer.php");
							require_once(constant('LIBDIR') ."/cs-content/cs_genericPage.class.php");
							$activateData = array(
								'email'			=> $email,
								'username'		=> $username,
								'activateHash'	=> md5($retval .'-'. $username),
								'uid'			=> $retval,
								'HOST'			=> $GLOBALS['pageObj']->templateVars['HOST'],
								'username'		=> $username,
								'password'		=> $password
							);
							
							$this->logger->log_by_class("Created uid (". $retval ."), username=(". $username ."), "
									."email=(". $email .")", 'create');
							
							$this->debug[__METHOD__] = $this->send_activation_email($activateData);
						}
						else {
							$details = __METHOD__ .": failed to create new user";
							$this->logger->log_by_class($details, 'exception');
							throw new exception($details);
						}
					}
					else {
						$details = __METHOD__ .": invalid email address (". $email .")";
						$this->logger->log_by_class($details, 'exception');
						throw new exception($details);
					}
				}
				else {
					$details = __METHOD__ .": password complexity failed";
					$this->logger->log_by_class($details, 'exception');
					throw new exception($details);
				}
			}
			else {
				$details = __METHOD__ .": username (". $username .") is not available";
				$this->logger->log_by_class($details, 'exception');
				throw new exception($details);
			}
		}
		else {
			$details = __METHOD__ .": one or more required fields missing";
			$this->logger->log_by_class($details, 'exception');
			throw new exception($details);
		}
		
		return($retval);
	}//end register_user()
	//-------------------------------------------------------------------------
	
	
	//-------------------------------------------------------------------
	function send_activation_email($infoArr) {
		//setup the variables for sending the email...
		$to   = $infoArr['email'];
		$subj = "New Account Activation [". $infoArr['uid'] ."]";
		
		//read-in the contents of the email template.
		$page = new cs_genericPage(false, "content/registrationEmail.tmpl");
		
		//parse the template...
		foreach($infoArr as $tmpl=>$value) {
			$page->add_template_var($tmpl,$value);
		}
		#$body = mini_parser($tmpl, $infoArr, "{", "}");
		$body = $page->return_printed_page();
		
		$result = $this->send_single_email($to, $subj, $body);
		$this->debug[__METHOD__] = $result;
		
		$this->logger->log_by_class($this->gfObj->debug_print($this->debug,0), 'debug');
		
		return($result);
	}//end send_activation_email()
	//-------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------
	/**
	 * Send an email to a single address with no special parsing.
	 */
	function send_single_email($toAddr, $subject, $body) {
			
		$mail = new PHPMailer();
		$mail->SetLanguage("en");
		$mail->IsSendmail();
		
		$mail->IsMail();
		//$mail->IsSMTP;
		$mail->Host = "127.0.0.1";
		$mail->From = "newAccounts@crazedsanity.com";
		$mail->FromName = "New User Registration";
		$mail->AddAddress($toAddr);
		$mail->ContentType = "text/plain";
		$mail->Subject = $subject;
		$mail->Body = $body;
		
		$result = $mail->Send();
		$this->debug[__METHOD__] = $result;
		return($toAddr);
	}//end send_single_email()
	//-------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------
	public function activate_user($uid, $hash) {
		$activateRes = false;
		if(is_numeric($uid) && strlen($hash) == 32) {
			$sql = "SELECT * FROM cs_authentication_table WHERE uid=". $uid;
			try {
				$data = $this->dbObj->run_query($sql);
				
				$checkHash = md5($uid .'-'. $data['username']);
				if($hash == $checkHash) {
					$sql = "UPDATE cs_authentication_table SET user_status_id =1 WHERE uid=". $uid;
					if($this->dbObj->run_update($sql									)) {
						//TODO: log it.
						$activateRes = true;
					}
				}
				else {
					//TODO: log something here.
				}
			}
			catch(exception $e) {
				throw new exception(__METHOD__ .": running SQL failed::: ". $sql);
			}
		}
		return($activateRes);
	}//end activate_user()
	//-------------------------------------------------------------------
	
	
	
	
}//end authUser{}
