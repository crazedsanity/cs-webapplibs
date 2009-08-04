<?php
/*
 * Created on Jul 2, 2007
 * 
 * SVN INFORMATION:::
 * ------------------
 * SVN Signature::::::: $Id$
 * Last Author::::::::: $Author$ 
 * Current Revision:::: $Revision$ 
 * Repository Location: $HeadURL$ 
 * Last Updated:::::::: $Date$
 * 
 */

require_once(constant('LIBDIR') .'/cs-versionparse/cs_version.abstract.class.php');

class cs_webdbupgrade extends cs_versionAbstract {
	
	/** cs_fileSystem{} object: for filesystem read/write operations. */
	private $fsObj;
	
	/** cs_globalFunctions{} object: debugging, array, and string operations. */
	protected $gfObj;
	
	/** Array of configuration parameters. */
	private $config = NULL;
	
	/** Name of primary key sequence of main table (for handling inserts with PostgreSQL) */
	private $sequenceName;
	
	/** Database object. */
	protected $db;
	
	/** Object used to log activity. */
	protected $logsObj;
	
	/** Name of the project as referenced in the database. */
	protected $projectName;
	
	/** Internal cache of the version string from the VERSION file. */
	private $versionFileVersion = NULL;
	
	/** Stored database version. */
	private $databaseVersion = NULL;
	
	/** Name (absolute location) of *.lock file that indicates an upgrade is running. */
	private $lockfile;
	
	/**  */
	private $allowNoDBVersion=true;
	
	/** List of acceptable suffixes; example "1.0.0-BETA3" -- NOTE: these MUST be in 
	 * an order that reflects newest -> oldest; "ALPHA happens before BETA, etc. */
	private $suffixList = array(
		'ALPHA', 	//very unstable
		'BETA', 	//kinda unstable, but probably useable
		'RC'		//all known bugs fixed, searching for unknown ones
	);
	
