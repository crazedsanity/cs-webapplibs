<?php
/*
 * Created on January 26, 2011
 * 
 * FILE INFORMATION:
 * 
 * $HeadURL$
 * $Id$
 * $LastChangedDate$
 * $LastChangedBy$
 * $LastChangedRevision$
 */

class cs_urlBasedPermission extends cs_genericPermission {
	
	
	//============================================================================
	/**
	 * Permission system for Web URLs; so "http://web.site.com/index" can have 
	 * special permissions.  The most important part is that a permission set for 
	 * the URL "/" might have a setting, and "/x/y/z" might as well, without 
	 * anything for the interim URLs ("/x", "/x/y", "/x/y/z"); those missing URLs 
	 * are given the permissions for the closest URL (in this case "/").
	 */
	public function __construct(cs_phpDB $db) {
		$this->db = $db;
		$this->gfObj = new cs_globalFunctions;
		parent::__construct($db);
	}//end __construct()
	//============================================================================
	
	
	
	//============================================================================
	/**
	 * Break the URL into bits (delimited by "/"), and return an array.
	 */
	private function _get_url_bits($url) {
		$url = $this->gfObj->clean_url($url);
		if(!is_array($url)) {
			$bits = array("/");
		}
		else {
			$bits = explode("/", $url);
		}
		return($bits);
	}//end _get_url_bits()
	//============================================================================
	
	
	
	//============================================================================
	//============================================================================
}
