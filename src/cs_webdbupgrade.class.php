<?php
/*
 * Created on Jul 2, 2007
 * 
 */

use crazedsanity\database\Database;
use crazedsanity\version\Version;
use crazedsanity\core\ToolBox;

class cs_webdbupgrade extends cs_webapplibsAbstract {
	
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
	
	/** Name of the project as referenced in the database and VERSION file. */
	protected $projectName;
	
	/** Internal cache of the version string from the VERSION file. */
	protected $versionFileVersion = NULL;
	
	/** Stored database version. */
	protected $databaseVersion = NULL;
	
	/** Determines if an internal upgrade is happening (avoids some infinite loops) */
	protected $internalUpgradeInProgress = false;
	
	/** */
	private $internalProjectName = "";
	
	protected $internalVersion;
	
	/** Log messages to store during an internal upgrade (to avoid problems) */
	protected $storedLogs = array();
	protected $debugLogs=array();
	
	protected $dbType=null;
	
	protected $dbTable = 'cswal_version_table';
	protected $dbPrimaryKey = 'version_id';
	protected $upgradeConfigFile;
	protected $dbParams = array();
	protected $rwDir = "/tmp";
	protected $initialVersion = "";
	protected $matchingData = array();
	
	protected $lockObj = null;
	
	protected static $cache = array();
	
	protected static $calls = 0;
	
	private $dbConnected = false;
	
	
	const UPGRADE_VERSION_ONLY = 0;
	const UPGRADE_SCRIPTED = 1;
	
	protected $lockFile='upgrade.lock';
	
	//=========================================================================
	public function __construct($versionFileLocation, $upgradeConfigFile, Database $db, $rwDir=null) {
		
		$this->internalVersion = new Version(__DIR__ .'/../VERSION');
		$this->internalProjectName = $this->internalVersion->get_project();
		
		$this->set_version_file_location(__DIR__ .'/../VERSION');
		$this->internalProjectName = $this->get_project();
		
		if(isset(self::$calls)) {
			self::$calls += 1;
		}
		else {
			self::$calls = 1;
		}
		
		if(self::$calls > 100) {
			throw new LogicException(__METHOD__ .": called too many times (". self::$calls .")... ");
		}
		
		$this->db = $db;
		
		parent::__construct(true);
		
		if(!file_exists($upgradeConfigFile) || !is_readable($upgradeConfigFile)) {
			throw new exception(__METHOD__ .": required upgrade config file location (". $upgradeConfigFile .") not set or unreadable");
		}
		else {
			$this->upgradeConfigFile = $upgradeConfigFile;
		}
		if(!strlen($versionFileLocation) || !file_exists($versionFileLocation)) {
			throw new exception(__METHOD__ .": unable to locate version file (". $versionFileLocation .")");
		}
		
		$this->set_version_file_location($versionFileLocation);
		
		if(!is_null($rwDir) && strlen($rwDir) > 0) {
			$this->rwDir = $rwDir;
			$rwType = 'passed';
		}
		elseif(defined('RWDIR')) {
			$this->rwDir = constant('RWDIR');
			$rwType = 'defined';
		}
		else {
			throw new InvalidArgumentException(__METHOD__ .": required RWDIR not specified");
		}
		
		if (!is_dir($this->rwDir) || !is_readable($this->rwDir) || !is_writable($this->rwDir)) {
			throw new ErrorException($rwType ." RWDIR var (" . $this->rwDir . ") is not a directory or is not readable/writable");
		}
		
		
		$this->lockObj = new cs_lockfile($this->rwDir, $this->lockFile);
		
		$this->projectName = $this->get_project();
		
		
//		$this->check_internal_upgrades();
//		$this->check_versions(false);
	}//end __construct()
	//=========================================================================
	
	
	