	//=========================================================================
	public function __construct($versionFileLocation, array $config) {
		
		//Handle the config array (cope with XML-ish array)
		if(is_array($config)) {
			//check if it is in the complex XML array style...
			$keys = array_keys($config);
			if(isset($config[$keys[0]]['type'])) {
				$this->fix_xml_config($config);
				$this->config = $this->tempXmlConfig;
			}
			else {
				$this->config = $config;
			}
		}
		else {
			throw new exception(__METHOD__ .": no configuration available");
		}
		
		//cope with problems in CS-Content v1.0-ALPHA9 (or before)--see http://project.crazedsanity.com/extern/helpdesk/view?ID=281
		if(isset($this->config['DBPARMLINKER'])) {
			$this->config['DBPARAMS'] = array();
			foreach($this->config['DBPARMLINKER'] as $i=>$loc) {
				$this->config['DBPARAMS'][strtolower($i)] = $this->config[$loc];
				unset($this->config[$loc]);
			}
			unset($this->config['DBPARMLINKER']);
		}
		
		//Check for some required constants.
		$requisiteConstants = array('LIBDIR');
		if(!defined('LIBDIR')) {
			throw new exception(__METHOD__ .": required constant 'LIBDIR' not set");
		}
		if(!defined('SITE_ROOT')) {
			throw new exception(__METHOD__ .": required constant 'SITE_ROOT' not set");
		}
		
		require_once(constant('LIBDIR') .'/cs-content/cs_globalFunctions.class.php');
		require_once(constant('LIBDIR') .'/cs-content/cs_fileSystem.class.php');
		require_once(constant('LIBDIR') .'/cs-content/cs_phpDB.class.php');
		require_once(constant('LIBDIR') .'/cs-webdblogger/cs_webdblogger.class.php');
		require_once(constant('LIBDIR') .'/cs-phpxml/cs_phpxmlParser.class.php');
		require_once(constant('LIBDIR') .'/cs-phpxml/cs_phpxmlCreator.class.php');
		require_once(constant('LIBDIR') .'/cs-phpxml/cs_arrayToPath.class.php');
		
		$this->versionFileLocation = $versionFileLocation;
		
		$this->fsObj =  new cs_fileSystem(constant('SITE_ROOT'));
		$this->gfObj = new cs_globalFunctions;
		if(defined('DEBUGPRINTOPT')) {
			$this->gfObj->debugPrintOpt = constant('DEBUGPRINTOPT');
		}
		
		if(!isset($this->config['DB_PRIMARYKEY']) || !isset($this->config['DB_TABLE'])) {
			throw new exception(__METHOD__ .": no setting for DB_TABLE or DB_PRIMARYKEY, cannot continue");
		}
		$this->sequenceName = $this->config['DB_TABLE'] .'_'. $this->config['DB_PRIMARYKEY'] .'_seq';
		
		if(!defined('DBTYPE')) {
			throw new exception(__METHOD__ .": required constant 'DBTYPE' not set");
		}
		if(!isset($this->config['CONFIG_FILE_LOCATION'])) {
			throw new exception(__METHOD__ .": required setting 'CONFIG_FILE_LOCATION' not found");
		}
		if(!strlen($versionFileLocation) || !file_exists($versionFileLocation)) {
			throw new exception(__METHOD__ .": unable to locate version file (". $versionFileLocation .")");
		}
		if(!isset($this->config['RWDIR']) || !is_dir($this->config['RWDIR']) || !is_readable($this->config['RWDIR']) || !is_writable($this->config['RWDIR'])) {
			throw new exception(__METHOD__ .": missing RWDIR (". $this->config['RWDIR'] .") or isn't readable/writable");
		}
		$this->lockfile = $this->config['RWDIR'] .'/upgrade.lock';
		
		$this->db = new cs_phpDB(constant('DBTYPE'));
		try {
			$this->db->connect($this->config['DBPARAMS']);
		}
		catch(exception $e) {
			throw new exception(__METHOD__ .": failed to connect to database or logger error: ". $e->getMessage());
		}
		
		if($this->check_lockfile()) {
			//there is an existing lockfile...
			$this->error_handler(__METHOD__ .": upgrade in progress: ". $this->fsObj->read($this->lockfile));
		}
		
		$this->check_internal_upgrades();
		try {	
			$this->connect_logger("Upgrade");
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
	private function check_internal_upgrades() {
		$oldVersionFileLocation = $this->versionFileLocation;
		$oldUpgradeConfigFile = $this->config['UPGRADE_CONFIG_FILE'];
		$this->config['UPGRADE_CONFIG_FILE'] = dirname(__FILE__) .'/upgrades/upgrade.xml';
		
		
		//connect the logger...
		try {
			$this->connect_logger("Internal Upgrade");
		}
		catch(exception $e) {
			$this->error_handler($e->getMessage());
		}
		
		
		//do stuff here...
		$this->versionFileLocation = dirname(__FILE__) .'/VERSION';
		$this->read_version_file();
		
		//if there is an error, then... uh... yeah.
		try {
			$this->get_database_version();
		}
		catch(exception $e) {
			#throw new exception(__METHOD__ .": error while retrieving database version: ". $e->getMessage());
			
			//try creating the version.
			$this->load_initial_version();
		}
		
		//do upgrades here...
		$this->check_versions(true);
		
		
		
		
		//reset internal vars.
		$this->versionFileLocation = $oldVersionFileLocation;
		$this->config['UPGRADE_CONFIG_FILE'] = $oldUpgradeConfigFile;
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
			$this->logsObj->log_by_class("Upgrade in progress", 'notice');
			throw new exception(__METHOD__ .": upgrade in progress");
		}
		else {
			//okay, all files present: check the version in the VERSION file.
			$versionFileVersion = $this->read_version_file();
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
		
		return($retval);
	}//end read_version_file()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Read information from our config file, so we know what to expect.
	 */
	private function read_upgrade_config_file() {
		try {
			$xmlString = $this->fsObj->read($this->config['UPGRADE_CONFIG_FILE']);
		}
		catch(exception $e) {
			throw new exception(__METHOD__ .": failed to read upgrade config file::: ". $e->getMessage());
		}
		
		//parse the file.
		$xmlParser = new cs_phpxmlParser($xmlString);
		
		$config = $xmlParser->get_tree(TRUE);
		
		if(is_array($config['UPGRADE']) && count($config['UPGRADE'])) {
			$this->config['UPGRADELIST'] = $config['UPGRADE'];
		}
		else {
			$this->error_handler(__METHOD__ .": failed to retrieve 'UPGRADE' section; " .
					"make sure upgrade.xml's ROOT element is 'UPGRADE'");
		}
	}//end read_upgrade_config_file()
	//=========================================================================
	
	
	
	//=========================================================================
	private function perform_upgrade() {
		//make sure there's not already a lockfile.
		if($this->upgrade_in_progress()) {
			//ew.  Can't upgrade.
			$this->error_handler(__METHOD__ .": upgrade already in progress...????");
		}
		else {
			$lockConfig = $this->upgrade_in_progress(TRUE);
			$this->fsObj->cd("/");
			
			$this->logsObj->log_by_class("Starting upgrade process...", 'begin');
			
			//TODO: not only should the "create_file()" method be run, but also do a sanity check by calling lock_file_exists().
			if($lockConfig === 0) {
				//can't create the lockfile.  Die.
				$this->error_handler(__METHOD__ .": failed to set 'upgrade in progress'");
			}
			else {
				$this->logsObj->log_by_class(__METHOD__ .": result of creating lockfile: (". $lockConfig .")", 'debug');
				
				//push data into our internal "config" array.
				$this->read_upgrade_config_file();
				$this->get_database_version();
				
				//check for version conflicts.
				$versionConflictInfo = $this->check_for_version_conflict();
				
				
				if($versionConflictInfo !== false) {
					$this->logsObj->log_by_class("Upgrading ". $versionConflictInfo ." versions, from " .
							"(". $this->databaseVersion .") to (". $this->versionFileVersion .")", 'info');
				}
				
				$upgradeList = $this->get_upgrade_list();
				
				try {
					$i=0;
					$this->logsObj->log_by_class(__METHOD__ .": starting to run through the upgrade list, starting at (". $this->databaseVersion ."), " .
							"total number of upgrades to perform: ". count($upgradeList), 'debug');
					$this->db->beginTrans(__METHOD__);
					foreach($upgradeList as $fromVersion=>$toVersion) {
						
						$details = __METHOD__ .": upgrading from ". $fromVersion ." to ". $toVersion ."... ";
						$this->logsObj->log_by_class($details, 'system');
						$this->do_single_upgrade($fromVersion, $toVersion);
						$this->get_database_version();
						$i++;
						
						$details = __METHOD__ .": finished upgrade #". $i .", now at version (". $this->databaseVersion .")";
						$this->logsObj->log_by_class($details, 'system');
					}
					
					if($i < count($upgradeList)) {
						$this->logsObj->log_by_class(__METHOD__ .": completed upgrade ". $i ." of ". count($upgradeList), 'debug');
					}
					else {
						if($this->databaseVersion == $this->versionFileVersion) {
							$this->logsObj->log_by_class(__METHOD__ .": finished upgrading after performing (". $i .") upgrades", 'debug');
							$this->newVersion = $this->databaseVersion;
						}
						else {
							$this->logsObj->log_by_class(__METHOD__ .": upgradeList::: ". $this->gfObj->debug_print($upgradeList,0), 'debug');
							$this->error_handler(__METHOD__ .": finished upgrade, but version wasn't updated (expecting '". $this->versionFileVersion ."', got '". $this->databaseVersion ."')!!!");
						}
					}
					$this->remove_lockfile();
					
					$this->db->commitTrans();
				}
				catch(exception $e) {
					$this->error_handler(__METHOD__ .": upgrade aborted:::". $e->getMessage());
					$this->db->rollbackTrans();
				}
				$this->logsObj->log_by_class("Upgrade process complete", 'end');
			}
		}
	}//end perform_upgrade()
	//=========================================================================
	
	
	
	//=========================================================================
	public function upgrade_in_progress($makeItSo=FALSE) {
		if($makeItSo === TRUE) {
			if(strlen($this->databaseVersion)) {
				$details = $this->projectName .': Upgrade from '. $this->databaseVersion .' started at '. date('Y-m-d H:i:s');
				$this->create_lockfile($details);
				$retval = TRUE;
			}
			else {
				$this->error_handler(__METHOD__ .": missing internal databaseVersion (". $this->databaseVersion .")");
			}
		}
		$retval = $this->check_lockfile();
		
		return($retval);
	}//end upgrade_in_progress()
	//=========================================================================
	
	
	
	//=========================================================================
	public function parse_version_string($versionString) {
		if(is_null($versionString) || !strlen($versionString)) {
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
	private function check_for_version_conflict() {
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
					$this->error_handler(__METHOD__ .": downgrading minor versions is unsupported");
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
	private function get_database_version() {
		$this->gfObj->debugPrintOpt=1;
		//create a database object & attempt to read the database version.
		
		$sql = "SELECT * FROM ". $this->config['DB_TABLE'] ." WHERE project_name='" .
				$this->gfObj->cleanString($this->projectName, 'sql') ."'";
		
		$numrows = $this->db->exec($sql);
		$dberror = $this->db->errorMsg();
		
		if(strlen($dberror) || $numrows != 1) {
			//
			if(preg_match('/doesn\'t exist/', $dberror) || preg_match('/does not exist/', $dberror)) {
				//add the table...
				$loadTableResult = $this->load_table();
				if($loadTableResult === TRUE) {
					//now try the SQL...
					$numrows = $this->db->exec($sql);
					$dberror = $this->db->errorMsg();
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
			$data = $this->db->farray_fieldnames();
			$this->databaseVersion = $data['version_string'];
			$retval = $this->parse_version_string($data['version_string']);
		}
		
		return($retval);
	}//end get_database_version()
	//=========================================================================
	
	
	
	//=========================================================================
	private function do_single_upgrade($fromVersion, $toVersion=null) {
		//Use the "matching_syntax" data in the upgrade.xml file to determine the filename.
		$versionIndex = "V". $this->get_full_version_string($fromVersion);
		if(!isset($this->config['UPGRADELIST']['MATCHING'][$versionIndex])) {
			//version-only upgrade.
			$this->newVersion = $toVersion;
			$this->update_database_version($toVersion);
		}
		else {
			//scripted upgrade...
			$scriptIndex = $versionIndex;
			
			$upgradeData = $this->config['UPGRADELIST']['MATCHING'][$versionIndex];
			
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
		$this->logsObj->log_by_class("Finished upgrade to ". $this->newVersion, 'system');
	}//end do_single_upgrade()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Updates information that's stored in the database, internal to cs-project, 
	 * so the version there is consistent with all the others.
	 */
	protected function update_database_version($newVersionString) {
		$versionArr = $this->parse_version_string($newVersionString);
		
		$queryArr = array();
		foreach($versionArr as $index=>$value) {
			$queryArr[$index] = "SELECT internal_data_set_value('". $index ."', '". $value ."');";
		}
		
		$updateData = $versionArr;
		$sql = "UPDATE ". $this->config['DB_TABLE'] ." SET ". 
				$this->gfObj->string_from_array($updateData, 'update', null, 'sql') ." WHERE " .
				"project_name='". $this->gfObj->cleanString($this->projectName, 'sql') ."'";
		
		
		$updateRes = $this->db->run_update($sql,false);
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
	private function check_database_version() {
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
				$this->logsObj->log_by_class("Version check failed, versionString=(". $versionString ."), checkVersion=(". $this->newVersion .")", 'FATAL');
			}
			
		}
		else {
			$this->error_handler(__METHOD__ .": no version string given (". $this->newVersion .")");
		}
		
		return($retval);
	}//end check_database_version()
	//=========================================================================
	
	
	
	//=========================================================================
	private function do_scripted_upgrade(array $upgradeData) {
		$myConfigFile = $upgradeData['SCRIPT_NAME'];
		
		$this->logsObj->log_by_class("Preparing to run script '". $myConfigFile ."'", 'debug');
		
		//we've got the filename, see if it exists.
		if(isset($this->config['UPGRADE_SCRIPTS_DIR'])) {
			$scriptsDir = $this->config['UPGRADE_SCRIPTS_DIR'];
		}
		else {
			$this->logsObj->log_by_class("No UPGRADE_SCRIPTS_DIR config setting", 'warning');
			$scriptsDir = dirname($this->config['UPGRADE_CONFIG_FILE']);
		}
		$fileName = $scriptsDir .'/'. $myConfigFile;
		if(file_exists($fileName)) {
		
			$this->logsObj->log_by_class("Performing scripted upgrade (". $myConfigFile .")", 'DEBUG');
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
						$this->logsObj->log_by_class("Upgrade succeeded (". $upgradeResult .")", 'success');
					}
					else {
						$this->error_handler(__METHOD__ .": upgrade failed (". $upgradeResult .")");
					}
					$this->logsObj->log_by_class("Finished running ". $createClassName ."::". $classUpgradeMethod ."(), result was (". $upgradeResult .")", 'debug');
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
	private function get_upgrade_list() {
		$this->get_database_version();
		$dbVersion = $this->databaseVersion;
		$newVersion = $this->versionFileVersion;
		
		$retval = array();
		if(!$this->is_higher_version($dbVersion, $newVersion)) {
			$this->error_handler(__METHOD__ .": version (". $newVersion .") isn't higher than (". $dbVersion .")... something is broken");
		}
		elseif(is_array($this->config['UPGRADELIST']['MATCHING'])) {
			$lastVersion = $dbVersion;
			foreach($this->config['UPGRADELIST']['MATCHING'] as $matchVersion=>$data) {
				
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
						$this->logsObj->log_by_class(__METHOD__ .": entry in upgrade.xml (". $matchVersion .") is higher than the VERSION file (". $this->versionFileVersion .")", 'warning');
					}
				}
				else {
					$this->logsObj->log_by_class(__METHOD__ .": SKIPPING (". $matchVersion .")", 'debug');
				}
			}
			
			if($lastVersion !== $newVersion && (!isset($retval[$lastVersion]) || $retval[$lastVersion] != $newVersion)) {
				$retval[$lastVersion] = $newVersion;
			}
		}
		else {
			//no intermediary upgrades: just pass back the latest version.
			$this->logsObj->log_by_class(__METHOD__ .": no intermediary upgrades", 'debug');
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
	private function fix_xml_config($config, $path=null) {
		$this->xmlLoops++;
		if($this->xmlLoops > 1000) {
			$this->error_handler(__METHOD__ .": infinite loop detected...");
		}
		
		try {
			$a2p = new cs_arrayToPath($config);
		}
		catch(exception $e) {
			$this->logsObj->log_by_class(__METHOD__ .': encountered exception: '. $e->getMessage());
			$this->error_handler($e->getMessage());
		}
		if(!is_array($this->tempXmlConfig)) {	
			$this->tempXmlConfig = array();
		}
		try {
			$myA2p = new cs_arrayToPath(&$this->tempXmlConfig);
		}
		catch(exception $e) {
			$this->logsObj->log_by_class(__METHOD__ .': encountered exception: '. $e->getMessage());
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
					$oldData = $myA2p->get_data();
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
	
	
	
	//=========================================================================
	public function load_table() {
		$schemaFileLocation = dirname(__FILE__) .'/schema/schema.sql';
		$schema = file_get_contents($schemaFileLocation);
		$schema = str_replace('{tableName}', $this->config['DB_TABLE'], $schema);
		$schema = str_replace('{primaryKey}', $this->config['DB_PRIMARYKEY'], $schema);
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
		$this->logsObj->log_by_class($logRes .' table ('. $this->config['DB_TABLE'] .') into ' .
				'database::: '. $loadTableResult, $logType);
		
		return($loadTableResult);
	}//end load_table()
	//=========================================================================
	
	
	
	//=========================================================================
	public function check_lockfile() {
		$status = false;
		if(file_exists($this->lockfile)) {
			$status = true;
		}
		
		return($status);
	}//end check_lockfile()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Create a *.lock file that indicates the system is in the process of 
	 * performing an upgrade (safer than always updating the site's configuration 
	 * file).
	 */
	public function create_lockfile($contents) {
		if(!$this->check_lockfile()) {
			if($this->fsObj->create_file($this->lockfile)) {
				if(!preg_match('/\n$/', $contents)) {
					$contents .= "\n";
				}
				$writeRes = $this->fsObj->write($contents);
				if(is_numeric($writeRes) && $writeRes > 0) {
					$this->fsObj->closeFile();
				}
				else {
					$this->error_handler(__METHOD__ .": failed to write contents (". $contents .") to lockfile");
				}
			}
			else {
				$this->error_handler(__METHOD__ .": failed to create lockfile (". $this->lockfile .")");
			}
		}
		else {
			$this->error_handler(__METHOD__ .": failed to create lockfile, one already exists (". $this->lockfile .")");
		}
	}//end create_lockfile()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Destroy the *.lock file that indicates an upgrade is underway.
	 */
	private function remove_lockfile() {
		if($this->check_lockfile()) {
			if(!$this->fsObj->rm($this->lockfile)) {
				$this->error_handler(__METHOD__ .": failed to remove lockfile (". $this->lockfile .")");
			}
		}
		else {
			$this->error_handler(__METHOD__ .": no lockfile (". $this->lockfile .")");
		}
	}//end remove_lockfile()
	//=========================================================================
	
	
	
	//=========================================================================
	private function get_full_version_string($versionString) {
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
		$this->logsObj->log_by_class($details, 'exception in code');
		
		//now throw an exception so other code can catch it.
		throw new exception($details);
	}//end error_handler()
	//=========================================================================
	
	
	
	//=========================================================================
	public function load_initial_version() {
		//if there's an INITIAL_VERSION in the upgrade config file, use that.
		$this->read_upgrade_config_file();
		if(isset($this->config['UPGRADELIST']['INITIALVERSION'])) {
			$insertData = $this->parse_version_string($this->config['UPGRADELIST']['INITIALVERSION']);
		}
		else {
			$insertData = $this->parse_version_string($this->versionFileVersion);
		}
		$insertData['project_name'] = $this->projectName;
		
		$sql = 'INSERT INTO '. $this->config['DB_TABLE'] . $this->gfObj->string_from_array($insertData, 'insert');
		
		if($this->db->run_insert($sql, $this->sequenceName)) {
			$loadRes = true;
			$this->logsObj->log_by_class("Created data for '". $this->projectName ."' with version '". $insertData['version_string'] ."'", 'initialize');
		}
		else {
			$this->error_handler(__METHOD__ .": failed to load initial version::: ". $e->getMessage());
		}
		
		return($loadRes);
	}//end load_initial_version()
	//=========================================================================
	
	
	
	//=========================================================================
	private function connect_logger($logCategory) {
		$loggerDb = new cs_phpDB(constant('DBTYPE'));
		$loggerDb->connect($this->config['DBPARAMS'], true);
		$this->logsObj = new cs_webdblogger($loggerDb, $logCategory);
	}//end connect_logger()
	//=========================================================================
	
	
}//end upgrade{}


?>