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
	
	
	
	//--------------------------------------------------------------------------
	function test_check_higher() {
		
		//NOTE: the first item should ALWAYS be higher.
		$tests = array(
			'basic, no suffix'	=> array('1.0.1', '1.0.0'),
			'basic + suffix'	=> array('1.0.0-ALPHA1', '1.0.0-ALPHA0'),
			'basic w/o maint'	=> array('1.0.1', '1.0'),
			'suffix check'		=> array('1.0.0-BETA1', '1.0.0-ALPHA1'),
			'suffix check2'		=> array('1.0.0-ALPHA10', '1.0.0-ALPHA1'),
			'suffix check3'		=> array('1.0.1', '1.0.0-RC1')
		);
		
		foreach($tests as $name=>$checkData) {
			$ver = new middleTestClass;
			$this->assertTrue($ver->is_higher_version($checkData[1], $checkData[0]));
			$this->assertFalse($ver->is_higher_version($checkData[0], $checkData[1]));
		}
		
		//now check to ensure there's no problem with parsing equivalent versions.
		$tests = array(
			'no suffix'				=> array('1.0', '1.0.0'),
			'no maint + suffix'		=> array('1.0-ALPHA1', '1.0.0-ALPHA1'),
			'no maint + BETA'		=> array('1.0-BETA5555', '1.0.0-BETA5555'),
			'no maint + RC'			=> array('1.0-RC33', '1.0.0-RC33'),
			'maint with space'		=> array('1.0-RC  33', '1.0.0-RC33'),
			'extra spaces'			=> array(' 1.0   ', '1.0.0')
		);
		foreach($tests as $name=>$checkData) {
			$ver = new middleTestClass;
			
			//rip apart & recreate first version to test against the expected...
			$derivedFullVersion = $ver->build_full_version_string($ver->parse_version_string($checkData[0]));
			$this->assertEqual($derivedFullVersion, $checkData[1], "TEST=(". $name ."): derived version " .
					"(". $derivedFullVersion .") doesn't match expected (". $checkData[1] .")");
			
			//now rip apart & recreate the expected version (second) and make sure it matches itself.
			$derivedFullVersion = $ver->build_full_version_string($ver->parse_version_string($checkData[1]));
			$this->assertEqual($derivedFullVersion, $checkData[1], "TEST=(". $name ."): derived version " .
					"(". $derivedFullVersion .") doesn't match expected (". $checkData[1] .")");
		}
		
		
	}//end test_check_higher()
	//--------------------------------------------------------------------------
}


class middleTestClass extends cs_versionAbstract {
	function __construct(){}
} 
?>
