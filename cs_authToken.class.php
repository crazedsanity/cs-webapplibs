<?php
/*
 * Created on Aug 19, 2009
 */



class cs_authToken extends cs_webapplibsAbstract {
	
	/** Database object. */
	private $db;
	
	/** Algorithm for password_hash() */
	private $passAlgorithm=PASSWORD_DEFAULT;
	
	/** Algorigthm for creating the hash  */
	private $hashAlgorigthm='sha1';
	
	/** Name of the table */
	private $table = 'cswal_auth_token_table';
	
	/** Sequence name for the given table (for PostgreSQL) */
	private $seq = 'cswal_auth_token_table_auth_token_id_seq';
	
	const EXPIRE_SINGLE = 1;
	const EXPIRE_ALL = 2;
	
	/* Specific results for token authentication.  For security purposes, the user should only be given a result of pass/fail */
	const RESULT_SUCCESS		= 1;
	const RESULT_FAIL		= 2;
	const RESULT_EXPIRED	= 4;
	const RESULT_BADPASS		= 8;
	
	
	//=========================================================================
	/**
	 * The CONSTRUCTOR.  Sets internal properties & such.
	 * @codeCoverageIgnore
	 * 
	 * @param cs_phpDB $db			(cs_phpDB) database object.
	 * @param type $passAlgorithm	(int, optional) for use with PHP's password_hash()
	 * @param type $nonceAlgorithm	(str, optional) for creating a token string
	 * @throws exception
	 */
	public function __construct(cs_phpDB $db, $passAlgorithm=PASSWORD_DEFAULT, $nonceAlgorithm=null) {
		
		if(is_object($db)) {
			parent::__construct(true);
			$this->db = $db;
			
			if(!is_null($passAlgorithm) && is_numeric($passAlgorithm)) {
				$this->passAlgorithm = $passAlgorithm;
			}
			if(!is_null($nonceAlgorithm) && is_string($nonceAlgorithm)) {
				$this->hashAlgorigthm = $nonceAlgorithm;
			}
		}
		else {
			cs_debug_backtrace(1);
			throw new exception(__METHOD__ .": invalid database object (". $db .")");
		}
	}//end __construct()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Generate a hash for creating a new token.  Code was derived from 
	 * http://stackoverflow.com/questions/3290283/what-is-a-good-way-to-produce-a-random-site-salt-to-be-used-in-creating-passwo/3291689#3291689
	 * 
	 * @return string		A string that can be used (as part of) a new token.
	 */
	public function generate_token_string() {
		return hash($this->hashAlgorigthm, $this->create_nonce());
	}//end generate_token_string()
	//=========================================================================
	
	
	
