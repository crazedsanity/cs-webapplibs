<?php
/*
 * Created on Jul 2, 2007
 * 
 */


class cs_webdbupgrade extends cs_webapplibsAbstract {
	
	/** cs_fileSystem{} object: for filesystem read/write operations. */
	protected $fsObj;
	
	/** cs_globalFunctions{} object: debugging, array, and string operations. */
	protected $gfObj;
	
	/** Array of configuration parameters. */
	protected $config = NULL;
	
	/** Name of primary key sequence of main table (for handling inserts with PostgreSQL) */
	protected $sequenceName = 'cswal_version_table_version_id_seq';
	
	/** Database object. */
	protected $db;
	
	/** Object used to log activity. */
	protected $logsObj;
	
	/** Name of the project as referenced in the database. */
	protected $projectName;
	
	/** Internal cache of the version string from the VERSION file. */
	protected $versionFileVersion = NULL;
	
	/** Stored database version. */
	protected $databaseVersion = NULL;
	
	/** Determines if an internal upgrade is happening (avoids some infinite loops) */
	protected $internalUpgradeInProgress = false;
	
	/** Avoids an exception if the project has no version information in the database */
	protected $allowNoDBVersion=true; //TODO: make allowNoDBVersion accessible or remove altogether
	
	/** Log messages to store during an internal upgrade (to avoid problems) */
	protected $storedLogs = array();
	protected $debugLogs=array();
	
	protected $dbType=null;
	
	protected $dbTable = 'cswal_version_table';
	protected $dbPrimaryKey = 'version_id';
	protected $upgradConfigFile;
	protected $dbParams = array();
	protected $rwDir = "";
	protected $initialVersion = "";
	protected $matchingData = array();
	
	protected $lockObj = null;
	
	protected static $lockfile;
	
	
	/** List of acceptable suffixes; example "1.0.0-BETA3" -- NOTE: these MUST be in 
	 * an order that reflects newest -> oldest; "ALPHA happens before BETA, etc. */
	protected $suffixList = array(
		'ALPHA', 	//very unstable
		'BETA', 	//kinda unstable, but probably useable
		'RC'		//all known bugs fixed, searching for unknown ones
	);
	
