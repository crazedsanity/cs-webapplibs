<?php
/*
 * Created on Jan 25, 2009
 * 
 * FILE INFORMATION:
 * 
 * $HeadURL$
 * $Id$
 * $LastChangedDate$
 * $LastChangedBy$
 * $LastChangedRevision$
 */



class testOfCSVersionParse extends UnitTestCase {
	
	function __construct() {
		$this->gfObj = new cs_globalFunctions;
	}//end __construct()
	
	
	//--------------------------------------------------------------------------
	function test_basics() {
		
		$tests = array(
			'files/version1'	=> array(
				'0.1.2-ALPHA8754',
				'test1',
				array(
					'version_major'			=> 0,
					'version_minor'			=> 1,
					'version_maintenance'	=> 2,
					'version_suffix'		=> 'ALPHA8754'
				)
			),
			'files/version2'	=> array(
				'5.4.0',
				'test2',
				array(
					'version_major'			=> 5,
					'version_minor'			=> 4,
					'version_maintenance'	=> 0,
					'version_suffix'		=> null
				)
			),
			'files/version3'	=> array(
				'5.4.3-BETA5543',
				'test3 stuff',
				array(
					'version_major'			=> 5,
					'version_minor'			=> 4,
					'version_maintenance'	=> 3,
					'version_suffix'		=> 'BETA5543'
				)
			)
		);
		
		foreach($tests as $fileName=>$expectedArr) {
			$ver = new middleTestClass();
			$ver->set_version_file_location(dirname(__FILE__) .'/'. $fileName);
			
			$this->assertEqual($expectedArr[0], $ver->get_version(), "Failed to match string from file (". $fileName .")");
			$this->assertEqual($expectedArr[1], $ver->get_project(), "Failed to match project from file (". $fileName .")");
			
			//now check that pulling the version as an array is the same...
			$checkItArr = $ver->get_version(true);
			$expectThis = $expectedArr[2];
			$expectThis['version_string'] = $expectedArr[0];
		}
	}//end test_basics()
	//--------------------------------------------------------------------------
}


class middleTestClass extends cs_versionAbstract {
	function __construct(){}
} 
?>
