<?php
/*
 * Created on Aug 19, 2009
 *
 *  SVN INFORMATION:::
 * -------------------
 * Last Author::::::::: $Author$ 
 * Current Revision:::: $Revision$ 
 * Repository Location: $HeadURL$ 
 * Last Updated:::::::: $Date$
 */


require_once(dirname(__FILE__) .'/cs_webapplibs.abstract.class.php');

class cs_authToken extends cs_webapplibsAbstract {
	
	/** Database object. */
	private $db;
	
	/** Object that helps deal with strings. */
	private $gfObj;
	
	/** Name of the table */
	private $table = 'cswal_auth_token_table';
	
	/** Sequence name for the given table (for PostgreSQL) */
	private $seq = 'cswal_auth_token_table_auth_token_id_seq';
	
	//=========================================================================
	/**
	 * The CONSTRUCTOR.  Sets internal properties & such.
	 */
	public function __construct(cs_phpDB $db) {
		
		if($db->is_connected()) {
			$this->db = $db;
		}
		else {
			throw new exception(__METHOD__ .": database object not connected");
		}
		$this->gfObj = new cs_globalFunctions();
		
	}//end __construct()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Load table into the database...
	 */
	public function load_table() {
		$file = dirname(__FILE__) .'/setup/authtoken_schema.'. $this->db->get_dbtype() .'.sql';
		
		if(file_exists($file)) {
			try {
				$this->db->run_update(file_get_contents($file), true);
			}
			catch(exception $e) {
				throw new exception(__METHOD__ .": error while trying to load table::: ". $e->getMessage());
			}
		}
		else {
			throw new exception(__METHOD__ .": unsupported database type (". $this->db->get_dbtype() .")");
		}
	}//end load_table()
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
		return(md5($tokenId ."_". $uid ."_". $checksum ."_". $stringToHash));
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
		if(!is_null($lifetime) && strlen($lifetime)) {
			$insertData['expiration'] = strftime('%Y-%m-%d %T', strtotime($lifetime));
		}
		if(!is_null($maxUses) && is_numeric($maxUses) && $maxUses > 0) {
			$insertData['max_uses'] = $maxUses;
		}
		try {
			$sql = "INSERT INTO cswal_auth_token_table ". 
					$this->gfObj->string_from_array($insertData, 'insert', null, 'sql');
			$tokenId = $this->db->run_insert($sql, $this->seq);
			
			//now that we have the ID, let's create the real has string.
			$finalHash = $this->create_hash_string($tokenId, $uid, $checksum, $stringToHash);
			
			$this->db->run_update("UPDATE ". $this->table ." SET token='". $finalHash ."' WHERE " .
					"auth_token_id=". $tokenId);
			
			$tokenInfo = array(
				'id'	=> $tokenId,
				'hash'	=> $finalHash
			);
		}
		catch(exception $e) {
			throw new exception(__METHOD__ .": failed to create token::: ". $e->getMessage());
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
			$sql = "UPDATE ". $this->table ." SET total_uses= total_uses+1 " .
					"WHERE auth_token_id=". $tokenId;
			$updateRes = $this->db->run_update($sql);
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
			$sql = "DELETE FROM ". $this->table ." WHERE auth_token_id=". $tokenId;
			$deleteRes = $this->db->run_update($sql);
		}
		catch(exception $e) {
			throw new exception(__METHOD__ .": failed to ");
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
		
		if(is_numeric($tokenId) && strlen($checksum) && strlen($hash) == 32) {
			try {
				$data = $this->get_token_data($tokenId);
				
				if(count($data) == 1 && isset($data[$tokenId]) && is_array($data[$tokenId])) {
					$data = $data[$tokenId];
					
					if($data['token'] == $hash && $data['checksum']) {
						
						$methodCall = 'update_token_uses';
						if(is_numeric($data['max_uses'])) {
							$authTokenRes = null;
							if($data['max_uses'] == $data['total_uses']) {
								//reached max uses already... (maybe this should throw an exception?)
								$methodCall = 'destroy_token';
							}
							elseif($data['total_uses'] < $data['max_uses']) {
								$authTokenRes = $data['uid'];
								if(($data['total_uses'] +1) == $data['max_uses']) {
									//this is the last use: just destroy it.
									$methodCall = 'destroy_token';
								}
							}
							else {
								throw new exception(__METHOD__ .": token (". $tokenId .") used more than max allowed uses [total=". $data['total_uses'] .", max=". $data['max_uses'] ."]");
							}
						}
						else {
							$authTokenRes = $data['uid'];
						}
						$this->$methodCall($tokenId);
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
	protected function get_token_data($tokenId) {
		try {
			$data = $this->db->run_query("SELECT * FROM ". $this->table ." WHERE auth_token_id=". $tokenId
					." AND expiration::date >= CURRENT_DATE", 'auth_token_id');
			if(is_array($data) && count($data) == 1) {
				$tokenData = $data;
			}
			elseif($data === false) {
				$tokenData = false;
			}
			else {
				throw new exception("too many records returned (". count($data) .")");
			}
		}
		catch(exception $e) {
			throw new exception(__METHOD__ .": failed to retrieve tokenId (". $tokenId .")::: ". $e->getMessage());
		}
		return($tokenData);
	}//end get_token_data();
	//=========================================================================
	
}
?>
