<?php
/*
 * Created on Jan 9, 2007
 * 
 */

require_once(dirname(__FILE__) .'/abstract/cs_content.abstract.class.php');


class cs_tabs extends cs_contentAbstract {
	private $tabsArr=array();
	private $selectedTab;
	
	private $templateVar;
	private $gfObj;
	
	/** This is the default suffix to use when none is given during the add_tab() call. */
	private $defaultSuffix='tab';
	
	//---------------------------------------------------------------------------------------------
	/**
	 * Build the object, and parses the given template.  Tabs must be added & selected manually.
	 * 
	 * @param $csPageObj	(object) Instance of the class "cs_genericPage".
	 * @param $templateVar	(str,optional) What template var to find the tab blockrows in.
	 */
	public function __construct($templateVar="tabs") {
		parent::__construct(false);
		
		if(is_object($templateVar)) {
			//trying to pass cs_genericPage{}... tell 'em we don't like that anymore.
			throw new exception(__METHOD__ .": got an object (". get_class($templateVar) .") instead of template var name");
		}
		elseif(is_string($templateVar) && is_null($templateVar) || strlen($templateVar) < 3) {
			//no template name?  AHH!!!
			throw new exception("cs_tabs::__construct(): failed to specify proper template file");
		}
		else {
			//set the internal var.
			$this->templateVar = $templateVar;
		}
		
		$this->gfObj = new cs_globalFunctions;
	}//end __construct()
	//---------------------------------------------------------------------------------------------
	
	
	
	//---------------------------------------------------------------------------------------------
	public function add_tab_array(array $tabs, $useSuffix=null) {
		$retval = 0;
		foreach($tabs as $name=>$url) {
			//call an internal method to do it.
			$retval += $this->add_tab($name, $url, $useSuffix);
		}
		
		return($retval);
	}//end add_tab_array()
	//---------------------------------------------------------------------------------------------
	
	
	
	//---------------------------------------------------------------------------------------------
	/**
	 * Sets the given tab as selected, provided it exists.
	 * 
	 * @param $tabName		(str) Sets this tab as selected.
	 * @return (void)
	 */
	public function select_tab($tabName) {
		$this->selectedTab = $tabName;
	}//end select_tab()
	//---------------------------------------------------------------------------------------------
	
	
	
	//---------------------------------------------------------------------------------------------
	public function add_tab($tabName, $url, $useSuffix=null) {
		
		//set the default suffix.
		if(is_null($useSuffix)) {
			$useSuffix = $this->defaultSuffix;
		}
		
		//add it to an array.
		$this->tabsArr[$tabName] = array(
			'url'		=> $url,
			'suffix'	=> $useSuffix
		);
	}//end add_tab()
	//---------------------------------------------------------------------------------------------
	
	
	
	//---------------------------------------------------------------------------------------------
	/**
	 * Call this to add the parsed tabs into the page.
	 */
	public function display_tabs(array $blockRows) {
		
		if(!strlen($this->selectedTab)) {
			$keys = array_keys($this->tabsArr);
			$this->select_tab($keys[0]);
		}
		
		if(is_array($this->tabsArr) && count($this->tabsArr)) {
			$finalString = "";
			//loop through the array.
			foreach($this->tabsArr as $tabName=>$tabData) {
				
				$url = $tabData['url'];
				$suffix = $tabData['suffix'];
				
				$blockRowName = 'unselected_'. $suffix;
				if(strtolower($tabName) == strtolower($this->selectedTab)) {
					$blockRowName = 'selected_'. $suffix;
				}
				
				if(isset($blockRows[$blockRowName])) {
					$useTabContent = $blockRows[$blockRowName];
				}
				else {
					throw new exception(__METHOD__ ."(): failed to load block row " .
							"(". $blockRowName .") for tab (". $tabName .")". 
							$this->gfObj->debug_print($blockRows,0));
				}
				
				$parseThis = array(
					'title'			=> $tabName,
					'url'			=> $url,
					'cleanTitle'	=> preg_replace('/[^a-zA-Z0-9]/', '_', $tabName)
				);
				$finalString .= $this->gfObj->mini_parser($useTabContent, $parseThis, '%%', '%%');
			}
		}
		else {
			//something bombed.
			throw new exception(__METHOD__ ."(): no tabs to add");
		}
		
		return($finalString);
	}//end display_tabs()
	//---------------------------------------------------------------------------------------------
	
	
	//---------------------------------------------------------------------------------------------
	/**
	 * Determine if the given named tab exists (returns boolean true/false)
	 */
	public function tab_exists($tabName) {
		$retval = false;
		if(isset($this->tabsArr[$tabName])) {
			$retval = true;
		}
		return($retval);
	}//end tab_exists()
	//---------------------------------------------------------------------------------------------
	
	
	
	//---------------------------------------------------------------------------------------------
	public function rename_tab($tabName, $newTabName) {
		if($this->tab_exists($tabName) && !$this->tab_exists($newTabName)) {
			$tabContents = $this->tabsArr[$tabName];
			unset($this->tabsArr[$tabName]);
			$this->tabsArr[$newTabName] = $tabContents;
		}
		else {
			throw new exception(__METHOD__ .": tried to rename non-existent tab (". $tabName .") to (". $newTabName .")");
		}
	}//end rename_tab();
	//---------------------------------------------------------------------------------------------
	
}
?>