	//=========================================================================
	public function __construct($versionFileLocation, $upgradeConfigFile, cs_phpDB $db=null) {
		
		//setup configuration parameters for database connectivity.
		$this->set_version_file_location(dirname(__FILE__) .'/VERSION');
		if(is_object($db)) {
			$this->db = $db;
			
			$this->dbParams = array(
				'dsn'	=> $db->get_dsn(),
				'user'	=> $db->get_username(),
				'pass'	=> $db->get_password()
			);
		}
		else {
			$prefix = preg_replace('/-/', '_', $this->get_project());
			$this->dbParams = array(
				'dsn'	=> constant($prefix .'-DB_CONNECT_DSN'),
				'user'	=> constant($prefix .'-DB_CONNECT_USER'),
				'pass'	=> constant($prefix .'-DB_CONNECT_PASSWORD')
			);
			
			try {
				$this->db = new cs_phpDB($this->dbParams['dsn'], $this->dbParams['user'], $this->dbParams['pass']);
			}
			catch(exception $e) {
				throw new exception(__METHOD__ .": failed to connect to database or logger error: ". $e->getMessage());
			}
		}
		
		//Check for some required constants.
		if(!defined('LIBDIR')) {
			throw new exception(__METHOD__ .": required constant 'LIBDIR' not set");
		}
		if(!defined('SITE_ROOT')) {
			// TODO: this should really be using a "RW" directory.
			// TODO: make the "rwDir" code (further down) more apparent.
			throw new exception(__METHOD__ .": required constant 'SITE_ROOT' not set");
		}
		
		
		parent::__construct(true);
		if(defined('DEBUGPRINTOPT')) {
			$this->gfObj->debugPrintOpt = constant('DEBUGPRINTOPT');
		}
		
		if(defined('DBTYPE')) {
			$this->dbType = constant('DBTYPE');
		}
		
		if(!file_exists($upgradeConfigFile) || !is_readable($upgradeConfigFile)) {
			throw new exception(__METHOD__ .": required upgrade config file location (". $upgradeConfigFile .") not set or unreadable");
		}
		else {
			$this->upgradConfigFile = $upgradeConfigFile;
		}
		if(!strlen($versionFileLocation) || !file_exists($versionFileLocation)) {
			throw new exception(__METHOD__ .": unable to locate version file (". $versionFileLocation .")");
		}
		$this->set_version_file_location($versionFileLocation);
		
		$this->lockObj = new cs_lockfile();
		$this->lockObj->set_lockfile("upgrade.lock");
		self::$lockfile = $this->lockObj->get_lockfile();
		
		$this->fsObj =  new cs_fileSystem(constant('SITE_ROOT'));
		if($this->lockObj->is_lockfile_present()) {
			//there is an existing lockfile...
			throw new exception(__METHOD__ .": upgrade in progress: ". $this->lockObj->read_lockfile());
		}
		
		$this->check_internal_upgrades();
		
		try {
			$loggerDb = new cs_phpDB($this->dbParams['dsn'], $this->dbParams['user'], $this->dbParams['pass']);
			$this->logsObj = new cs_webdblogger($loggerDb, "Upgrade ". $this->projectName, false);
		}
		catch(exception $e) {
			throw new exception(__METHOD__ .": failed to create logger::: ". $e->getMessage());
		}
		
		$this->check_versions(false);
	}//end __construct()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Determine if there are any upgrades that need to be performed...
	 */
	protected function check_internal_upgrades() {
		$oldVersionFileLocation = $this->versionFileLocation;
		$oldUpgradeConfigFile = $this->upgradConfigFile;
		$this->upgradeConfigFile = dirname(__FILE__) .'/upgrades/upgrade.xml';
		
		
		//set a status flag so we can store log messages (for now).
		$this->internalUpgradeInProgress = true;
		
		
		//do stuff here...
		$this->set_version_file_location(dirname(__FILE__) .'/VERSION');
		$this->read_version_file();
		
		//if there is an error, then... uh... yeah.
		try {
			$this->get_database_version();
		}
		catch(exception $e) {
			throw new exception(__METHOD__ .": error while retrieving database version: ". $e->getMessage());
			
			//try creating the version.
			$this->load_initial_version();
		}
		
		//do upgrades here...
		$this->check_versions(true);
		$this->internalUpgradeInProgress = false;
		
		
		
		
		//reset internal vars.
		$this->set_version_file_location($oldVersionFileLocation);
		$this->upgradeConfigFile = $oldUpgradeConfigFile;
		$this->read_version_file();
		
	}//end check_internal_upgrades()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Where everything begins: checks if the version held in config.xml lines-up 
	 * with the one in the VERSION file; if it does, then it checks the version 
	 * listed in the database.
	 */
	public function check_versions($performUpgrade=TRUE) {
		if(!is_bool($performUpgrade) || is_null($performUpgrade)) {
			$performUpgrade = true;
		}
		
		//first, check that all files exist.
		$retval = NULL;
		
		//check to see if the lock files for upgrading exist.
		if($this->upgrade_in_progress()) {
			$this->do_log("Upgrade in progress", 'notice');
			throw new exception(__METHOD__ .": upgrade in progress");
		}
		else {
			//okay, all files present: check the version in the VERSION file.
			$this->read_version_file();
			$dbVersion = $this->get_database_version();
			if(!is_array($dbVersion)) {
				$this->load_initial_version();
			}
			
			$versionsDiffer = TRUE;
			$retval = FALSE;
			
			if($this->check_for_version_conflict() == false) {
				$versionsDiffer = false;
				$performUpgrade = false;
			}
			else {
				$versionsDiffer = true;
			}
			
			if($versionsDiffer == TRUE && $performUpgrade === TRUE) {
				//reset the return value, so it'll default to failure until we say otherwise.
				$retval = NULL;
				
				//Perform the upgrade!
				$this->perform_upgrade();
			}
		}
		
		return($retval);
	}//end check_versions()
	//=========================================================================
	
	
	
