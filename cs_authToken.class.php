<?php
/*
 * Created on Aug 19, 2009
 */



class cs_authToken extends cs_webapplibsAbstract {
	
	/** Database object. */
	private $db;
	
	/** Name of the table */
	private $table = 'cswal_auth_token_table';
	
	/** Sequence name for the given table (for PostgreSQL) */
	private $seq = 'cswal_auth_token_table_auth_token_id_seq';
	
	
	//=========================================================================
	/**
	 * The CONSTRUCTOR.  Sets internal properties & such.
	 * @codeCoverageIgnore
	 */
	public function __construct(cs_phpDB $db) {
		
		if(is_object($db)) {
			parent::__construct(true);
			$this->db = $db;
			
			#$upg = new cs_webdbupgrade(dirname(__FILE__) .'/VERSION', dirname(__FILE__) .'/upgrades/upgrade.xml');
			#$upg->check_versions(true);
		}
		else {
			cs_debug_backtrace(1);
			throw new exception(__METHOD__ .": invalid database object (". $db .")");
		}
	}//end __construct()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Standardized method of creating a hash from a string.
	 * 
	 * @param $tokenId			(int) matches auth_token_id column....
	 * @param $uid				(int) matches uid column...
	 * @param $checksum			(str) This is the value that can be used by the 
	 * 								calling code to see if the given uid matches 
	 * 								this data (i.e. using an email address/username).
	 * @param $stringToHash		(str) Data used to help create a hash, usually 
	 * 								something very unique.
	 */
	protected function create_hash_string($tokenId, $uid, $checksum, $stringToHash=NULL) {
		return(sha1($tokenId ."_". $uid ."_". $checksum ."_". $stringToHash));
	}//end create_hash_string()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Build a token record in the database that can be authenticated against later.
	 * 
	 * @param $uid			(int) matches uid column...
	 * @param $checksum		(str) matches checksum column...
	 * @param $stringToHash	(str) unique value to help build hash from.
	 * @param $lifetime		(str,optional) string (interval) representing how 
	 * 							long the token should last.
	 * @param $maxUses		(int,optional) Number of times it can be authenticated 
	 * 							against before being removed.
	 * 
	 * @return (array)		PASS: contains id & hash for the token.
	 * @return (exception)	FAIL: exception contains error details.
	 */
	public function create_token($uid, $checksum, $stringToHash, $lifetime=null, $maxUses=null) {
		
		$insertData = array(
			'uid'		=> $uid,
			'checksum'	=> $checksum,
			'token'		=> '____INCOMPLETE____'
		);
		
		$insertData['expiration'] = strftime('%Y-%m-%d %T', strtotime('1 day'));
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

			$tokenId = $this->db->run_insert($sql, $insertData, $this->seq);
			
			//now that we have the ID, let's create the real hash string.
			$stringToHash .= microtime(true) ."__". rand(1000, 9999999);
			$finalHash = $this->create_hash_string($tokenId, $uid, $checksum, $stringToHash);
			
			$this->_generic_update($tokenId, array('token'=>$finalHash));
			$tokenInfo = array(
				'id'	=> $tokenId,
				'hash'	=> $finalHash
			);
		}
		catch(exception $e) {
			throw new ErrorException(__METHOD__ .": failed to create token::: ". $e->getMessage());
		}
		
		return($tokenInfo);
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
	 * Determine if a token is authentic: the id is used to make the search as
	 * fast as possible, while the hash & checksum are given to compare against.
	 * Failure results in FALSE, while success returns the contact_id for the
	 * given token.
	 *
	 * NOTE: the calling program can leave it to this method to say if the
	 * token is authentic, or use a checksum which can in turn be used to get
	 * a specific contact_id; when they authenticate, the return of this
	 * method must then match the contact_id retrieved from the checksum...
	 *
	 * EXAMPLE:
	 * $tokenUid = cs_authToken::authenticate_token($tokenId, $hash, $checksum);
	 * $realUid = userClass::get_uid_from_email($checksum);
	 * if($tokenUid == $realUid) {
	 *	      //token is truly authentic
	 * }
	 * 
	 * @param $tokenId		(int) auth_token_id to check against
	 * @param $checksum		(str) required 'checksum' value.
	 * @param $hash			(str) required 'token' value.
	 */
	public function authenticate_token($tokenId, $checksum, $hash) {
		
		$authTokenRes = null;
		
		if(is_numeric($tokenId) && strlen($checksum) && strlen($hash) == 40) {
			try {
				$data = $this->get_token_data($tokenId);
				
				if(count($data) == 9 && is_array($data) && isset($data['auth_token_id'])) {
					
					if($data['token'] == $hash && $data['checksum'] == $checksum) {
						
						$methodCall = 'update_token_uses';
						if(is_numeric($data['max_uses'])) {
							$authTokenRes = null;
							if($data['max_uses'] == $data['total_uses']) {
								//reached max uses already... (maybe this should throw an exception?)
								$this->destroy_token($tokenId);
							}
							elseif($data['total_uses'] < $data['max_uses']) {
								$authTokenRes = $data['uid'];
								if(($data['total_uses'] +1) == $data['max_uses']) {
									//this is the last use: just destroy it.
									$this->destroy_token($tokenId);
								}
							}
							else {
								throw new exception(__METHOD__ .": token (". $tokenId .") used more than max allowed uses [total=". $data['total_uses'] .", max=". $data['max_uses'] ."]");
							}
						}
						else {
							$authTokenRes = $data['uid'];
							$this->update_token_uses($tokenId);
						}
					}
				}
				elseif($data === false) {
					$authTokenRes = null;
				}
				else {
					throw new exception(__METHOD__ .": invalid data returned:: ". $this->gfObj->debug_var_dump($data,0));
				}
			}
			catch(exception $e) {
				throw new exception(__METHOD__ .": failed to authenticate token::: ". $e->getMessage());
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
	protected function get_token_data($tokenId, $onlyNonExpired=true) {
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
	 * @param (null)		(void)
	 */
	public function remove_expired_tokens() {
		$sql = "SELECT * FROM ". $this->table ." WHERE NOW() > expiration";
		
		$destroyedTokens = 0;
		try {
			$numrows = $this->db->run_query($sql, array());
			
			if($numrows > 0) {
				$data = $this->db->farray_fieldnames('auth_token_id');
				if(is_array($data)) {
					foreach($data as $tokenId => $tokenData) {
						//TODO: add logging here?
						$destroyedTokens += $this->destroy_token($tokenId);
					}
				}
			}
		}
		catch(exception $e) {
			throw new exception(__METHOD__ .": error encountered while expiring tokens::: ". $e->getMessage());
		}
		
		return($destroyedTokens);
	}//end remove_expired_tokens()
	//=========================================================================
	
	
	
	//=========================================================================
	private function _generic_update($tokenId, $updateParams) {
		try {
			$updateString = "";
			foreach($updateParams as $k=>$v) {
				$updateString = $this->gfObj->create_list($updateString, $k .'=:'. $k);
			}
			$updateParams['tokenId'] = $tokenId;
			$sql = "UPDATE ". $this->table ." SET ". $updateString .", last_updated=NOW() " .
					"WHERE auth_token_id=:tokenId";
			$updateRes = $this->db->run_update($sql, $updateParams);
		}
		catch(exception $e) {
			throw new exception("failed to update token::: ". $e->getMessage());
		}
		return($updateRes);
	}//end generic_update()
	//=========================================================================
	
}
?>
