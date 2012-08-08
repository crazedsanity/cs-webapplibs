<?php

class cs_lockfile {
	
	/** Name of the lock file  */
	public static $lockfile = "upgrade.lock";
	
	
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
		$rwDir = dirname(__FILE__) .'/../../rw';
		if(defined(__CLASS__ .'-RWDIR')) {
			$rwDir = constant(__CLASS__ .'-RWDIR');
		}
		return($rwDir);
	}//end get_rwdir()
	//=========================================================================
	
	
	
	//=========================================================================
	/***
	 * Get full path to lock file.
	 */
	public static function get_lockfile() {
		$rwDir = self::get_rwdir();
		$lockFile = $rwDir .'/'. self::$lockfile;
		
		return($lockFile);
	}//end get_lockfile()
	//=========================================================================
	
	
	
	//=========================================================================
	public static function is_lockfile_present() {
		return(file_exists(self::get_lockfile()));
	}//end is_lock_present()
	//=========================================================================
	
	
	
	//=========================================================================
	public static function create_lockfile($contents=null) {
		$fsObj = new cs_fileSystem(self::get_rwdir());
		
		$retval = $fsObj->create_file(self::get_lockfile());
		
		if(!is_null($contents)) {
			$fsObj->write($contents);
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
}

?>