	//=========================================================================
	protected function read_version_file() {
		$retval = NULL;
		
		//okay, all files present: check the version in the VERSION file.
		if(file_exists($this->versionFileLocation)) {
			$versionFileContents = $this->fsObj->read($this->versionFileLocation);

			$versionMatches = array();
			preg_match_all('/\nVERSION: (.*)\n/', $versionFileContents, $versionMatches);
			if(count($versionMatches) == 2 && count($versionMatches[1]) == 1) {
				$retval = trim($versionMatches[1][0]);
				$this->versionFileVersion = $this->get_full_version_string($retval);

				//now retrieve the PROJECT name.
				$projectMatches = array();
				preg_match_all('/\nPROJECT: (.*)\n/', $versionFileContents, $projectMatches);
				if(count($projectMatches) == 2 && count($projectMatches[1]) == 1) {
					$this->projectName = trim($projectMatches[1][0]);
				}
				else {
					$this->error_handler(__METHOD__ .": failed to find PROJECT name");
				}
			}
			else {
				$this->error_handler(__METHOD__ .": could not find VERSION data");
			}
		}
		else {
			$this->error_handler(__METHOD__ .": Version file (". $this->versionFileLocation .") does not exist");
		}
		
		return($retval);
	}//end read_version_file()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Read information from our config file, so we know what to expect.
	 */
	protected function read_upgrade_config_file() {
		try {
			$xmlString = $this->fsObj->read($this->upgradeConfigFile);
		}
		catch(exception $e) {
			throw new exception(__METHOD__ .": failed to read upgrade config file::: ". $e->getMessage());
		}
		
		//parse the file.
		$xmlParser = new cs_phpxmlParser($xmlString);
		
		if($xmlParser->get_root_element() == 'UPGRADE') {
			
			//see if there's an "initial version" setting.
			try {
				$this->initialVersion = $xmlParser->get_tag_value('/UPGRADE/INITIALVERSION');
			}
			catch(Exception $e) {
				//no worries, this only happens when the tag doesn't exist or it doesn't have data (that is okay).
			}
			
			$tConfig = array();
			if(is_array($xmlParser->get_data('/UPGRADE/MATCHING'))) {
				$matchingData = $xmlParser->get_data('/UPGRADE/MATCHING');
				foreach($matchingData as $index=>$array) {
					$array = $array[0];
					foreach($array as $matchingName=>$subInfo) {
						if(isset($subInfo[0][cs_phpxmlCreator::dataIndex])) {
							$tConfig[$index][$matchingName] = $subInfo[0][cs_phpxmlCreator::dataIndex];
						}
						else {
							throw new exception(__METHOD__ .": invalid data beneath matching (". $index .")::: ". $this->gfObj->debug_print($subInfo,0));
						}
					}
				}
			}
			$this->matchingData = $tConfig;
		}
		else {
			$this->error_handler(__METHOD__ .": failed to retrieve 'UPGRADE' section; " .
					"make sure upgrade.xml's ROOT element is 'UPGRADE'");
		}
		
		return($xmlParser);
	}//end read_upgrade_config_file()
	//=========================================================================
	
	
	
	//=========================================================================
	protected function perform_upgrade() {
		$transactionStartedExternally = $this->db->get_transaction_status();
		if($this->upgrade_in_progress()) {
			//ew.  Can't upgrade.
			$this->error_handler(__METHOD__ .": upgrade already in progress...????");
		}
		else {
			$lockConfig = $this->upgrade_in_progress(TRUE);
			$this->fsObj->cd("/");
			
			$this->do_log("Starting upgrade process...", 'begin');
			
			if($lockConfig === 0) {
				$this->error_handler(__METHOD__ .": failed to set 'upgrade in progress'");
			}
			else {
				$this->do_log(__METHOD__ .": result of creating lockfile: (". $lockConfig .")", 'debug');
				
				//push data into our internal "config" array.
				$this->read_upgrade_config_file();
				$this->get_database_version();
				
				$versionConflictInfo = $this->check_for_version_conflict();
				
				if($versionConflictInfo !== false) {
					$this->do_log("Upgrading ". $versionConflictInfo ." versions, from " .
							"(". $this->databaseVersion .") to (". $this->versionFileVersion .")", 'info');
				}
				
				$upgradeList = $this->get_upgrade_list();
				try {
					$i=0;
					$this->do_log(__METHOD__ .": starting to run through the upgrade list, starting at (". $this->databaseVersion ."), " .
							"total number of upgrades to perform: ". count($upgradeList), 'debug');
					if(!$transactionStartedExternally) {
						$this->db->beginTrans();
					}
					foreach($upgradeList as $fromVersion=>$toVersion) {
						
						$details = __METHOD__ .": upgrading from ". $fromVersion ." to ". $toVersion ."... ";
						$this->do_log($details, 'system');
						$this->do_single_upgrade($fromVersion, $toVersion);
						$this->get_database_version();
						$i++;
						
						$details = __METHOD__ .": finished upgrade #". $i .", now at version (". $this->databaseVersion .")";
						$this->do_log($details, 'system');
					}
					
					if($i < count($upgradeList)) {
						$this->do_log(__METHOD__ .": completed upgrade ". $i ." of ". count($upgradeList), 'debug');
					}
					else {
						if($this->databaseVersion == $this->versionFileVersion) {
							$this->do_log(__METHOD__ .": finished upgrading after performing (". $i .") upgrades", 'debug');
							$this->newVersion = $this->databaseVersion;
						}
						else {
							$this->do_log(__METHOD__ .": upgradeList::: ". $this->gfObj->debug_print($upgradeList,0), 'debug');
							$this->error_handler(__METHOD__ .": finished upgrade, but version wasn't updated (expecting '". $this->versionFileVersion ."', got '". $this->databaseVersion ."')!!!");
						}
					}
					$this->lockObj->delete_lockfile();
					
					if(!$transactionStartedExternally) {
						$this->db->commitTrans();
					}
				}
				catch(exception $e) {
					$transactionStatus = $this->db->get_transaction_status(false);
					$this->error_handler(__METHOD__ .": transaction status=(". $transactionStatus ."), upgrade aborted:::". $e->getMessage());
					if(!$transactionStartedExternally) {
						$this->db->rollbackTrans();
					}
				}
				$this->do_log("Upgrade process complete", 'end');
			}
		}
	}//end perform_upgrade()
	//=========================================================================
	
	
	
