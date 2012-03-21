<?php

/*
 * A class for handling configuration of database-driven web applications.
 * 
 * NOTICE::: this class requires that cs-phpxml and cs-arraytopath are both available
 * at the same directory level as cs-content; all projects are SourceForge.net projects,
 * using their unix names ("cs-phpxml" and "cs-arrayToPath").  The cs-phpxml project 
 * requires cs-arrayToPath for parsing XML paths.
 * 
 * SVN INFORMATION:::
 * SVN Signature:::::::: $Id$
 * Last Committted Date: $Date$
 * Last Committed Path:: $HeadURL$
 * 
 */


class cs_siteConfig extends cs_webapplibsAbstract {
	
	/** XMLParser{} object, for reading XML config file. */
	private $xmlReader;
	
	/** cs_fileSystem{} object, for writing/updating XML config file 
	 * (only available if file is writable)
	 */
	private $xmlWriter;
	
	/** XMLBuilder{} object, for updating XML. */
	private $xmlBuilder;
	
	/** cs_fileSystem{} object, for handling generic file operations (i.e. reading) */
	private $fs;
	
	/** boolean flag indicating if the given config file is readOnly (false=read/write) */
	private $readOnly;
	
	/** Directory for the config file. */
	private $configDirname;
	
	/** Location of the configuration file itself. */
	private $configFile;
	
	/** Active section of the full site configuration. */
	private $activeSection;
	
	/** The FULL configuration file, instead of just the active section. */
	private $fullConfig=array();
	
	/** cs_arrayToPath{} object. */
	private $a2p;
	
	/** Prefix to add to every index in GLOBALS and CONSTANTS. */
	private $setVarPrefix;
	
	/** Sections available within the config */
	private $configSections=array();
	
	/** Boolean flag to determine if the object has been properly initialized or not. */
	private $isInitialized=false;
	
	/** Store a list of items that need to be pushed into $GLOBALS on a given path. */
	private $setGlobalArrays=array();
	
