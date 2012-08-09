<?php

class cs_lockfile {
	
	/** Name of the lock file  */
	private static $lockfile = NULL;
	
	/** Directory to hold lock file (must be readable + writable) */
	private static $rwDir = NULL;
	
	/**  */
	private static $pathToLockfile=NULL;
	
	/** Default name for the lockfile. */
	const defaultLockfile = "upgrade.lock";
	
	//=========================================================================
	public function __construct($lockFile=null) {
		if(!is_null($lockFile)) {
			self::$lockfile = $lockFile;
		}
	}
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 *
	 * @return string (full path to readable/writable directory) 
	 */
	public static function get_rwdir() {

		if (!isset(self::$rwDir)) {
			self::set_rwdir();
		}
		else {
			if (!is_writable(self::$rwDir)) {
				throw new exception(__METHOD__ . ": directory is not writable (" . $rwDir . ")");
			}
		}
		return(self::$rwDir);
	}//end get_rwdir()
	//=========================================================================
	
	
	
	//=========================================================================
	public static function set_rwdir($dir=null) {
		
		$constantName = __CLASS__ . '-RWDIR';
		$errorPrefix = null;
		$errorSuffix = " (define or change the value for constant '". $constantName ."')";
		if (is_null($dir)) {
			$errorPrefix = "automatically assigned ";
			
			$dir = dirname(__FILE__) . '/../../rw';
			if (defined($constantName)) {
				$dir = constant($constantName);
			}
		}
		
		if (is_dir($dir)) {
			if(is_readable($dir)) {
				if (is_writable($dir)) {
					// WINNER!
					self::$rwDir = $dir;
				} else {
					throw new exception(__METHOD__ . ": ". $errorPrefix ."directory (" . $dir . ") not writable". $errorSuffix);
				}
			}
			else {
				throw new exception(__METHOD__ .": ". $errorPrefix ."directory (". $dir .") not readable". $errorSuffix);
			}
		} else {
			throw new exception(__METHOD__ . ": ". $errorPrefix ."value is not a directory". $errorSuffix);
		}
		
	}//end set_rwdir()
	//=========================================================================
	
	
	
	//=========================================================================
	public static function get_lockfile() {
		if(!isset(self::$pathToLockfile)) {
			try {
				self::$lockfile = self::defaultLockfile();
				$rwDir = self::set_rwdir();
				self::$pathToLockfile = $rwDir .'/'. self::$lockfile;
			}
			catch(Exception $e) {
				throw new Exception(__METHOD__ .": error while getting lockfile::: ". $e->getMessage());
			}
		}
		
		return(self::$pathToLockfile);
	}//end get_lockfile()
	//=========================================================================
	
	
	
	//=========================================================================
	public static function set_lockfile($name=null) {
		
		if(self::is_lockfile_present()) {
			throw new exception(__METHOD__ .": cannot change lockfile name when file exists (". self::$pathToLockfile .")");
		}
		elseif(is_null($name)) {
			$name = self::defaultLockfile;
		}
		
		self::$lockfile = $name;
		try {
			if(!strpos($name, '\\') && !strpos($name, '/')) { //preg_match('/\//', $name) && !preg_match('/\\/', $name)) {
				self::get_rwdir();
				self::$lockfile = $name;
				self::$pathToLockfile = self::$rwDir .'/'. self::$lockfile;
			}
			else {
				throw new exception(__METHOD__ .": name (". $name .") has invalid characters");
			}
		}
		catch(exception $e) {
			throw new exception(__METHOD__ .": error while setting lockfile::: ". $e->getMessage());
		}
		
		return(self::$lockfile);
	}//end set_lockfile()
	//=========================================================================
	
	
	
	//=========================================================================
	public static function is_lockfile_present() {
		$retval = false;
		if(!is_null(self::$pathToLockfile)) {
			$retval = file_exists(self::get_lockfile());
		}
		
		return($retval);
	}//end is_lock_present()
	//=========================================================================
	
	
	
	//=========================================================================
	public static function create_lockfile($contents=null) {
		
		if(!self::is_lockfile_present()) {
			$fsObj = new cs_fileSystem(self::get_rwdir());

			$retval = $fsObj->create_file(self::get_lockfile());

			if(!is_null($contents)) {
				$fsObj->write($contents);
			}
		}
		else {
			throw new exception(__METHOD__ .": lockfile (". self::$lockfile .") already present");
		}
		
		return($retval);
	}//end create_lockfile()
	//=========================================================================
	
	
	
	//=========================================================================
	public static function delete_lockfile() {
		$fsObj = new cs_fileSystem(self::get_rwdir());
		
		$retval = $fsObj->rm(self::get_lockfile());
		
		return($retval);
	}
	//=========================================================================
	
	
	
	//=========================================================================
	public static function read_lockfile() {
		$retval = null;
		if(self::is_lockfile_present()) {
			$retval = file_get_contents(self::$pathToLockfile);
		}
		else {
			throw new exception(__METHOD__ .": no lockfile exists");
		}
		return($retval);
	}//end read_lockfile()
	//=========================================================================
}

?>
