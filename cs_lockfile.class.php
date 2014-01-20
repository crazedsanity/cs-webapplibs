<?php

class cs_lockfile {
	/**  */
	protected $lockFile = null;
	
	/** Default name for the lockfile. */
	const defaultLockfile = "upgrade.lock";
	
	/** */
	protected $rwDir;
	
	/**  */
	protected $fsObj = null;
	
	//=========================================================================
	public function __construct($lockFile=null) {
		if(!is_null($lockFile)) {
			$this->set_lockfile(basename($lockFile));
		}
		$this->fsObj = new cs_fileSystem($this->get_rwdir());
	}
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 *
	 * @return string (full path to readable/writable directory) 
	 */
	public function get_rwdir() {

		$constantName = __CLASS__ . '-RWDIR';
		$errorSuffix = " (define or change the value for constant '". $constantName ."')";
		$errorPrefix = "automatically assigned ";
		
		$rwDir = $this->rwDir;
		if (!isset($this->rwDir)) {
			
			$rwDir = dirname(__FILE__) . '/../../rw';
			if (defined($constantName)) {
				$rwDir = constant($constantName);
			}
		}
		
		if (is_dir($rwDir)) {
			//@codeCoverageIgnoreStart
			if (is_readable($rwDir)) {
				if (is_writable($rwDir)) {
					// WINNER!
					$this->rwDir = $rwDir;
				} else {
					throw new ErrorException(__METHOD__ . ": " . $errorPrefix . "directory (" . $rwDir . ") not writable" . $errorSuffix);
				}
			} else {
				throw new ErrorException(__METHOD__ . ": " . $errorPrefix . "directory (" . $rwDir . ") not readable" . $errorSuffix);
			}
			//@codeCoverageIgnoreEnd
		} else {
			throw new ErrorException(__METHOD__ . ": " . $errorPrefix . "value is not a directory" . $errorSuffix);
		}
		
		return($this->rwDir);
	}//end get_rwdir()
	//=========================================================================
	
	
	
	//=========================================================================
	public function get_lockfile() {
		$pathToLockfile = null;
		if(!isset($this->rwDir) && !isset($this->lockFile)) {
			try {
				$this->lockFile = self::defaultLockfile;
				$this->rwDir = $this->get_rwdir();
			}
			catch(Exception $e) {
				throw new Exception(__METHOD__ .": error while getting lockfile::: ". $e->getMessage());
			}
		}
		elseif(isset($this->rwDir) && !isset($this->lockFile)) {
			$this->lockFile = self::defaultLockfile;
		}
		$pathToLockfile = $this->rwDir .'/'. $this->lockFile;
		
		return($pathToLockfile);
	}//end get_lockfile()
	//=========================================================================
	
	
	
	//=========================================================================
	public function set_lockfile($name=null) {
		
		if($this->is_lockfile_present()) {
			throw new exception(__METHOD__ .": cannot change lockfile name when file exists (". $this->get_lockfile() .")");
		}
		elseif(is_null($name)) {
			$name = self::defaultLockfile;
		}
		
		$this->lockFile = $name;
		try {
			if(!strpos($name, '\\') && !strpos($name, '/')) { //preg_match('/\//', $name) && !preg_match('/\\/', $name)) {
				$this->get_rwdir();
				$this->lockFile = $name;
			}
			else {
				throw new exception(__METHOD__ .": name (". $name .") has invalid characters");
			}
		}
		catch(exception $e) {
			throw new exception(__METHOD__ .": error while setting lockfile::: ". $e->getMessage());
		}
		
		return($this->lockFile);
	}//end set_lockfile()
	//=========================================================================
	
	
	
	//=========================================================================
	public function is_lockfile_present() {
		$retval = false;
		try {
			$pathToLockfile = $this->get_lockfile();
			$retval = file_exists($pathToLockfile);
		}
		catch(Exception $e) {
			// nothing to see here, move along.
		}
		
		return($retval);
	}//end is_lock_present()
	//=========================================================================
	
	
	
	//=========================================================================
	public function create_lockfile($contents=null) {
		
		if(!$this->is_lockfile_present()) {
			$retval = $this->fsObj->create_file($this->get_lockfile());
			
			if(!is_null($contents)) {
				$this->fsObj->write($contents);
			}
		}
		else {
			throw new exception(__METHOD__ .": lockfile (". $this->lockFile .") already present");
		}
		
		return($retval);
	}//end create_lockfile()
	//=========================================================================
	
	
	
	//=========================================================================
	public function delete_lockfile() {
		$retval = false;
		try {
			if($this->is_lockfile_present()) {
				$retval = $this->fsObj->rm($this->get_lockfile());
			}
		}
		catch (Exception $e) {
			$retval = false;
		}
		
		return($retval);
	}
	//=========================================================================
	
	
	
	//=========================================================================
	public function read_lockfile() {
		$retval = null;
		if($this->is_lockfile_present()) {
			$retval = file_get_contents($this->get_lockfile());
		}
		else {
			throw new exception(__METHOD__ .": no lockfile exists");
		}
		return($retval);
	}//end read_lockfile()
	//=========================================================================
}

?>