	//=========================================================================
	public function upgrade_in_progress($makeItSo=FALSE) {
		if($makeItSo === TRUE) {
			if(strlen($this->databaseVersion)) {
				$details = $this->projectName .': Upgrade from '. $this->databaseVersion .' started at '. date('Y-m-d H:i:s');
				$this->lockObj->create_lockfile($details);
				$retval = TRUE;
			}
			else {
				$this->error_handler(__METHOD__ .": missing internal databaseVersion (". $this->databaseVersion .")");
			}
		}
		$retval = $this->lockObj->is_lockfile_present();
		
		return($retval);
	}//end upgrade_in_progress()
	//=========================================================================
	
	
	
	//=========================================================================
	public function parse_version_string($versionString) {
		if(is_null($versionString) || !strlen($versionString)) {
			cs_debug_backtrace(1);
			$this->error_handler(__METHOD__ .": invalid version string ($versionString)");
		}
		
		$suffix = "";
		$explodeThis = $versionString;
		if(preg_match('/-[A-Z]{2,5}[0-9]{1,}/', $versionString)) {
			$bits = explode('-', $versionString);
			$suffix = $bits[1];
			$explodeThis = $bits[0];
		}
		$tmp = explode('.', $explodeThis);
		
		
		if(is_numeric($tmp[0]) && is_numeric($tmp[1])) {
			$retval = array(
				'version_string'	=> $versionString,
				'version_major'		=> $tmp[0],
				'version_minor'		=> $tmp[1],
			);
			if(isset($tmp[2])) {
				$retval['version_maintenance'] = $tmp[2];
			}
			else {
				$retval['version_maintenance'] = 0;
			}
			
			$retval['version_suffix'] = $suffix;
		}
		else {
			$this->error_handler(__METHOD__ .": invalid version string format, requires MAJOR.MINOR syntax (". $versionString .")");
		}
		
		return($retval);
	}//end parse_version_string()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Checks for issues with versions.
	 * 0		== No problems.
	 * (string)	== upgrade applicable (indicates "major"/"minor"/"maintenance").
	 * NULL		== encountered error
	 */
	protected function check_for_version_conflict() {
		//set a default return...
		$retval = NULL;
		
		$this->read_version_file();
		
		//parse the version strings.
		$dbVersion = $this->get_database_version();
		$versionFileData = $this->parse_version_string($this->versionFileVersion);
		
		if($versionFileData['version_string'] == $dbVersion['version_string']) {
			//good to go: no upgrade needed.
			$retval = false;
		}
		else {
			//NOTE: this seems very convoluted, but it works.
			if($versionFileData['version_major'] == $dbVersion['version_major']) {
				if($versionFileData['version_minor'] == $dbVersion['version_minor']) {
					if($versionFileData['version_maintenance'] == $dbVersion['version_maintenance']) {
						if($versionFileData['version_suffix'] == $dbVersion['version_suffix']) {
							$this->error_handler(__METHOD__ .": no version upgrade detected, but version strings don't match (versionFile=". $versionFileData['version_string'] .", dbVersion=". $dbVersion['version_string'] .")");
						}
						else {
							$retval = "suffix";
						}
					}
					elseif($versionFileData['version_maintenance'] > $dbVersion['version_maintenance']) {
						$retval = "maintenance";
					}
					else {
						$this->error_handler(__METHOD__ .": downgrading from maintenance versions is unsupported");
					}
				}
				elseif($versionFileData['version_minor'] > $dbVersion['version_minor']) {
					$retval = "minor";
				}
				else {
					$this->error_handler(__METHOD__ .": downgrading minor versions is unsupported, project_name=(". $this->get_project() ."), ".
						"file version=(". $versionFileData['version_string'] ."), dbVersion=(". $dbVersion['version_string'] ."");
				}
			}
			elseif($versionFileData['version_major'] > $dbVersion['version_major']) {
				$retval = "major";
			}
			else {
				$this->error_handler(__METHOD__ .": downgrading major versions is unsupported");
			}
		}
		
		return($retval);
	}//end check_for_version_conflict()
	//=========================================================================
	
	
	
