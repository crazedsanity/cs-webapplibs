<?php
/*
 * Created on January 01, 2009 by Dan Falconer
 * 
 * SVN INFORMATION:::
 * -------------------
 * Last Author::::::::: $Author$ 
 * Current Revision:::: $Revision$ 
 * Repository Location: $HeadURL$ 
 * Last Updated:::::::: $Date$
 */

abstract class cs_versionAbstract {
	
	public $isTest = FALSE;
	private $versionFileLocation=null;
	
	abstract public function __construct();
	
	
	
	//=========================================================================
	/**
	 * Retrieve our version string from the VERSION file.
	 */
	final public function get_version($asArray=false) {
		$retval = NULL;
		
		$this->auto_set_version_file();
		
		if(file_exists($this->versionFileLocation)) {
			$myMatches = array();
			$findIt = preg_match('/VERSION: (.+)/', file_get_contents($this->versionFileLocation), $matches);
			
			if($findIt == 1 && count($matches) == 2) {
				$fullVersionString = $matches[1];
				$pieces = explode('.', $fullVersionString);
				$retval = array(
					'version_major'			=> $pieces[0],
					'version_minor'			=> $pieces[1],
					'version_maintenance'	=> $pieces[2]
				);
				if(!strlen($retval['version_maintenance'])) {
					$retval['version_maintenance'] = 0;
				}
				
				if(preg_match('/-/', $retval['version_maintenance'])) {
					$bits = explode('-', $retval['version_maintenance']);
					$retval['version_maintenance'] = $bits[0];
					$suffix = $bits[1];
				}
				else {
					$suffix = "";
				}
				
				$fullVersionString = "";
				foreach(array_values($retval) as $chunk) {
					if(strlen($fullVersionString)) {
						$fullVersionString .= '.';
					}
					$fullVersionString .= $chunk;
				}
				if(strlen($suffix)) {
					$fullVersionString .= '-'. $suffix;
				}
				
				
				if($asArray) {
					$retval['version_suffix'] = $suffix;
					$retval['version_string'] = $fullVersionString;
				}
				else {
					$retval = $fullVersionString;
				}
			}
			else {
				throw new exception(__METHOD__ .": failed to retrieve version string in file " .
						"(". $this->versionFileLocation .")");
			}
		}
		else {
			throw new exception(__METHOD__ .": failed to retrieve version information, file " .
					"(". $this->versionFileLocation .") does not exist or was not set");
		}
		
		return($retval);
	}//end get_version()
	//=========================================================================
	
	
	
	//=========================================================================
	final public function get_project() {
		$retval = NULL;
		$this->auto_set_version_file();
		if(file_exists($this->versionFileLocation)) {
			$myMatches = array();
			$findIt = preg_match('/PROJECT: (.+)/', file_get_contents($this->versionFileLocation), $matches);
			
			if($findIt == 1 && count($matches) == 2 && strlen($matches[1])) {
				$retval = $matches[1];
			}
			else {
				throw new exception(__METHOD__ .": failed to retrieve project string");
			}
		}
		else {
			throw new exception(__METHOD__ .": failed to retrieve project information");
		}
		
		return($retval);
	}//end get_project()
	//=========================================================================
	
	
	
	//=========================================================================
	public function set_version_file_location($location) {
		if(file_exists($location)) {
			$this->versionFileLocation = $location;
		}
		else {
			throw new exception(__METHOD__ .": invalid location of VERSION file (". $location .")");
		}
	}//end set_version_file_location()
	//=========================================================================
	
	
	
	//=========================================================================
	protected function auto_set_version_file() {
		if(!strlen($this->versionFileLocation)) {
			$bt = debug_backtrace();
			foreach($bt as $callNum=>$data) {
				if(strlen($data['class'])) {
					if($data['class'] != __CLASS__) {
						$dir = dirname($data['file']);
						if(preg_match('/tests$/', $dir)) {
							$dir = preg_replace('/\/tests$/', '', $dir);
						}
						elseif(preg_match('/test$/', $dir)) {
							$dir = preg_replace('/\/test$/', '', $dir);
						}
						break;
					}
				}
				else {
					throw new exception(__METHOD__ .": failed to locate the calling class in backtrace");
				}
			}
			
			if(file_exists($dir .'/VERSION')) {
				$this->set_version_file_location($dir .'/VERSION');
			}
			else {
				throw new exception(__METHOD__ .": failed to automatically set version file");
			}
		}
	}//end auto_set_version_file()
	//=========================================================================
	
	
}
?>