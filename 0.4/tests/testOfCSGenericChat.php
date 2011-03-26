<?php
/*
 * FILE INFORMATION:
 * 
 * $HeadURL$
 * $Id$
 * $LastChangedDate$
 * $LastChangedBy$
 * $LastChangedRevision$
 */

class testOfCSGenericChat extends testDbAbstract {
	
	
	//--------------------------------------------------------------------------
	public function __construct() {
	}//end __construct()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	function setUp() {
		$this->gfObj = new cs_globalFunctions;
		$this->gfObj->debugPrintOpt=1;
		parent::__construct('postgres','', 'localhost', '5432');

	}//end setUp()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function tearDown() {
		if(isset($GLOBALS['keepDb'])) {
			unset($GLOBALS['keepDb']);
		}
		else {
			$this->destroy_db();
		}
	}
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function test_chatCategories() {
	}//end test_chatCategories()
	//--------------------------------------------------------------------------
	
	
	
}