	//=========================================================================
	protected function get_database_version() {
		$this->gfObj->debugPrintOpt=1;
		$sql = "SELECT * FROM ". $this->dbTable ." WHERE ".
				"project_name=:projectName";
		
		$numrows = $this->db->run_query($sql, array('projectName'=>$this->projectName));
		$dberror = $this->db->errorMsg();
		
		if(strlen($dberror) || $numrows != 1) {
			//
			if(preg_match('/doesn\'t exist/', $dberror) || preg_match('/does not exist/', $dberror)) {
				//add the table...
				$loadTableResult = $this->load_table();
				if($loadTableResult === TRUE) {
					//now try the SQL...
					$numrows = $this->db->run_query($sql, array('projectName'=>$this->projectName));
					$dberror = $this->db->errorMsg();
					
					//retrieve the data...
					$data = $this->db->get_single_record();
					$this->databaseVersion = $data['version_string'];
					$retval = $this->parse_version_string($data['version_string']);
				}
				else {
					$this->error_handler(__METHOD__ .": no table in database, failed to create one... ORIGINAL " .
						"ERROR: ". $dberror .", SCHEMA LOAD ERROR::: ". $loadTableResult);
				}
			}
			elseif(!strlen($dberror) && $numrows == 0) {
				if($this->allowNoDBVersion) {
					$retval = false;
				}
				else {
					$this->error_handler(__METHOD__ .": no version data found for (". $this->projectName .")");
				}
			}
			else {
				$this->error_handler(__METHOD__ .": failed to retrieve version... numrows=(". $numrows ."), DBERROR::: ". $dberror);
			}
		}
		else {
			$data = $this->db->get_single_record();
			$this->databaseVersion = $data['version_string'];
			$retval = $this->parse_version_string($data['version_string']);
		}
		
		return($retval);
	}//end get_database_version()
	//=========================================================================
	
	
	
	//=========================================================================
	protected function do_single_upgrade($fromVersion, $toVersion=null) {
		//Use the "matching_syntax" data in the upgrade.xml file to determine the filename.
		$versionIndex = "V". $this->get_full_version_string($fromVersion);
		if(!isset($this->matchingData[$versionIndex])) {
			//version-only upgrade.
			$this->newVersion = $toVersion;
			$this->update_database_version($toVersion);
		}
		else {
			//scripted upgrade...
			$upgradeData = $this->matchingData[$versionIndex];
			
			if(isset($upgradeData['TARGET_VERSION']) && count($upgradeData) > 1) {
				$this->newVersion = $upgradeData['TARGET_VERSION'];
				if(isset($upgradeData['SCRIPT_NAME']) && isset($upgradeData['CLASS_NAME']) && isset($upgradeData['CALL_METHOD'])) {
					//good to go; it's a scripted upgrade.
					$this->do_scripted_upgrade($upgradeData);
					$this->update_database_version($upgradeData['TARGET_VERSION']);
				}
				else {
					$this->error_handler(__METHOD__ .": not enough information to run scripted upgrade for ". $versionIndex);
				}
			}
			else {
				$this->error_handler(__METHOD__ .": target version not specified, unable to proceed with upgrade for ". $versionIndex);
			}
		}
		$this->do_log("Finished upgrade to ". $this->newVersion, 'system');
	}//end do_single_upgrade()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Updates information that's stored in the database, internal to cs-project, 
	 * so the version there is consistent with all the others.
	 */
	protected function update_database_version($newVersionString) {
		$versionInfo = $this->parse_version_string($newVersionString);
		$sql = "UPDATE ". $this->dbTable ." SET version_string=:vStr WHERE project_name=:pName";
		$params = array(
			'vStr'	=> $versionInfo['version_string'],
			'pName'	=> $this->projectName
		);
		
		
		$updateRes = $this->db->run_update($sql, $params);
		if($updateRes == 1) {
			$retval = $updateRes;
		}
		else {
			$this->error_handler(__METHOD__ .": invalid result (". $updateRes .")	");
		}
		
		//okay, now check that the version string matches the updated bits.
		if(!$this->check_database_version($this->newVersion)) {
			$this->error_handler(__METHOD__ .": database version information is invalid: (". $this->newVersion .")");
		}
		
		return($retval);
		
	}//end update_database_version()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Checks consistency of version information in the database, and optionally 
	 * against a given version string.
	 */
	protected function check_database_version() {
		//retrieve the internal version information.
		if(!is_null($this->newVersion)) {
			$data = $this->get_database_version();
			$versionString = $data['version_string'];
			
			if($versionString == $this->newVersion) {
				$retval = TRUE; 
			}
			else {
				$retval = FALSE;
			}
			
			if(!$retval) {
				$this->do_log("Version check failed, versionString=(". $versionString ."), checkVersion=(". $this->newVersion .")", 'FATAL');
			}
			
		}
		else {
			$this->error_handler(__METHOD__ .": no version string given (". $this->newVersion .")");
		}
		
		return($retval);
	}//end check_database_version()
	//=========================================================================
	
	
	
