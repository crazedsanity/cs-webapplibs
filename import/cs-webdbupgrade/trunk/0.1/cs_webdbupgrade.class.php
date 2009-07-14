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

class cs_webdbupgrade {
	
	private $fsObj;
	private $gfObj;
	private $config = NULL;
	protected $db;
	protected $logsObj;
	
	/** Name of the project as referenced in the database. */
	protected $projectName;
	
	private $versionFileVersion = NULL;
	private $databaseVersion = NULL;
	
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
		
		require_once(constant('LIBDIR') .'/cs-content/cs_globalFunctions.class.php');
		require_once(constant('LIBDIR') .'/cs-content/cs_fileSystem.class.php');
		require_once(constant('LIBDIR') .'/cs-content/cs_phpDB.class.php');
		require_once(constant('LIBDIR') .'/cs-webdblogger/cs_webdblogger.class.php');
		require_once(constant('LIBDIR') .'/cs-phpxml/cs_phpxmlParser.class.php');
		require_once(constant('LIBDIR') .'/cs-phpxml/cs_arrayToPath.class.php');
		
		$this->versionFileLocation = $versionFileLocation;
		
		$this->fsObj =  new cs_fileSystem(dirname($this->versionFileLocation));
		$this->gfObj = new cs_globalFunctions;
		$this->gfObj->debugPrintOpt = DEBUGPRINTOPT;
		
		if(!defined('DBTYPE')) {
			throw new exception(__METHOD__ .": required constant 'DBTYPE' not set");
		}
		if(!isset($this->config['CONFIG_FILE_LOCATION'])) {
			throw new exception(__METHOD__ .": required setting 'CONFIG_FILE_LOCATION' not found");
		}
		if(!strlen($versionFileLocation) || !file_exists($versionFileLocation)) {
			throw new exception(__METHOD__ .": unable to locate version file (". $versionFileLocation .")");
		}
		
		$this->db = new cs_phpDB(constant('DBTYPE'));
		try {
			$this->db->connect($this->config['DBPARAMS']);
			$this->logsObj = new cs_webdblogger($this->db, "Upgrade");
		}
		catch(exception $e) {
			throw new exception(__METHOD__ .": failed to connect to database or logger error: ". $e->getMessage());
		}
		
