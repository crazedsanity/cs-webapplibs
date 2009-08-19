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
	protected function create_hash_string($tokenId, $uid, $checksum, $stringToHash=NULL) {
		return(md5($tokenId ."_". $uid ."_". $checksum ."_". $stringToHash));
	}//end create_hash_string()
	//=========================================================================
	
	
	
	//=========================================================================
	public function create_token($uid, $checksum, $stringToHash, $lifetime=null, $maxUses=null) {
		
		$insertData = array(
			'uid'		=> $uid,
			'checksum'	=> $checksum,
			'token'		=> '____INCOMPLETE____'
		);
		if(!is_null($lifetime) && strlen($lifetime)) {
			$insertData['duration'] = $lifetime;
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
	 */
	public function authenticate_token($tokenId, $checksum, $hash) {
		
		$authTokenRes = null;
		
		if(is_numeric($tokenId) && strlen($checksum) && strlen($hash) == 32) {
			$sql = "SELECT * FROM ". $this->table ." WHERE auth_token_id=". $tokenId
					." AND (creation + duration)::date >= CURRENT_DATE";
			
			try {
				$data = $this->db->run_query($sql, 'auth_token_id');
				
				if(count($data) == 1 && isset($data[$tokenId]) && is_array($data[$tokenId])) {
					$data = $data[$tokenId];
					
					if($data['token'] == $hash && $data['checksum'] == $checksum) {
						
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
	protected function get_token_data($tokenId) {
		try {
			$data = $this->db->run_query("SELECT * FROM ". $this->table ." WHERE auth_token_id=". $tokenId);
		}
		catch(exception $e) {
			throw new exception(__METHOD__ .": failed to retrieve tokenId (". $tokenId .")::: ". $e->getMessage());
		}
		return($data);
	}//end get_token_data();
	//=========================================================================
	
}
?>