	//=========================================================================
	protected function do_scripted_upgrade(array $upgradeData) {
		$myConfigFile = $upgradeData['SCRIPT_NAME'];
		
		$this->do_log("Preparing to run script '". $myConfigFile ."'", 'debug');
		
		//we've got the filename, see if it exists.
		
		$scriptsDir = dirname($this->upgradeConfigFile);
		$fileName = $scriptsDir .'/'. $myConfigFile;
		
		if(file_exists($fileName)) {
			
			$this->do_log("(". __CLASS__ .") Performing scripted upgrade (". $myConfigFile .") from file '". $fileName ."'", 'DEBUG');
			$createClassName = $upgradeData['CLASS_NAME'];
			$classUpgradeMethod = $upgradeData['CALL_METHOD'];
			require_once($fileName);
			
			//now check to see that the class we need actually exists.
			if(class_exists($createClassName)) {
				$upgradeObj = new $createClassName($this->db);
				if(method_exists($upgradeObj, $classUpgradeMethod)) {
					$upgradeResult = $upgradeObj->$classUpgradeMethod();
					
					if($upgradeResult === true) {
						//yay, it worked!
						$this->do_log("Upgrade succeeded (". $upgradeResult .")", 'success');
					}
					else {
						$this->error_handler(__METHOD__ .": upgrade failed (". $upgradeResult .")");
					}
					$this->do_log("Finished running ". $createClassName ."::". $classUpgradeMethod ."(), result was (". $upgradeResult .")", 'debug');
				}
				else {
					$this->error_handler(__METHOD__ .": upgrade method doesn't exist (". $createClassName ."::". $classUpgradeMethod 
						."), unable to perform upgrade ");
				}
			}
			else {
				$this->error_handler(__METHOD__ .": unable to locate upgrade class name (". $createClassName .")");
			}
		}
		else {
			$this->error_handler(__METHOD__ .": upgrade filename (". $fileName .") does not exist");
		}
	}//end do_scripted_upgrade()
	//=========================================================================
	
	
	
