<?php

class testOfCSPermission extends testDbAbstract {
	public function test_bitwise() {
		new _perm(); //this ensures the class file has been included
		
		$gf = new cs_globalFunctions();
		
		$perms = array(
			'crud'	=> 15,
			'rud'	=> 14,
			'cud'	=> 13,
			'ud'	=> 12,
			'crd'	=> 11,
			'rd'	=> 10,
			'cd'	=> 9,
			'd'		=> 8,
			'cru'	=> 7,
			'ru'	=> 6,
			'cu'	=> 5,
			'u'		=> 4,
			'cr'	=> 3,
			'r'		=> 2,
			'c'		=> 1,
			''		=> 0
		);
		
		
		
		foreach($perms as $name=>$permVal) {
			$bits = preg_split('//', $name, -1, PREG_SPLIT_NO_EMPTY);
			#$gf->debug_print($bits,1);
			
			$hasC = $permVal & CS_CREATE;
			$testInt = 0;
			
			if(in_array('c', $bits)) {
				$this->assertEqual($hasC, CS_CREATE, "Permission string '". $name ."' *should* have 'c', but doesn't (". $hasC ." & ". CS_CREATE .")");
				$testInt |= CS_CREATE;
			}
			else {
				$this->assertNotEqual($hasC, CS_CREATE, "'". $name ."' should *not* have 'c', but does (". $hasC ." & ". CS_CREATE .")");
			}
			
			$hasR = $permVal & CS_READ;
			if(in_array('r', $bits)) {
				$this->assertEqual($hasR, CS_READ, "Permission string '". $name ."' *should* have 'r', but doesn't (". $hasR ." & ". CS_READ .")");
				$testInt |= CS_READ;
			}
			else {
				$this->assertNotEqual($hasR, CS_READ, "Permission string '". $name ."' should *not* have 'r', but does (". $hasR ." & ". CS_READ .")");
			}
			
			#
			$hasU = $permVal & CS_UPDATE;
			if(in_array('u', $bits)) {
				$this->assertEqual($hasU, CS_UPDATE, "Permission string '". $name ."' *should* have 'u', but doesn't (". $hasU ." & ". CS_UPDATE .")");
				$testInt |= CS_UPDATE;
			}
			else {
				$this->assertNotEqual($hasU, CS_UPDATE, "Permission string '". $name ."' should *not* have 'u', but does (". $hasU ." & ". CS_UPDATE .")");
			}
			
			$hasD = $permVal & CS_DELETE;
			if(in_array('d', $bits)) {
				$this->assertEqual($hasD, CS_DELETE, "Permission string '". $name ."' *should* have 'd', but doesn't (". $hasD ." & ". CS_DELETE .")");
				$testInt |= CS_DELETE;
			}
			else {
				$this->assertNotEqual($hasD, CS_DELETE, "Permission string '". $name ."' should *not* have 'd', but does (". $hasD ." & ". CS_DELETE .")");
			}
			
			$this->assertEqual($testInt, $permVal);
			
		}
		
	}
	
	public function test_locationCleaning() {
		$p = new _perm();
		$tests = array(
			'/x/y/z'			=> '/x/y/z',
			'/x/y/z/'			=> '/x/y/z/',
			'/x/y/z@test'		=> '/x/y/z@test',
			'/X/Y/Z@TEST'		=> '/x/y/z@TEST',
			'/x/Y/z@TeST/yZ'	=> '/x/y/z@TeST/yZ',		//even URL-like stuff in the special property doesn't get lowered
			'//x//y//z'			=> '/x/y/z',
			'//x/y/z@test///X'	=> '/x/y/z@test///X'		//extra slashes in the special property don't get cleansed
		);
		
		foreach($tests as $originalUrl => $expectedUrl) {
			$cleanedUrl = $p->clean_location($originalUrl);
			$this->assertEqual($cleanedUrl, $expectedUrl);
		}
	}
	
	
	public function test_permFromLocation() {
		$p = new _perm();
		$perms = array(
			'crud'	=> 15,
			'rud'	=> 14,
			'cud'	=> 13,
			'ud'	=> 12,
			'crd'	=> 11,
			'rd'	=> 10,
			'cd'	=> 9,
			'd'		=> 8,
			'cru'	=> 7,
			'ru'	=> 6,
			'cu'	=> 5,
			'u'		=> 4,
			'cr'	=> 3,
			'r'		=> 2,
			'c'		=> 1,
			''		=> 0
		);
		
		foreach($perms as $str => $val) {
			$checkThis = $p->get_perms_from_string($str);
			$this->assertEqual($val, $checkThis, "Permission '". $str ."' doesn't match numericially... expected '". $val ."', got '". $checkThis ."'");
		}
	}
}

class _perm extends cs_permission {
	public function __construct() {
		$this->gf = new cs_globalFunctions();
	}
}