	//=========================================================================
	public function crypto_rand_secure($min, $max) {
		$range = $max - $min;
		$retval = $min;
		
		if($range > 0) {
			$log = log($range, 2);
			$bytes = (int) ($log / 8) + 1; // length in bytes
			$bits = (int) $log + 1; // length in bits
			$filter = (int) (1 << $bits) - 1; // set all lower bits to 1
			do {
				$rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes)));
				$rnd = $rnd & $filter; // discard irrelevant bits
			} 
			while ($rnd >= $range);
			$retval = $min + $rnd;
		}
		return $retval;
	}
	//=========================================================================
	
	
	
	//=========================================================================
	public function create_nonce($length = 32) {
		$nonce = "";
		$codeAlphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
		$codeAlphabet.= "abcdefghijklmnopqrstuvwxyz";
		$codeAlphabet.= "0123456789";
		for ($i = 0; $i < $length; $i++) {
			$nonce .= $codeAlphabet[$this->crypto_rand_secure(0, strlen($codeAlphabet))];
		}
		return $nonce;
	}
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Build a token record in the database that can be authenticated against later.
	 * 
	 * @param $password		(str) matches checksum column...
	 * @param $tokenId		(str, optional) key to match against
	 * @param $lifetime		(str,optional) string (interval) representing how 
	 * 							long the token should last
	 * @param $maxUses		(int,optional) Number of times it can be authenticated 
	 * 							against before being removed 
	 * 
	 * @return (array)		PASS: contains id & hash for the token.
	 * @return (exception)	FAIL: exception contains error details.
	 */
	public function create_token($password, $valueToStore, $tokenId=null, $lifetime=null, $maxUses=null) {
		
		if(is_null($tokenId) || strlen($tokenId) < 1) {
			$tokenId = $this->generate_token_string();
		}
		
		$finalHash = password_hash($password, $this->passAlgorithm);
		
		$insertData = array(
			'auth_token_id'	=> $tokenId,
			'passwd'		=> $finalHash,
			'stored_value'	=> serialize($valueToStore),
		);
		
		if(!is_null($lifetime) && strlen($lifetime)) {
			$insertData['expiration'] = strftime('%Y-%m-%d %T', strtotime($lifetime));
		}
		if(!is_null($maxUses) && is_numeric($maxUses) && $maxUses > 0) {
			$insertData['max_uses'] = $maxUses;
		}
		try {
			$fields = "";
			$values = "";
			foreach($insertData as $k=>$v) {
				$fields = $this->gfObj->create_list($fields, $k);
				$values = $this->gfObj->create_list($values, ':'. $k);
			}
			$sql = "INSERT INTO cswal_auth_token_table (". $fields .") VALUES (". $values .")";

			$numRows = $this->db->run_query($sql, $insertData);
			
			if($numRows != 1) {
				throw new LogicException(__METHOD__ .": unable to create token (". $numRows .")");
			}
		}
		catch(exception $e) {
			throw new ErrorException(__METHOD__ .": failed to create token::: ". $e->getMessage());
		}
		
		return($tokenId);
	}//end create_token()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Update the number of times the given token has been used (even if the 
	 * maximum uses hasn't been set).
	 * 
	 * @param $tokenId		(int) auth_token_id to look up.
	 * 
	 * @return (int)		PASS: updated this many records (should always be 1)
	 * @return (exception)	FAIL: exception denotes problem
	 */
	protected function update_token_uses($tokenId) {
		try {
			#$updateRes = $this->_generic_update($tokenId, array('total_uses'=>"total_uses + 1"));
			$sql = 'UPDATE '. $this->table .' SET total_uses=total_uses + 1 WHERE auth_token_id=:id';
			$updateRes = $this->db->run_update($sql, array('id'=>$tokenId));
		}
		catch(exception $e) {
			throw new exception(__METHOD__ .": failed to update usage count::: ". $e->getMessage());
		}
		return($updateRes);
	}//end update_token_uses()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Deletes the given token ID from the database.
	 * 
	 * @param $tokenId		(int) auth_token_id to delete
	 * 
	 * @return (int)		PASS: this many were deleted (should always be 1)
	 * @return (exception)	FAIL: exception contains error details
	 */
	protected function destroy_token($tokenId) {
		try {
			$sql = "DELETE FROM ". $this->table ." WHERE auth_token_id=:tokenId";
			$deleteRes = $this->db->run_update($sql, array('tokenId'=>$tokenId));
		}
		catch(exception $e) {
			throw new exception(__METHOD__ .": failed to destroy token::: ". $e->getMessage());
		}
		
		return($deleteRes);
	}//end destroy_token()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Check if a token is valid.  See documentation (docs/README_authUser.md)
	 * 
	 * @param $tokenId	(int) auth_token_id to check against
	 * @param $pass		(str) required 'checksum' value.
	 * 
	 * @return (array)	Contains the following indexes:
	 *						'result'       => true/false
	 *						'reason'       => a "RESULT_" class constant
	 *						'stored_value' => value stored in the token (only on success)
	 */
	public function authenticate_token($tokenId, $pass) {
		
		$checkExpiration = $this->remove_expired_tokens($tokenId);
		
		$authTokenRes = array(
			'result'		=> false,
			'reason'		=> self::RESULT_FAIL,
		);
		
		if($checkExpiration['type'] == self::EXPIRE_SINGLE && $checkExpiration['num'] == 0) {
			$data = $this->get_token_data($tokenId);
			if(password_verify($pass, $data['passwd'])) {
				$authTokenRes['result'] = true;
				$authTokenRes['reason'] = self::RESULT_SUCCESS;
				$authTokenRes['stored_value'] = unserialize($data['stored_value']);
				
				//do some maintenance.
				$this->update_token_uses($tokenId);
				$this->remove_expired_tokens($tokenId);
			}
			else {
				$authTokenRes['reason'] = self::RESULT_BADPASS;
			}
		}
		else {
			if($checkExpiration['type'] == self::EXPIRE_SINGLE) {
				$authTokenRes['result'] = self::RESULT_EXPIRED;
			}
			else {
				throw new LogicException(__METHOD__ .": expired multiple tokens instead of just one");
			}
		}
		
		return($authTokenRes);
	}//end authenticate_token()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Retrieve data for the given ID.
	 * 
	 * @param $tokenId		(int) auth_token_id to look up.
	 * 
	 * @return (array)		PASS: contains data about the given ID
	 * @return (exception)	FAIL: exception contains error details.
	 */
	public function get_token_data($tokenId, $onlyNonExpired=false) {
		try {
			$sql = "SELECT * FROM ". $this->table ." WHERE auth_token_id=:tokenId";
			if($onlyNonExpired === true) {
				$sql .= " AND expiration::date >= CURRENT_DATE";
			}
			
			try {
				$numrows = $this->db->run_query($sql, array('tokenId'=>$tokenId));

				if($numrows == 1) {
					$tokenData = $this->db->get_single_record();
				}
				elseif($numrows < 1) {
					$tokenData = false;
				}
				else {
					throw new exception("too many records returned (". count($data) .")");
				}
			} catch(Exception $e) {
				throw new exception(__METHOD__ .": Failed to retrieve token data::: ". $e->getMessage());
			}
		}
		catch(exception $e) {
			throw new exception(__METHOD__ .": failed to retrieve tokenId (". $tokenId .")::: ". $e->getMessage());
		}
		return($tokenData);
	}//end get_token_data();
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Deletes any tokens that are past expiration (does not test for total vs. 
	 * max uses; authenticate_token() does that).
	 * 
	 * @param tokenId(str,optional)	Optionally checks only a specific token
	 * 
	 * @return (array)				indexed array, containing:
	 *								'type'	=> (EXPIRE_SINGLE|EXPIRE_ALL)
	 *								'num'	=> (int)
	 * 
	 * TODO: log each token's expiration
	 */
	public function remove_expired_tokens($tokenId=null) {
		$sql = "SELECT * FROM ". $this->table ." WHERE NOW() > expiration OR (total_uses >= max_uses AND max_uses > 0)";
		$params = array();
		$expireType = self::EXPIRE_ALL;
		if(!is_null($tokenId)) {
			$sql .= " AND auth_token_id=:id";
			$params['id'] = $tokenId;
			$expireType = self::EXPIRE_SINGLE;
		}
		
		$destroyedTokens = array(
			'type'	=> $expireType,
			'num'	=> 0
		);
		
		try {
			$numrows = $this->db->run_query($sql, $params);
			
			if($numrows > 0) {
				$data = $this->db->farray_fieldnames('auth_token_id');
				if(is_array($data)) {
					foreach($data as $tokenId => $tokenData) {
						//TODO: add logging here?
						$destroyedTokens['num'] += $this->destroy_token($tokenId);
					}
				}
			}
			$destroyedTokens['num'] = $numrows;
		}
		catch(exception $e) {
			throw new exception(__METHOD__ .": error encountered while expiring tokens::: ". $e->getMessage());
		}
		
		return($destroyedTokens);
	}//end remove_expired_tokens()
	//=========================================================================
	
}
