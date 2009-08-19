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
	private $fullVersionString;
	private $suffixList = array(
		'ALPHA', 	//very unstable
		'BETA', 	//kinda unstable, but probably useable
		'RC'		//all known bugs fixed, searching for unknown ones
	);
	
	
	
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
				$versionInfo = $this->parse_version_string($fullVersionString);
				$this->fullVersionString = $this->build_full_version_string($versionInfo);
				
				
				if($asArray) {
					$retval = $versionInfo;
					$retval['version_string'] = $this->fullVersionString;
				}
				else {
					$retval = $this->build_full_version_string($versionInfo);
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
	public function __get($var) {
		return($this->$var);
	}//end __get()
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
				throw new exception(__METHOD__ .": failed to automatically set version file (tried ". $dir ."/VERSION)");
			}
		}
	}//end auto_set_version_file()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * 
	 * TODO: add logic to split apart the suffix (i.e. "-ALPHA5" broken into "ALPHA" and "5").
	 */
	public function parse_version_string($version) {
		if(is_string($version) && strlen($version) && preg_match('/\./', $version)) {
			$version = preg_replace('/ /', '', $version);
			
			$pieces = explode('.', $version);
			$retval = array(
				'version_major'			=> $pieces[0],
				'version_minor'			=> $pieces[1]
			);
			if(isset($pieces[2]) && strlen($pieces[2])) {
				$retval['version_maintenance'] = $pieces[2];
			}
			else {
				$retval['version_maintenance'] = 0;
			}
			
			if(preg_match('/-/', $retval['version_maintenance'])) {
				$bits = explode('-', $retval['version_maintenance']);
				$retval['version_maintenance'] = $bits[0];
				$suffix = $bits[1];
			}
			elseif(preg_match('/-/', $retval['version_minor'])) {
				$bits = explode('-', $retval['version_minor']);
				$retval['version_minor'] = $bits[0];
				$suffix = $bits[1];
			}
			else {
				$suffix = "";
			}
			$retval['version_suffix'] = $suffix;
		}
		else {
			throw new exception(__METHOD__ .": invalid version string passed (". $version .")");
		}
		
		return($retval);
	}//end parse_version_string()
	//=========================================================================
	
	
	
	//=========================================================================
	public function build_full_version_string(array $versionInfo) {
		$requiredIndexes = array(
			'version_major', 'version_minor', 'version_maintenance', 'version_suffix'
		);
		
		$missing="";
		$count=0;
		foreach($requiredIndexes as $indexName) {
			if(isset($versionInfo[$indexName])) {
				$count++;
			}
			else {
				if(strlen($missing)) {
					$missing .= ", ". $indexName;
				}
				else {
					$missing = $indexName;
				}
			}
		}
		
		if($count == count($requiredIndexes) && !strlen($missing)) {
			$suffix = $versionInfo['version_suffix'];
			unset($versionInfo['version_suffix']);
			
			$retval = "";
			foreach($versionInfo as $name=>$value) {
				if(strlen($retval)) {
					$retval .= ".". $value;
				}
				else {
					$retval = $value;
				}
			}
			if(strlen($suffix)) {
				$retval .= "-". $suffix;
			}
		}
		else {
			throw new exception(__METHOD__ .": missing indexes in given array (". $missing .")");
		}
		
		return($retval);
		
	}//end build_full_version_string()
	//=========================================================================
	
	
	
	//=========================================================================
	public function is_higher_version($version, $checkIfHigher) {
		$retval = FALSE;
		$this->gfObj = new cs_globalFunctions;
		if(!is_string($version) || !is_string($checkIfHigher)) {
			throw new exception(__METHOD__ .": no valid version strings, version=(". $version ."), checkIfHigher=(". $checkIfHigher .")");
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
				//EXAMPLE: $version="1.0.0-BETA3", $checkIfHigher="1.1.0"
				// Moving from a non-suffixed version to a suffixed version isn't supported, but the inverse is:
				//		i.e. (1.0.0-BETA3 to 1.0.0) is okay, but (1.0.0 to 1.0.0-BETA3) is NOT.
				//		Also: (1.0.0-BETA3 to 1.0.0-BETA4) is okay, but (1.0.0-BETA4 to 1.0.0-BETA3) is NOT.
				if(strlen($curVersionSuffix) && strlen($checkVersionSuffix) && $curVersionSuffix == $checkVersionSuffix) {
					//matching suffixes.
				}
				elseif(strlen($curVersionSuffix) || strlen($checkVersionSuffix)) {
					//we know the suffixes are there and DO match.
					if(strlen($curVersionSuffix) && strlen($checkVersionSuffix)) {
						//okay, here's where we do some crazy things...
						$curVersionData = $this->parse_suffix($curVersionSuffix);
						$checkVersionData = $this->parse_suffix($checkVersionSuffix);
						
						if($curVersionData['type'] == $checkVersionData['type']) {
							//got the same suffix type (like "BETA"), check the number.
							if($checkVersionData['number'] > $curVersionData['number']) {
								//new version's suffix number higher than current...
								$retval = TRUE;
							}
							elseif($checkVersionData['number'] == $curVersionData['number']) {
								//new version's suffix number is EQUAL TO current...
								$retval = FALSE;
							}
							else {
								//new version's suffix number is LESS THAN current...
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
								//current suffix type is higher...
							}
						}
						
					}
					elseif(strlen($curVersionSuffix) && !strlen($checkVersionSuffix)) {
						//i.e. "1.0.0-BETA1" to "1.0.0" --->>> OKAY!
						$retval = TRUE;
					}
					elseif(!strlen($curVersionSuffix) && strlen($checkVersionSuffix)) {
						//i.e. "1.0.0" to "1.0.0-BETA1" --->>> NOT ACCEPTABLE!
					}
				}
				else {
					//no suffix to care about
				}
			}
		}
		
		return($retval);
		
	}//end is_higher_version()
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
	
	
}
?>