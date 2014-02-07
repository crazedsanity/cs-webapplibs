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
	public function __construct($rwDir, $lockFile="upgrade.lock") {
		if(!is_null($lockFile) && strlen($lockFile) > 1) {
			$this->lockFile = $lockFile;
		}
		else {
			$this->lockFile = self::defaultLockfile;
		}
		
		if(!is_null($rwDir) && strlen($rwDir)) {	
			if(is_dir($rwDir) && is_readable($rwDir)) {
				if(is_writable($rwDir)) {
					$this->rwDir = $rwDir;
				}
				else {
					throw new InvalidArgumentException("directory (". $rwDir .") is readable but not writable");
				}
			}
			else {
				throw new InvalidArgumentException("specified path (". $rwDir .") is not a directory or is not readable");
			}
		}
		else {
			throw new InvalidArgumentException("no path specified for rwDir");
		}
		$this->set_lockfile($this->lockFile);
		
		$this->fsObj = new cs_fileSystem($this->rwDir);
	}
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 *
	 * @return string (full path to readable/writable directory) 
	 */
	public function get_rwdir() {
		if(!is_dir($this->rwDir)) {
			throw new ErrorException("Invalid rwDir (". $this->rwDir .")");
		}
		return($this->rwDir);
	}//end get_rwdir()
	//=========================================================================
	
	
	
	//=========================================================================
	public function get_lockfile() {
		$pathToLockfile = $this->rwDir .'/'. $this->lockFile;
		return($pathToLockfile);
	}//end get_lockfile()
	//=========================================================================
	
	
	
	//=========================================================================
	public function set_lockfile($name=null) {
		
		if(!is_null($name) && $this->lockFile === $name && $this->is_lockfile_present()) {
			//do nothing.
		}
		else {
			if($this->is_lockfile_present()) {
				throw new exception(__METHOD__ .": cannot change lockfile name (to '". $name ."') when file exists (". $this->lockFile .")");
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
		}
		
		return($this->lockFile);
	}//end set_lockfile()
	//=========================================================================
	
	
	
	//=========================================================================
	public function is_lockfile_present() {
		$retval = false;
		try {
			$pathToLockfile = $this->rwDir .'/'. $this->lockFile;
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
