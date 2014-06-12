<?php


class cs_registerUser {
	
	/** Database connection object */
	private $dbObj;
	
	/** cs_globalFunctions class. */
	private $gfObj;
	
	public $debug=array();
	
	//-------------------------------------------------------------------------
	public function __construct(cs_phpDB $db) {
		
		$this->dbObj = $db;
		
		$this->logger = new cs_webdblogger($this->dbObj, 'Registration');
		
		$this->gfObj = new cs_globalFunctions;
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	public function check_username_available($username) {
		$retval = false;
		$sql = "SELECT * FROM cs_authentication_table WHERE username=:username";
		
		try {
			$check = $this->dbObj->run_query($sql, array('username'=>$username));
			if($check == 0) {
				$retval = true;
			}
			$this->logger->log_by_class(__METHOD__ .": username=[". $username ."], result=(". 
					$this->gfObj->interpret_bool($retval, array(0,1)) .")", 'precheck');
		}
		catch(Exception $e) {
			$details = __METHOD__ .": determine username availability, DETAILS::: ". $e->getMessage();
			$this->logger->log_by_class($details, 'exception');
			throw new exception($details);
		}
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
						$insertSql = "INSERT INTO cs_authentication_table (username, passwd, email) 
								VALUES (:username, :passwd, :email)";
						
						try {
							$retval = $this->dbObj->run_insert($insertSql, $insertData, 'cs_authentication_table_uid_seq');
							
							//now let's build the activation email.
							$activateData = array(
								'email'			=> $email,
								'username'		=> $username,
								'activateHash'	=> md5($retval .'-'. $username),
								'uid'			=> $retval,
								'HOST'			=> $_SERVER['HTTP_HOST'],
								'username'		=> $username,
								'password'		=> $password
							);
							
							$this->logger->log_by_class("Created uid (". $retval ."), username=(". $username ."), "
									."email=(". $email .")", 'create');
							
							$this->debug[__METHOD__] = $this->send_activation_email($activateData);
						}
						catch(Exception $e) {
							$details = __METHOD__ .": failed to create new user, DETAILS::: ". $e->getMessage();
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
					$details = "Password complexity failed: >= 8 chars, 1 letter, 1 number, at least one upper and one lowercase.";
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
			$sql = "SELECT * FROM cs_authentication_table WHERE uid=:uid";
			try {
				$this->dbObj->run_query($sql, array('uid'=>$uid));
				$data = $this->dbObj->get_single_record();
				
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