	//=========================================================================
	public function is_higher_version($version, $checkIfHigher) {
		try {
			$retval = parent::is_higher_version($version, $checkIfHigher);
		}
		catch(exception $e) {
			$this->error_handler($e->getMessage());
		}
		
		return($retval);
		
	}//end is_higher_version()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Determines list of upgrades to perform.
	 * 
	 * If the current version is 1.0.1, the version file is 1.0.5, and there's a 
	 * scripted upgrade at 1.0.4, this will update the database version to 1.0.3, 
	 * run the scripted upgrade at 1.0.4, then update the database version to 
	 * 1.0.5 (keeps from skipping the upgrade at 1.0.4)
	 */
	protected function get_upgrade_list() {
		$this->get_database_version();
		$dbVersion = $this->databaseVersion;
		$newVersion = $this->versionFileVersion;
		
		$retval = array();
		if(!$this->is_higher_version($dbVersion, $newVersion)) {
			$this->error_handler(__METHOD__ .": version (". $newVersion .") isn't higher than (". $dbVersion .")... something is broken");
		}
		elseif(is_array($this->matchingData)) {
			$lastVersion = $dbVersion;
			foreach($this->matchingData as $matchVersion=>$data) {
				
				$matchVersion = preg_replace('/^V/', '', $matchVersion);
				if($matchVersion == $data['TARGET_VERSION']) {
					$this->error_handler(__METHOD__ .": detected invalid TARGET_VERSION in (". $matchVersion ."): make sure TARGET_VERSION is higher than matching!");
				}
				elseif($this->databaseVersion == $matchVersion || $this->is_higher_version($this->databaseVersion, $matchVersion)) {
					//the version in MATCHING is equal to or HIGHER than our database version... make sure it is NOT
					//	higher than the version in our versionFile.
					if(!$this->is_higher_version($this->versionFileVersion, $matchVersion)) {
						if(!count($retval) && $matchVersion != $this->databaseVersion) {
							$retval[$this->databaseVersion] = $matchVersion;
						}
						//the MATCHING version is NOT higher than the version file's version, looks ok.
						$lastVersion = $data['TARGET_VERSION'];
						$retval[$matchVersion] = $data['TARGET_VERSION'];
					}
					else {
						$this->do_log(__METHOD__ .": entry in upgrade.xml (". $matchVersion .") is higher than the VERSION file (". $this->versionFileVersion .")", 'warning');
					}
				}
				else {
					$this->do_log(__METHOD__ .": SKIPPING (". $matchVersion .")", 'debug');
				}
			}
			
			if($lastVersion !== $newVersion && (!isset($retval[$lastVersion]) || $retval[$lastVersion] != $newVersion)) {
				$retval[$lastVersion] = $newVersion;
			}
		}
		else {
			//no intermediary upgrades: just pass back the latest version.
			$this->do_log(__METHOD__ .": no intermediary upgrades", 'debug');
			$retval[$dbVersion] = $this->versionFileVersion;
		}
		
		return($retval);
		
	}//end get_upgrade_list()
	//=========================================================================
	
	
	
	//=========================================================================
	protected function parse_suffix($suffix) {
		$retval = NULL;
		if(strlen($suffix)) {
			//determine what kind it is.
			foreach($this->suffixList as $type) {
				if(preg_match('/^'. $type .'/', $suffix)) {
					$checkThis = preg_replace('/^'. $type .'/', '', $suffix);
					if(strlen($checkThis) && is_numeric($checkThis)) {
						//oooh... it's something like "BETA3"
						$retval = array(
							'type'		=> $type,
							'number'	=> $checkThis
						);
					}
					else {
						$this->error_handler(__METHOD__ .": invalid suffix (". $suffix .")");
					}
					break;
				}
			}
		}
		else {
			$this->error_handler(__METHOD__ .": invalid suffix (". $suffix .")");
		}
		
		return($retval);
	}//end parse_suffix()
	//=========================================================================
	
	
	
	//=========================================================================
	protected function fix_xml_config($config, $path=null) {
		$this->xmlLoops++;
		if($this->xmlLoops > 1000) {
			$this->error_handler(__METHOD__ .": infinite loop detected...");
		}
		
		try {
			$a2p = new cs_arrayToPath($config);
		}
		catch(exception $e) {
			$this->do_log(__METHOD__ .': encountered exception: '. $e->getMessage());
			$this->error_handler($e->getMessage());
		}
		if(!is_array($this->tempXmlConfig)) {	
			$this->tempXmlConfig = array();
		}
		try {
			$myA2p = new cs_arrayToPath($this->tempXmlConfig);
		}
		catch(exception $e) {
			$this->do_log(__METHOD__ .': encountered exception: '. $e->getMessage());
			$this->error_handler($e->getMessage());
		}
		
		$myData = $a2p->get_data($path);
		
		if(is_array($myData)) {
			if(isset($myData['type']) && $myData['type'] != 'open') {
				if($myData['type'] == 'complete') {
					$val = null;
					if(isset($myData['value'])) {
						$val = $myData['value'];
					}
					$myA2p->set_data($path, $val);
					$this->tempXmlConfig = $myA2p->get_data();
				}
				else {
					$this->error_handler(__METHOD__ .": invalid type (". $myData['type'] .")");
				}
			}
			else {
				foreach($myData as $i=>$d) {
					if(!in_array($i, array('type', 'attributes', 'value'))) {
						$this->fix_xml_config($config, $path .'/'. $i);
					}
				}
			}
		}
		else {
			$this->error_handler(__METHOD__ .": unable to fix data on path=(". $path .")::: ". $this->gfObj->debug_print($myData,0));
		}
	}//end fix_xml_config()
	//=========================================================================
	
	
	