	//=========================================================================
	protected function reconnect_db() {
		
		if(is_object($this->db)) {
			$dsn = $this->db->get_dsn();
			$user = $this->db->get_username();
			$pass = $this->db->get_password();
			
			$this->db->reconnect($dsn, $user, $pass);
			$this->dbConnected = $this->db->is_connected();
		}
		else {
			throw new LogicException(__METHOD__ .": database object not found");
		}
		
		return $this->dbConnected;
	}//end connect_db()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Determine if there are any upgrades that need to be performed...
	 */
	protected function check_internal_upgrades() {
		$oldProjectName = $this->projectName;
		$this->projectName = $this->internalProjectName;
		
		if(!isset(self::$cache[$this->projectName])) {
			
			$this->reconnect_db();
			
			$oldVersionFileLocation = $this->versionFileLocation;
			$oldUpgradeConfigFile = $this->upgradeConfigFile;
			$this->upgradeConfigFile = dirname(__FILE__) .'/upgrades/upgrade.ini';


			//set a status flag so we can store log messages (for now).
			$this->internalUpgradeInProgress = true;


			//do stuff here...
			$this->set_version_file_location(__DIR__ .'/../VERSION');
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
			
			self::$cache[$this->projectName] = array(
				'versionFileVersion'	=> $this->versionFileVersion,
				'databaseVersion'		=> $this->databaseVersion
			);
		}
		
		$this->projectName = $oldProjectName;
		
	}//end check_internal_upgrades()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Where everything begins: checks if the version held in config.xml lines-up 
	 * with the one in the VERSION file; if it does, then it checks the version 
	 * listed in the database.
	 */
	public function check_versions($performUpgrade=true) {
		if(isset(self::$cache[$this->projectName])) {
			$retval = false;
		}
		else {
			if($performUpgrade && $this->is_upgrade_in_progress()) {
				$this->do_log("Upgrade in progress", 'notice');
				throw new exception(__METHOD__ .": upgrade in progress");
			}
			
			$retval = NULL;
			
			//okay, all files present: check the version in the VERSION file.
			$this->read_version_file();
			$dbVersion = $this->get_database_version(true);
			
			if (!is_array($dbVersion)) {
				$this->load_initial_version();
			}
			
			$versionsDiffer = !($this->versionFileVersion == $this->databaseVersion);
			$retval = false;
			
			$conflict = $this->check_for_version_conflict();
			
			if ($performUpgrade && $conflict != null && $versionsDiffer) {
				$retval = $this->perform_upgrade();
			}
			
			if($performUpgrade === true) {
				// put this into cache so we don't try doing it again.
				self::$cache[$this->projectName] = array(
					'versionFileVersion'	=> $this->versionFileVersion,
					'databaseVersion'		=> $this->databaseVersion
				);
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
			$versionFileContents = file_get_contents($this->versionFileLocation);
			
			$versionMatches = array();
			preg_match_all('/\nVERSION: (.*)\n/', $versionFileContents, $versionMatches);
			if(count($versionMatches) == 2 && count($versionMatches[1]) == 1) {
				$this->versionFileVersion = $this->get_full_version_string(trim($versionMatches[1][0]));
				$retval = $this->versionFileVersion;

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
		
		$config = parse_ini_file($this->upgradeConfigFile,true, INI_SCANNER_RAW);
		
		if(is_array($config)) {
			$myConfig = $config;
			if(isset($config['main'])) {
				if(isset($config['main']['initial_version'])) {
					$this->initialVersion = $config['main']['initial_version'];
				}
				unset($myConfig['main'], $myConfig['defaults']);
			}
			
			$lastVersion = null;
			foreach ($myConfig as $v => $d) {
				$myVersion = preg_replace('/^v/', '', $v);
				if (is_null($lastVersion)) {
					$lastVersion = $myVersion;
				} else {
					if (!$this->is_higher_version($lastVersion, $myVersion)) {
						throw new LogicException(__METHOD__ . ": version must be in order of lowest to highest");
					}
				}
				
				//certain indexes MUST be set.
				if(!isset($d['target_version'])) {
					throw new ErrorException(__METHOD__ .": missing target version for '". $myVersion ."'");
				}
				
				$this->matchingData[$myVersion] = $d;
			}
		} else {
			$this->initialVersion = $this->versionFileVersion;
		}
		
		return $this->matchingData;
	}//end read_upgrade_config_file()
	//=========================================================================
	
	
	
	//=========================================================================
	protected function perform_upgrade() {
		$transactionStartedExternally = $this->db->get_transaction_status();
		if($this->is_upgrade_in_progress()) {
			//ew.  Can't upgrade.
			$this->error_handler(__METHOD__ .": upgrade already in progress...????");
		}
		else {
			$lockConfig = $this->is_upgrade_in_progress(TRUE);
			
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
				
				if(!is_null($versionConflictInfo)) {
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
							$this->do_log(__METHOD__ .": upgradeList::: ". ToolBox::debug_print($upgradeList,0), 'debug');
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
	public function set_upgrade_in_progress() {
		$retval = false;
		$showDbVersion = $this->databaseVersion;
		if(is_null($showDbVersion) || !strlen($showDbVersion)) {
			$showDbVersion = "(unknown)";
		}
		if(!$this->lockObj->is_lockfile_present()) {
			$details = $this->projectName .': Upgrade from '. $showDbVersion .' started at '. date('Y-m-d H:i:s');
			$this->lockObj->create_lockfile($details);
		}
		$retval = $this->lockObj->is_lockfile_present();
		
		return $retval;
	}//end set_upgrade_in_progress()
	//=========================================================================
	
	
	
	//=========================================================================
	public function is_upgrade_in_progress($makeItSo=FALSE) {
		if($makeItSo === TRUE) {
			$retval = $this->set_upgrade_in_progress();
		}
		$retval = $this->lockObj->is_lockfile_present();
		
		return($retval);
	}//end upgrade_in_progress()
	//=========================================================================
	
	
	
	//=========================================================================
	public static function parse_version_string($versionString) {
		if(is_null($versionString) || !strlen($versionString)) {
			throw new exception(__METHOD__ .": invalid version string ($versionString)");
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
			throw new exception(__METHOD__ .": invalid version string format, requires MAJOR.MINOR syntax (". $versionString .")");
		}
		
		return($retval);
	}//end parse_version_string()
	//=========================================================================
	
	
	
	//=========================================================================
	protected function check_for_version_conflict() {
		//set a default return...
		$retval = NULL;
		
//		$this->read_version_file();
		
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
					$this->error_handler(__METHOD__ .": downgrading minor versions is unsupported, ".
						"file version=(". $versionFileData['version_string'] ."), dbVersion=(". $dbVersion['version_string'] .")");
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
	public function get_database_version($missingVersionAllowed=false) {
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
					$message = __METHOD__ .": no table in database, failed to create one... ORIGINAL " .
						"ERROR: ". $dberror .", SCHEMA LOAD ERROR::: ". $loadTableResult;
					$this->error_handler($message);
				}
			}
			elseif(!strlen($dberror) && $numrows == 0) {
				if($missingVersionAllowed) {
					$retval = false;
				}
				else {
					$this->error_handler(__METHOD__ .": no version data found for (". $this->projectName .")");
				}
			}//@codeCoverageIgnoreStart
			else {
				$this->error_handler(__METHOD__ .": failed to retrieve version... numrows=(". $numrows ."), DBERROR::: ". $dberror);
			}//@codeCoverageIgnoreEnd
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
		$upgradeType = self::UPGRADE_VERSION_ONLY;
		
		$versionIndex = $this->get_full_version_string($fromVersion);
		
		if(isset($this->matchingData[$versionIndex])) {
			//scripted upgrade...
			$upgradeData = $this->matchingData[$versionIndex];
			if(isset($upgradeData['target_version']) && count($upgradeData) > 1) {
				$this->newVersion = $upgradeData['target_version'];
				if(isset($upgradeData['script_name']) && isset($upgradeData['class_name']) && isset($upgradeData['call_method'])) {
					$this->do_scripted_upgrade($upgradeData);
					$toVersion = $upgradeData['target_version'];
					$upgradeType = self::UPGRADE_SCRIPTED;
				}
			}
			else {
				$this->error_handler(__METHOD__ .": target version not specified, unable to proceed with scripted upgrade for ". $versionIndex);
			}
		}
		
		$this->newVersion = $toVersion;
		$this->update_database_version($toVersion);
		
		$this->do_log("Finished upgrade to ". $this->newVersion, 'system');
		
		return $upgradeType;
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
		if(!$this->check_database_version($versionInfo)) {
			$this->error_handler(__METHOD__ .": database version information is invalid: (". $versionInfo .")");
		}
		
		return($retval);
		
	}//end update_database_version()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Checks consistency of version information in the database, and optionally 
	 * against a given version string.
	 */
	protected function check_database_version(array $checkThis) {
		$data = $this->get_database_version();
		$versionString = $data['version_string'];
		
		if ($versionString == $checkThis['version_string']) {
			$retval = TRUE;
		} else {
			$retval = FALSE;
		}
		
		if (!$retval) {
			$this->do_log("Version check failed, versionString=(" . $versionString . "), checkVersion=(" . $this->newVersion . ")", 'FATAL');
		}
		
		return($retval);
	}//end check_database_version()
	//=========================================================================
	
	
	
	//=========================================================================
	protected function do_scripted_upgrade(array $upgradeData) {
		$myConfigFile = $upgradeData['script_name'];
		
		$this->do_log("Preparing to run script '". $myConfigFile ."'", 'debug');
		
		//we've got the filename, see if it exists.
		
		$scriptsDir = dirname($this->upgradeConfigFile);
		$fileName = $scriptsDir .'/'. $myConfigFile;
		
		if(file_exists($fileName)) {
			
			$this->do_log("(". __CLASS__ .") Performing scripted upgrade (". $myConfigFile .") from file '". $fileName ."'", 'debug');
			$createClassName = $upgradeData['class_name'];
			$classUpgradeMethod = $upgradeData['call_method'];
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
						$this->do_log(__METHOD__ .": entry in upgrade.ini (". $matchVersion .") is higher than the VERSION file (". $this->versionFileVersion .")", 'warning');
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
			throw new exception($details);
		}
		if(self::internalUpgradeInProgress === false) {
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
		if(isset($this->initialVersion) && strlen($this->initialVersion)) {
			$parseThis = $this->initialVersion;
		}
		else {
			$parseThis = $this->versionFileVersion;
		}
		
		$loadRes = $this->set_initial_version($parseThis);
		
		return($loadRes);
	}//end load_initial_version()
	//=========================================================================
	
	
	
	//=========================================================================
	protected function set_initial_version($versionString) {
		
		try {
			$versionInfo = $this->parse_version_string($versionString);
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
		
		return $loadRes;
	}
	//=========================================================================
	
	
	
	//=========================================================================
	protected function do_log($message, $type='exception in code') {
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