		$this->check_versions(false);
	}//end __construct()
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
		elseif(!file_exists($this->config['CONFIG_FILE_LOCATION'])) {
			throw new exception(__METHOD__ .": config file (". $this->config['CONFIG_FILE_LOCATION'] .") missing");
		}
		elseif(!file_exists($this->versionFileLocation)) {
			$this->logsObj->log_by_class("VERSION file missing", 'error');
			throw new exception(__METHOD__ .": VERSION file missing");
		}
		else {
			//okay, all files present: check the version in the VERSION file.
			$versionFileVersion = $this->read_version_file();
			$dbVersion = $this->get_database_version();
			
			$versionsDiffer = TRUE;
			$retval = FALSE;
			
			if($this->check_for_version_conflict() == 0) {
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
			$this->versionFileVersion = $retval;
			
			//now retrieve the PROJECT name.
			$projectMatches = array();
			preg_match_all('/\nPROJECT: (.*)\n/', $versionFileContents, $projectMatches);
			if(count($projectMatches) == 2 && count($projectMatches[1]) == 1) {
				$this->projectName = trim($projectMatches[1][0]);
			}
			else {
				throw new exception(__METHOD__ .": failed to find PROJECT name");
			}
		}
		else {
			throw new exception(__METHOD__ .": could not find VERSION data");
		}
		
		return($retval);
	}//end read_version_file()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Read information from our config file, so we know what to expect.
	 */
	private function read_upgrade_config_file() {
		$xmlString = $this->fsObj->read("upgrade/upgrade.xml");
		
		//parse the file.
		$xmlParser = new xmlParser($xmlString);
		
		$config = $xmlParser->get_tree(TRUE);
		$this->config = $config['UPGRADE'];
	}//end read_upgrade_config_file()
	//=========================================================================
	
	
	
	//=========================================================================
	private function perform_upgrade() {
		//make sure there's not already a lockfile.
		if($this->upgrade_in_progress()) {
			//ew.  Can't upgrade.
			throw new exception(__METHOD__ .": upgrade already in progress...????");
		}
		else {
			$lockConfig = $this->upgrade_in_progress(TRUE);
			$this->fsObj->cd("/");
			
			//TODO: not only should the "create_file()" method be run, but also do a sanity check by calling lock_file_exists().
			if($lockConfig === 0) {
				//can't create the lockfile.  Die.
				throw new exception(__METHOD__ .": failed to set 'upgrade in progress'");
			}
			else {
				$this->gfObj->debug_print(__METHOD__ .": result of setting 'upgrade in progress': (". $lockConfig .")");
				
				//check to see if our config file is writable.
				if(!$this->fsObj->is_writable(CONFIG_FILE_LOCATION)) {
					throw new exception(__METHOD__ .": config file isn't writable!");
				}
				
				//push data into our internal "config" array.
				$this->read_upgrade_config_file();
				$this->get_database_version();
				
				//check for version conflicts.
				$this->check_for_version_conflict();
				
				$upgradeList = $this->get_upgrade_list();
				
				$i=0;
				$this->gfObj->debug_print(__METHOD__ .": starting to run through the upgrade list, starting at (". $this->databaseVersion .")...");
				$this->db->beginTrans(__METHOD__);
				foreach($upgradeList as $fromVersion=>$toVersion) {
					
					$details = __METHOD__ .": upgrading from ". $fromVersion ." to ". $toVersion ."... ";
					$this->gfObj->debug_print($details);
					$this->logsObj->log_by_class($details, 'system');
					$this->do_single_upgrade($fromVersion);
					$this->get_database_version();
					$i++;
					
					$details = __METHOD__ .": finished upgrade #". $i .", now at version (". $this->databaseVersion .")";
					$this->gfObj->debug_print($details);
					$this->logsObj->log_by_class($details, 'system');
				}
				
				if($this->databaseVersion == $this->versionFileVersion) {
					$this->gfObj->debug_print(__METHOD__ .": finished upgrading after performing (". $i .") upgrades!!!");
					$this->newVersion = $this->databaseVersion;
				}
				else {
					throw new exception(__METHOD__ .": finished upgrade, but version wasn't updated (expecting '". $this->versionFileVersion ."', got '". $this->databaseVersion ."')!!!");
				}
				$this->update_config_file('version_string', $this->newVersion);
				$this->update_config_file('workingonit', "0");
				
				$this->db->commitTrans();
			}
		}
	}//end perform_upgrade()
	//=========================================================================
	
	
	
	//=========================================================================
	public function upgrade_in_progress($makeItSo=FALSE) {
		$retval = FALSE;
		if($makeItSo === TRUE) {
			$this->get_database_version();
			$details = 'Upgrade from '. $this->databaseVersion .' started at '. date('Y-m-d H:i:s');
			$this->update_config_file('WORKINGONIT', $details);
			$retval = TRUE;
		}
		elseif(preg_match('/^upgrade/i', $this->mainConfig['WORKINGONIT'])) {
			$retval = TRUE;
		}
		
		return($retval);
	}//end upgrade_in_progress()
	//=========================================================================
	
	
	
	//=========================================================================
	public function parse_version_string($versionString) {
		if(is_null($versionString) || !strlen($versionString)) {
			throw new exception(__METHOD__ .": invalid version string ($versionString)");
		}
		
		$suffix = "";
		if(preg_match('/-[A-Z]{2,5}[0-9]{1,}/', $versionString)) {
			$bits = explode('-', $versionString);
			$suffix = $bits[1];
			$versionString = $bits[0];
		}
		$tmp = explode('.', $versionString);
		
		
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
		}
		else {
			throw new exception(__METHOD__ .": invalid version string format, requires MAJOR.MINOR syntax (". $versionString .")");
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
			$retval = 0;
		}
		else {
			//NOTE: this seems very convoluted, but it works.
			if($versionFileData['version_major'] == $dbVersion['version_major']) {
				if($versionFileData['version_minor'] == $dbVersion['version_minor']) {
					if($versionFileData['version_maintenance'] == $dbVersion['version_maintenance']) {
						if($versionFileData['version_suffix'] == $dbVersion['version_suffix']) {
							throw new exception(__METHOD__ .": no version upgrade detected, but version strings don't match (versionFile=". $versionFileData['version_string'] .", dbVersion=". $dbVersion['version_string'] .")");
						}
						else {
							$retval = "suffix";
						}
					}
					elseif($versionFileData['version_maintenance'] > $dbVersion['version_maintenance']) {
						$retval = "maintenance";
					}
					else {
						throw new exception(__METHOD__ .": downgrading from maintenance versions is unsupported");
					}
				}
				elseif($versionFileData['version_minor'] > $dbVersion['version_minor']) {
					$retval = "minor";
				}
				else {
					throw new exception(__METHOD__ .": downgrading minor versions is unsupported");
				}
			}
			elseif($versionFileData['version_major'] > $dbVersion['version_major']) {
				$retval = "major";
			}
			else {
				throw new exception(__METHOD__ .": downgrading major versions is unsupported");
			}
		}
		
		if(!is_null($retval) && $retval !== 0) {
			$this->gfObj->debug_print(__METHOD__ .": upgrading ". $retval ." versions, from (". $this->databaseVersion .") to (". $this->versionFileVersion .")");
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
			if(preg_match('/doesn\'t exist/', $dberror)) {
				//add the table...
				$loadTableResult = $this->load_table();
				if($loadTableResult === TRUE) {
					//now try the SQL...
					$numrows = $this->db->exec($sql);
					$dberror = $this->db->errorMsg();
				}
				else {
					throw new exception(__METHOD__ .": no table in database, failed to create one... ORIGINAL " .
						"ERROR: ". $dberror .", SCHEMA LOAD ERROR::: ". $loadTableResult);
				}
			}
			else {
				throw new exception(__METHOD__ .": failed to retrieve version... numrows=(". $numrows ."), DBERROR::: ". $dberror);
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
	private function do_single_upgrade($targetVersion) {
		//Use the "matching_syntax" data in the upgrade.xml file to determine the filename.
		$versionIndex = "V". $targetVersion;
		$this->gfObj->debug_print(__METHOD__ .": versionIndex=(". $versionIndex ."), config MATCHING::: ". $this->gfObj->debug_print($this->config['MATCHING'],0));
		if(!isset($this->config['MATCHING'][$versionIndex])) {
			//version-only upgrade.
			$this->update_database_version($this->versionFileVersion);
			$this->newVersion = $this->versionFileVersion;
			$this->gfObj->debug_print(__METHOD__ .": doing version-only upgrade...");
		}
		else {
			$scriptIndex = $versionIndex;
			
			$upgradeData = $this->config['MATCHING'][$versionIndex];
			
			if(isset($upgradeData['TARGET_VERSION']) && count($upgradeData) > 1) {
				$this->newVersion = $upgradeData['TARGET_VERSION'];
				if(isset($upgradeData['SCRIPT_NAME']) && isset($upgradeData['CLASS_NAME']) && isset($upgradeData['CALL_METHOD'])) {
					//good to go; it's a scripted upgrade.
					$this->do_scripted_upgrade($upgradeData);
					$this->update_database_version($upgradeData['TARGET_VERSION']);
				}
				else {
					throw new exception(__METHOD__ .": not enough information to run scripted upgrade for ". $versionIndex);
				}
			}
			else {
				throw new exception(__METHOD__ .": target version not specified, unable to proceed with upgrade for ". $versionIndex);
			}
		}
		$this->gfObj->debug_print(__METHOD__ .": done... ");
	}//end do_single_upgrade()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Updates information that's stored in the database, internal to cs-project, 
	 * so the version there is consistent with all the others.
	 */
	protected function update_database_version($newVersionString) {
		$this->gfObj->debug_print(__METHOD__ .": setting (". $newVersionString .")");
		$versionArr = $this->parse_version_string($newVersionString);
		
		$queryArr = array();
		foreach($versionArr as $index=>$value) {
			$queryArr[$index] = "SELECT internal_data_set_value('". $index ."', '". $value ."');";
		}
		
		$retval = NULL;
		foreach($queryArr as $name=>$sql) {
			if($this->run_sql($sql, 1)) {
				$retval++;
			}
		}
		
		//okay, now check that the version string matches the updated bits.
		if(!$this->check_database_version($this->newVersion)) {
			throw new exception(__METHOD__ .": database version information is invalid: (". $this->newVersion .")");
		}
		
		return($retval);
		
	}//end update_database_version()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Checks consistency of version information in the database, and optionally 
	 * against a given version string.
	 */
	private function check_database_version($checkThisVersion=NULL) {
		//retrieve the internal version information.
		$sql = "select internal_data_get_value('version_string') as version_string, (" .
			"internal_data_get_value('version_major') || '.' || " .
			"internal_data_get_value('version_minor') || '.' || " .
			"internal_data_get_value('version_maintenance')) as check_version, " .
			"internal_data_get_value('version_suffix') AS version_suffix";
		
		$retval = NULL;
		if($this->run_sql($sql,1)) {
			$data = $this->db->farray_fieldnames();
			$versionString = $data['version_string'];
			$checkVersion = $data['check_version'];
			
			if(strlen($data['version_suffix'])) {
				//the version string already would have this, but the checked version wouldn't.
				$checkVersion .= "-". $data['version_suffix'];
			}
			
			if($versionString == $checkVersion) {
				$retval = TRUE; 
			}
			else {
				$retval = FALSE;
			}
		}
		else {
			$retval = FALSE;
		}
		
		if(!$retval) {
			$this->gfObj->debug_print($data);
			$this->gfObj->debug_print(__METHOD__ .": versionString=(". $versionString ."), checkVersion=(". $checkVersion .")");
		}
		
		return($retval);
		
	}//end check_database_version()
	//=========================================================================
	
	
	
	//=========================================================================
	private function do_scripted_upgrade(array $upgradeData) {
		$myConfigFile = $upgradeData['SCRIPT_NAME'];
		
		$this->gfObj->debug_print(__METHOD__ .": script name=(". $myConfigFile .")");
		
		//we've got the filename, see if it exists.
		$fileName = UPGRADE_DIR .'/'. $myConfigFile;
		if(file_exists($fileName)) {
			$this->gfObj->debug_print(__METHOD__ .": file exists... ");
			$createClassName = $upgradeData['CLASS_NAME'];
			$classUpgradeMethod = $upgradeData['CALL_METHOD'];
			require_once($fileName);
			
			//now check to see that the class we need actually exists.
			if(class_exists($createClassName)) {
				$upgradeObj = new $createClassName($this->db);
				if(method_exists($upgradeObj, $classUpgradeMethod)) {
					$upgradeResult = $upgradeObj->$classUpgradeMethod();
					$this->gfObj->debug_print(__METHOD__ .": finished running ". $createClassName ."::". $classUpgradeMethod ."(), result was (". $upgradeResult .")");
				}
				else {
					throw new exception(__METHOD__ .": upgrade method doesn't exist (". $createClassName ."::". $classUpgradeMethod 
						."), unable to perform upgrade ");
				}
			}
			else {
				throw new exception(__METHOD__ .": unable to locate upgrade class name (". $createClassName .")");
			}
		}
		else {
			throw new exception(__METHOD__ .": upgrade filename (". $fileName .") does not exist");
		}
	}//end do_scripted_upgrade()
	//=========================================================================
	
	
	
	//=========================================================================
	protected function run_sql($sql, $expectedNumrows=1) {
		if(!$this->db->is_connected()) {
			$this->db->connect(get_config_db_params());
		}
		$numrows = $this->db->exec($sql);
		$dberror = $this->db->errorMsg();
		
		if(strlen($dberror)) {
			$details = "DBERROR::: ". $dberror;
			throw new exception(__METHOD__ .": SQL FAILED::: ". $sql ."\n\nDETAILS: ". $details);
		}
		elseif(!is_null($expectedNumrows) && $numrows != $expectedNumrows) {
			throw new exception(__METHOD__ .": SQL FAILED::: ". $sql ."\n\nDETAILS: " .
				"rows affected didn't match expectation (". $numrows ." != ". $expectedNumrows .")");
		}
		elseif(is_null($expectedNumrows) && $numrows < 1) {
			throw new exception(__METHOD__ .": SQL FAILED::: ". $sql ."\n\nDETAILS: " .
				"invalid number of rows affected (". $numrows .")");
		}
		else {
			$retval = TRUE;
		}
		
		return($retval);
	}//end run_sql()
	//=========================================================================
	
	
	
	//=========================================================================
	private function update_config_file($index, $value) {
		$gf = new cs_globalFunctions;
		$myConfigFile = CONFIG_FILE_LOCATION;
		$fs = new cs_fileSystemClass(dirname(__FILE__) .'/../');
		$xmlParser = new XMLParser($fs->read($myConfigFile));
		$xmlCreator = new XMLCreator;
		$xmlCreator->load_xmlparser_data($xmlParser);
		
		//update the given index.
		$xmlCreator->add_tag($index, $value, $xmlParser->get_attribute('/CONFIG/'. strtoupper($index)));
		$this->mainConfig[strtoupper($index)] = $value;
		
		$xmlString = $xmlCreator->create_xml_string();
		
		//truncate the file, to avoid problems with extra data at the end...
		$fs->closeFile();
		$fs->create_file($myConfigFile,TRUE);
		$fs->openFile($myConfigFile);
		
		//now write the new configuration.
		$fs->write($xmlString, $myConfigFile);
	}//end update_config_file()
	//=========================================================================
	
	
	
	//=========================================================================
	protected function get_num_users_to_convert() {
		
		$retval = 0;
		try {
			//if this generates an error, there are no users...
			$this->run_sql("SELECT internal_data_get_value('users_to_convert')");
			$data = $this->db->farray();
			$retval = $data[0];
		}
		catch(exception $e) {
			$this->gfObj->debug_print(__METHOD__ .": failed to retrieve users to convert: ");
		}
		
		return($retval);
		
	}//end get_num_users_to_convert()
	//=========================================================================
	
	
	
	//=========================================================================
	protected function update_num_users_to_convert() {
		$retval = $this->run_sql("SELECT internal_data_set_value('users_to_convert', (select count(*) FROM user_table WHERE length(password) != 32))");
		return($retval);
	}//end update_num_users_to_convert()
	//=========================================================================
	
	
	
	//=========================================================================
	protected function is_higher_version($version, $checkIfHigher) {
		$retval = FALSE;
		if(!is_string($version) || !is_string($checkIfHigher)) {
			throw new exception(__METHOD__ .": didn't get strings... ". debug_print(func_get_args(),0));
		}
		elseif($version == $checkIfHigher) {
			$retval = FALSE;
		}
		else {
			$curVersionArr = $this->parse_version_string($version);
			$checkVersionArr = $this->parse_version_string($checkIfHigher);
			
			unset($curVersionArr['version_string'], $checkVersionArr['version_string']);
			
			
			$curVersionSuffix = $curVersionArr['version_suffix'];
			$checkVersionSuffix = $checkVersionArr['version_suffix'];
			
			
			unset($curVersionArr['version_suffix']);
			
			foreach($curVersionArr as $index=>$versionNumber) {
				$checkThis = $checkVersionArr[$index];
				
				if(is_numeric($checkThis) && is_numeric($versionNumber)) {
					//set them as integers.
					settype($versionNumber, 'int');
					settype($checkThis, 'int');
					
					if($checkThis > $versionNumber) {
						$retval = TRUE;
						break;
					}
					elseif($checkThis == $versionNumber) {
						//they're equal...
					}
					else {
						//TODO: should there maybe be an option to throw an exception (freak out) here?
						debug_print(__METHOD__ .": while checking ". $index .", realized the new version (". $checkIfHigher .") is LOWER than current (". $version .")",1);
					}
				}
				else {
					throw new exception(__METHOD__ .": ". $index ." is not numeric in one of the strings " .
						"(versionNumber=". $versionNumber .", checkThis=". $checkThis .")");
				}
			}
			
			//now deal with those damnable suffixes, but only if the versions are so far identical: if 
			//	the "$checkIfHigher" is actually higher, don't bother (i.e. suffixes don't matter when
			//	we already know there's a major, minor, or maintenance version that's also higher.
			if($retval === FALSE) {
				$this->gfObj->debug_print(__METHOD__ .": checking suffixes... ");
				//EXAMPLE: $version="1.0.0-BETA3", $checkIfHigher="1.1.0"
				// Moving from a non-suffixed version to a suffixed version isn't supported, but the inverse is:
				//		i.e. (1.0.0-BETA3 to 1.0.0) is okay, but (1.0.0 to 1.0.0-BETA3) is NOT.
				//		Also: (1.0.0-BETA3 to 1.0.0-BETA4) is okay, but (1.0.0-BETA4 to 1.0.0-BETA3) is NOT.
				if(strlen($curVersionSuffix) && strlen($checkVersionSuffix) && $curVersionSuffix == $checkVersionSuffix) {
					//matching suffixes.
					$this->gfObj->debug_print(__METHOD__ .": suffixes match");
				}
				elseif(strlen($curVersionSuffix) || strlen($checkVersionSuffix)) {
					//we know the suffixes are there and DO match.
					if(strlen($curVersionSuffix) && strlen($checkVersionSuffix)) {
						//okay, here's where we do some crazy things...
						$curVersionData = $this->parse_suffix($curVersionSuffix);
						$checkVersionData = $this->parse_suffix($checkVersionSuffix);
						
						if($curVersionData['type'] == $checkVersionData['type']) {
							$this->gfObj->debug_print(__METHOD__ .": got the same type...");
							//got the same suffix type (like "BETA"), check the number.
							if($checkVersionData['number'] > $curVersionData['number']) {
								$this->gfObj->debug_print(__METHOD__ .": new version's suffix number higher than current... ");
								$retval = TRUE;
							}
							elseif($checkVersionData['number'] == $curVersionData['number']) {
								$this->gfObj->debug_print(__METHOD__ .": new version's suffix number is EQUAL TO current... ");
								$retval = FALSE;
							}
							else {
								//umm... they're identical???  LOGIC HAS FAILED ME ALTOGETHER!!!
								$this->gfObj->debug_print(__METHOD__ .": new version's suffix number is LESS THAN current... ");
								$retval = FALSE;
							}
						}
						else {
							//not the same suffix... see if the new one is higher.
							$suffixValues = array_flip($this->suffixList);
							if($suffixValues[$checkVersionData['type']] > $suffixValues[$curVersionData['type']]) {
								$retval = TRUE;
							}
							else {
								$this->gfObj->debug_print(__METHOD__ .": current suffix type is higher... ");
							}
						}
						
					}
					elseif(strlen($curVersionSuffix) && !strlen($checkVersionSuffix)) {
						//i.e. "1.0.0-BETA1" to "1.0.0" --->>> OKAY!
						$retval = TRUE;
					}
					elseif(!strlen($curVersionSuffix) && strlen($checkVersionSuffix)) {
						//i.e. "1.0.0" to "1.0.0-BETA1" --->>> NOT ACCEPTABLE!
						$this->gfObj->debug_print(__METHOD__ .": from (". $version .") to (". $checkIfHigher .") isn't acceptable...?");
					}
				}
				else {
					$this->gfObj->debug_print(__METHOD__ .": no suffix to care about");
				}
			}
		}
		
		$this->gfObj->debug_print(__METHOD__ .": ('". $version ."',  '". $checkIfHigher ."') retval=(". $retval .")", 1);
		
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
			throw new exception(__METHOD__ .": version (". $newVersion .") isn't higher than (". $dbVersion .")... something is broken");
		}
		elseif(is_array($this->config['MATCHING'])) {
			$lastVersion = $dbVersion;
			foreach($this->config['MATCHING'] as $matchVersion=>$data) {
				
				$matchVersion = preg_replace('/^V/', '', $matchVersion);
				if($matchVersion == $data['TARGET_VERSION']) {
					throw new exception(__METHOD__ .": detected invalid TARGET_VERSION in (". $matchVersion ."): make sure TARGET_VERSION is higher than matching!");
				}
				elseif($this->databaseVersion == $matchVersion || $this->is_higher_version($this->databaseVersion, $matchVersion)) {
					//the version in MATCHING is equal to or HIGHER than our database version... make sure it is NOT
					//	higher than the version in our versionFile.
					if(!$this->is_higher_version($this->versionFileVersion, $matchVersion)) {
						if(!count($retval) && $matchVersion != $this->databaseVersion) {
							$retval[$this->databaseVersion] = $matchVersion;
						}
						//the MATCHING version is NOT higher than the version file's version, looks ok.
						$this->gfObj->debug_print(__METHOD__ .": adding (". $matchVersion .")");
						$lastVersion = $data['TARGET_VERSION'];
						$retval[$matchVersion] = $data['TARGET_VERSION'];
					}
					else {
						$this->gfObj->debug_print(__METHOD__ .": entry in upgrade.xml (". $matchVersion .") is higher than the VERSION file (". $this->versionFileVersion .")");
					}
				}
				else {
					$this->gfObj->debug_print(__METHOD__ .": SKIPPING (". $matchVersion .")");
				}
			}
			
			if($lastVersion !== $newVersion && (!isset($retval[$lastVersion]) || $retval[$lastVersion] != $newVersion)) {
				$this->gfObj->debug_print(__METHOD__ .": <b>ALSO (". $lastVersion .") => (". $newVersion .")</b>");
				$retval[$lastVersion] = $newVersion;
			}
		}
		else {
			//no intermediary upgrades: just pass back the latest version.
			$this->gfObj->debug_print(__METHOD__ .": no intermediary upgrades");
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
						throw new exception(__METHOD__ .": invalid suffix (". $suffix .")");
					}
					break;
				}
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid suffix (". $suffix .")");
		}
		
		return($retval);
	}//end parse_suffix()
	//=========================================================================
	
	
	
	//=========================================================================
	private function fix_xml_config($config, $path=null) {
		#$this->gfObj->debug_print(__METHOD__ .": path=(". $path ."):: ". $this->gfObj->debug_print($config,0));
		$this->xmlLoops++;
		if($this->xmlLoops > 1000) {
			throw new exception(__METHOD__ .": infinite loop detected...");
		}
		
		try {
			$a2p = new cs_arrayToPath($config);
		}
		catch(exception $e) {
			$this->gfObj->debug_print($config);
			exit("died on #1");
		}
		if(!is_array($this->tempXmlConfig)) {	
			$this->tempXmlConfig = array();
		}
		try {
			$myA2p = new cs_arrayToPath(&$this->tempXmlConfig);
		}
		catch(exception $e) {
			
			exit("died on #2");
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
					throw new exception(__METHOD__ .": invalid type (". $myData['type'] .")");
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
			throw new exception(__METHOD__ .": unable to fix data on path=(". $path .")::: ". $this->gfObj->debug_print($myData,0));
		}
	}//end fix_xml_config()
	//=========================================================================
	
	
	
	//=========================================================================
	public function load_table() {
		$schemaFileLocation = dirname(__FILE__) .'/schema/schema.sql';
		$schema = file_get_contents($schemaFileLocation);
		$schema = str_replace('{tableName}', $this->config['DB_TABLE'], $schema);
		$this->db->exec($schema);
		
		$loadTableResult = $this->db->errorMsg();
		if(!strlen($loadTableResult)) {
			$loadTableResult = true;
			$logRes = 'Successfully loaded ';
			$logType = 'initialize';
			
			//now set the initial version information...
			if(strlen($this->projectName) && strlen($this->versionFileVersion)) {
				$insertData = $this->parse_version_string($this->versionFileVersion);
				$insertData['project_name'] = $this->projectName;
				
				$sql = 'INSERT INTO '. $this->config['DB_TABLE'] . $this->gfObj->string_from_array($insertData, 'insert');
				if($this->db->run_insert($sql)) {
					$this->logsObj->log_by_class('Created initial version info ('. $insertData['version_string'] .')', $logType);
				}
				else {
					$this->logsObj->log_by_class('Failed to create version info ('. $insertData['version_string'] .')', 'error');
				}
			}
		}
		else {
			$logRes = 'Failed to load ';
			$logType = 'error';
		}
		$this->logsObj->log_by_class($logRes .' table ('. $this->config['DB_TABLE'] .') into ' .
				'database::: '. $loadTableResult, $logType);
		
		return($loadTableResult);
	}//end load_table()
	//=========================================================================
	
	
}//end upgrade{}


?>