	//=========================================================================cs_debug_backtrace(1);

	public function load_table() {

		$schemaFileLocation = dirname(__FILE__) .'/setup/schema.'. $this->db->get_dbtype() .'.sql';
		$schema = file_get_contents($schemaFileLocation);
		$this->db->exec($schema);
		
		$loadTableResult = $this->db->errorMsg();
		if(!strlen($loadTableResult)) {
			$loadTableResult = true;
			$logRes = 'Successfully loaded';
			$logType = 'initialize';
			
			//now set the initial version information...
			if(strlen($this->projectName) && strlen($this->versionFileVersion)) {
				$this->load_initial_version();
			}
			else {
				throw new exception(__METHOD__ .": missing projectName (". $this->projectName .") " .
						"or versionFileVersion (". $this->versionFileVersion ."), cannot load data");
			}
		}
		else {
			$logRes = 'Failed to load';
			$logType = 'error';
		}
		$this->do_log($logRes .' table ('. $this->dbTable .') into ' .
				'database::: '. $loadTableResult, $logType);
		
		return($loadTableResult);
	}//end load_table()
	//=========================================================================
	
	
	
	//=========================================================================
	protected function get_full_version_string($versionString) {
		if(strlen($versionString)) {
			$bits = $this->parse_version_string($versionString);
			
			$fullVersion = $bits['version_major'] .'.'. $bits['version_minor'] .'.'.
					$bits['version_maintenance'];
			if(strlen($bits['version_suffix'])) {
				$fullVersion .= '-'. $bits['version_suffix'];
			}
		}
		else {
			$this->error_handler(__METHOD__ .": no version string given");
		}
		
		return($fullVersion);
	}//end get_full_version_string()
	//=========================================================================
	
	
	
	//=========================================================================
	public function error_handler($details) {
		//log the error.
		if(!is_object($this->logsObj)) {
			throw new exception(__METHOD__ .": error encountered::: ". $details);
		}
		if($this->internalUpgradeInProgress === false) {
			$this->do_log($details, 'exception in code');
		}
		
		//now throw an exception so other code can catch it.
		throw new exception($details);
	}//end error_handler()
	//=========================================================================
	
	
	
	//=========================================================================
	public function load_initial_version() {
		//if there's an INITIAL_VERSION in the upgrade config file, use that.
		$this->read_upgrade_config_file();
		$insertData = array();
		if(isset($this->initialVersion) && strlen($this->initialVersion)) {
			$parseThis = $this->initialVersion;
		}
		else {
			$parseThis = $this->versionFileVersion;
		}
		
		try {
			$versionInfo = $this->parse_version_string($parseThis);
			$insertData = array(
				'projectName'		=> $this->projectName,
				'versionString'		=> $versionInfo['version_string']
			);

			$sql = 'INSERT INTO '. $this->dbTable . ' (project_name, version_string) '
					. 'VALUES (:projectName, :versionString)';
					
			if($this->db->run_insert($sql, $insertData, $this->sequenceName)) {
				$loadRes = true;
				$this->do_log("Created data for '". $this->projectName ."' with version '". $insertData['versionString'] ."'", 'initialize');
			}
			else {
				$this->error_handler(__METHOD__ .": failed to load initial version::: ". $e->getMessage());
			}
		}
		catch(Exception $e) {
			$this->error_handler(__METHOD__ .":: failed to load initial version due to exception, DETAILS::: ". $e->getMessage());
		}
		
		return($loadRes);
	}//end load_initial_version()
	//=========================================================================
	
	
	
	//=========================================================================
	protected function do_log($message, $type) {
		$this->debugLogs[] = array('project'=>$this->projectName,'upgradeFile'=>$this->upgradeConfigFile,'message'=>$message,'type'=>$type);
		if($this->internalUpgradeInProgress === true) {
			$this->storedLogs[] = func_get_args();
		}
		else {
			if(is_object($this->logsObj)) {
				$this->logsObj->log_by_class($message, $type);
			}
		}
	}//end do_log()
	//=========================================================================
	
	
}//end upgrade{}


?>
