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
	public function create_token($uid, $checksum, $stringToHash) {
		
		$this->db->beginTrans();
		
		$insertData = array(
			'uid'		=> $uid,
			'checksum'	=> $checksum,
			'token'		=> '____INCOMPLETE____'
		);
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
	
}
?>