	//-------------------------------------------------------------------------
	/**
	 * Constructor.
	 * 
	 * @param $configFileLocation	(str) URI for config file.
	 * @param $section				(str,optional) set active section (default=MAIN)
	 * @param $setVarPrefix			(str,optional) prefix to add to all global & constant names.
	 * 
	 * @return NULL					(PASS) object successfully created
	 * @return exception			(FAIL) failed to create object (see exception message)
	 */
	public function __construct($configFileLocation, $section=null, $setVarPrefix=null) {
		
		$section = strtoupper($section);
		$this->setVarPrefix=$setVarPrefix;
		
		parent::__construct();
		
		if(strlen($configFileLocation) && file_exists($configFileLocation)) {
			
			$this->configDirname = dirname($configFileLocation);
			$this->configFile = $configFileLocation;
			$this->fs = new cs_fileSystem($this->configDirname);
			
			$this->xmlReader = new cs_phpxmlParser($this->fs->read($configFileLocation));
			
			if($this->fs->is_writable($configFileLocation)) {
				$this->readOnly = false;
				$this->xmlWriter = new cs_fileSystem($this->configDirname);
				
			}
			else {
				$this->readOnly = true;
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid configuration file (". $configFileLocation .")");
		}
		
		if(is_null($section) || !strlen($section)) {
			$myData = $this->xmlReader->get_path($this->xmlReader->get_root_element());
			unset($myData['type'], $myData[cs_phpxmlCreator::attributeIndex]);
			$myData = array_keys($myData);
			$section = $myData[0];
		}
		
		if(strlen($section)) {
			try {
				$this->parse_config();
				$this->set_active_section($section);
				$this->config = $this->get_section($section);
			}
			catch(exception $e) {
				throw new exception(__METHOD__ .": invalid section (". $section ."), DETAILS::: ". $e->getMessage());
			}
		}
		else {
			throw new exception(__METHOD__ .": no section given (". $section .")");
		}
		
	}//end __construct()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/** 
	 * Sets the active section.
	 * 
	 * @param $section		(str) section to be set as active.
	 * 
	 * @return VOID			(PASS) section was set successfully.
	 * @return exception	(FAIL) problem encountred setting section. 
	 */
	public function set_active_section($section) {
		if($this->isInitialized === true) {
			$section = strtoupper($section);
			if(in_array($section, $this->configSections)) {
				$this->activeSection = $section;
			}
			else {
				throw new exception(__METHOD__ .": invalid section (". $section .")");
			}
		}
		else {
			throw new exception(__METHOD__ .": not initialized");
		}
	}//end set_active_section($section)
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Parse the configuration file.  Handles replacing {VARIABLES} in values, 
	 * sets items as global or as constants, and creates array indicating the 
	 * available sections from the config file.
	 * 
	 * @param VOID			(void) no arguments accepted.
	 * 
	 * @return NULL			(PASS) successfully parsed configuration
	 * @return exception	(FAIL) exception indicates problem encountered.
	 */
	private function parse_config() {
		if(is_object($this->xmlReader)) {
			$data = $this->xmlReader->get_path($this->xmlReader->get_root_element());
			unset($data[cs_phpxmlCreator::attributeIndex]);
			$specialVars = $this->build_special_vars();
			$parseThis = array();
			
			$this->configSections = array();
			
			foreach($data as $section=>$secData) {
				//only handle UPPERCASE index names....
				//TODO: take this (above) requirement out, as cs-phpxml doesn't require everything to be upper-case.
				if($section == strtoupper($section)) {
					$this->configSections[] = $section;
					
					//TODO: use method (i.e. $this->xmlReader->get_attribute($path)) to retrieve attributes.
					if(isset($secData[cs_phpxmlCreator::attributeIndex]) && is_array($secData[cs_phpxmlCreator::attributeIndex])) {
						//TODO: use method (i.e. $this->xmlReader->get_attribute($path)) to retrieve attributes.
						$sectionAttribs = $secData[cs_phpxmlCreator::attributeIndex];
						unset($secData[cs_phpxmlCreator::attributeIndex]);
						
						//put stuff into the globals scope...
						if(isset($sectionAttribs['SETGLOBAL'])) {
							$path = $section;
							
							$setPath = $path;
							if(strlen($sectionAttribs['GLOBALARRAYLOCATION'])) {
								$setPath = $sectionAttribs['GLOBALARRAYLOCATION'];
							}
							$this->setGlobalArrays[$path] = $setPath;
						}
					}
					
					$secData = $secData[0];
					$tSectionAttribs = null;
					if(isset($secData[cs_phpxmlCreator::attributeIndex])) {
						$tSectionAttribs = $secData[cs_phpxmlCreator::attributeIndex];
						unset($secData[cs_phpxmlCreator::attributeIndex]);
					}
					foreach($secData as $itemName=>$itemValue) {
						$attribs = array();
						//TODO: use method (i.e. $this->xmlReader->get_attribute($path)) to retrieve attributes.
						if(isset($itemValue[0][cs_phpxmlCreator::attributeIndex]) && is_array($itemValue[0][cs_phpxmlCreator::attributeIndex])) {
							//TODO: use method (i.e. $this->xmlReader->get_attribute($path)) to retrieve attributes.
							$attribs = $itemValue[0][cs_phpxmlCreator::attributeIndex];
						}
						//TODO: use method (i.e. $this->xmlReader->get_value($path)) to retrieve tag value.
						if(isset($itemValue[0][cs_phpxmlCreator::dataIndex])) {
							//TODO: use method (i.e. $this->xmlReader->get_value($path)) to retrieve tag value.
							$itemValue = $itemValue[0][cs_phpxmlCreator::dataIndex];
						}
						else {
							$itemValue = null;
						}
						if(preg_match("/{/", $itemValue)) {
							$origVal = $itemValue;
							
							//remove double-slashes (//)
							$itemValue = preg_replace('/[\/]{2,}/', '\/', $itemValue);
							
							//remove leading slash for string replaces (i.e. "{/MAIN/SITE_ROOT}" becomes "{MAIN/SITE_ROOT}")
							$itemValue = preg_replace('/{\//', '{', $itemValue);
							
							//replace special vars.
							$itemValue = $this->gfObj->mini_parser($itemValue, $specialVars, '{', '}');
							
							//replace internal vars.
							$itemValue = $this->gfObj->mini_parser($itemValue, $parseThis, '{', '}');
						}
						
						if(isset($attribs['CLEANPATH'])) {
							$itemValue = $this->fs->resolve_path_with_dots($itemValue);
						}
						
						$parseThis[$itemName] = $itemValue;
						$parseThis[$section ."/". $itemName] = $itemValue;
						$data[$section][$itemName][cs_phpxmlCreator::dataIndex] = $itemValue;
						
						$setVarIndex = $this->setVarPrefix . $itemName;
						if(isset($attribs['SETGLOBAL'])) {
							$GLOBALS[$setVarIndex] = $itemValue;
						}
						if(isset($attribs['SETCONSTANT'])) {
							if(isset($attribs['SETCONSTANTPREFIX'])) {
								//did they give a specific prefix, or just a number/true?
								if(strlen($attribs['SETCONSTANTPREFIX']) == 1) {
									$setVarIndex = $section ."-". $setVarIndex;
								}
								else {
									//use the prefix they gave.
									$setVarIndex = $attribs['SETCONSTANTPREFIX'] ."-". $setVarIndex;
								}
							}
							if(!defined($setVarIndex)) {
								define($setVarIndex, $itemValue);
							}
						}
					}
				}
			}
			
			$this->a2p = new cs_arrayToPath($data);
			$this->isInitialized=true;
			
			if(count($this->setGlobalArrays)) {
				$globA2p = new cs_arrayToPath($GLOBALS);
				foreach($this->setGlobalArrays as $configPath=>$globalsPath) {
					if($this->a2p->get_data($configPath)) {
						$setMe = array();
						foreach($this->a2p->get_data($configPath) as $i=>$v) {
							$setMe[$i] = $v[cs_phpxmlCreator::dataIndex];
						}
						$globA2p->set_data($globalsPath, $setMe);
					}
					else {
						throw new exception(__METHOD__ .": attempted to set global array from non-existent path (". $configPath .")");
					}
				}
			}
		}
		else {
			throw new exception(__METHOD__ .": xmlReader not created, object probably not initialized");
		}
	}//end parse_config()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Retrieve all data about the given section.
	 * 
	 * @param $section		(str) section to retrieve.
	 * 
	 * @return array		(PASS) array contains section data.
	 * @return exception	(FAIL) exception indicates problem.
	 */
	public function get_section($section) {
		if($this->isInitialized === true) {
			$section = strtoupper($section);
			$data = $this->a2p->get_data($section .'/0');
			
			if(is_array($data) && count($data)) {
				$retval = $data;
			}
			else {
				throw new exception(__METHOD__ .": invalid section (". $section .") or no data::: ". $this->gfObj->debug_print($data,0));
			}
		}
		else {
			throw new exception(__METHOD__ .": not initialized");
		}
		
		return($retval);
	}//end get_section()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Retrieves value from the active section, or from another (other sections 
	 * specified like "SECTION/INDEX").
	 * 
	 * @param $index		(str) index name of value to retrieve.
	 * 
	 * @return mixed		(PASS) returns value of given index.
	 * 
	 * NOTE::: this will return NULL if the given index or section/index does
	 * not exist.
	 */
	public function get_value($index) {
		if($this->isInitialized === true) {
			if(preg_match("/\//", $index)) {
				//section NOT given, assume they're looking for something in the active section.
				$index = $this->activeSection ."/". $index;
			}
			$retval = $this->a2p->get_data($index .'/'. cs_phpxmlCreator::dataIndex);
		}
		else {
			throw new exception(__METHOD__ .": not initialized");
		}
		return($retval);
	}//end get_value()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	/**
	 * Retrieves list of valid configuration sections, as defined by 
	 * parse_config().
	 * 
	 * @param VOID			(void) no parameters accepted.
	 * 
	 * @return array		(PASS) array holds list of valid sections.
	 * @return exception	(FAIL) exception gives error.
	 */
	public function get_valid_sections() {
		if($this->isInitialized === true) {
			if(is_array($this->configSections) && count($this->configSections)) {
				$retval = $this->configSections;
			}
			else {
				throw new exception(__METHOD__ .": no sections defined, probably invalid configuration");
			}
		}
		else {
			throw new exception(__METHOD__ .": not initialized");
		}
		
		return($retval);
	}//end get_valid_sections()
	//-------------------------------------------------------------------------
	
	
	
	//-------------------------------------------------------------------------
	private function build_special_vars() {
		//determine the current "APPURL" (current URL minus hostname and current filename)
		{
			$appUrl = $_SERVER['SCRIPT_NAME'];
			$bits = explode('/', $appUrl);
			if(!strlen($bits[0])) {
				array_shift($bits);
			}
			if(count($bits)) {
				array_pop($bits);
			}
			if(!count($bits)) {
				$appUrl = '/';
			}
			else {
				$appUrl = '/'. $this->gfObj->string_from_array($bits, null, '/');
			}
		}
		
		$specialVars = array(
			'_DIRNAMEOFFILE_'	=> $this->configDirname,
			'_CONFIGFILE_'		=> $this->configFile,
			'_THISFILE_'		=> $this->configFile,
			'_APPURL_'			=> $appUrl
		);
		return($specialVars);	
	}//end build_special_vars()
	//-------------------------------------------------------------------------
	
}//end cs_siteConfig

?>
