<?php

class testOfCSPermission extends testDbAbstract {
	public function test_bitwise() {
		$p = new _perm(); //this ensures the class file has been included
		
		//$gf = new cs_globalFunctions();
		
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
				$this->assertEquals($hasC, CS_CREATE, "Permission string '". $name ."' *should* have 'c', but doesn't (". $hasC ." & ". CS_CREATE .")");
				$testInt |= CS_CREATE;
				$this->assertTrue($p->can_create($name));
				$this->assertTrue($p->can_create($permVal));
			}
			else {
				$this->assertNotEquals($hasC, CS_CREATE, "'". $name ."' should *not* have 'c', but does (". $hasC ." & ". CS_CREATE .")");
				$this->assertFalse($p->can_create($name));
				$this->assertFalse($p->can_create($permVal));
			}
			
			$hasR = $permVal & CS_READ;
			if(in_array('r', $bits)) {
				$this->assertEquals($hasR, CS_READ, "Permission string '". $name ."' *should* have 'r', but doesn't (". $hasR ." & ". CS_READ .")");
				$testInt |= CS_READ;
				$this->assertTrue($p->can_read(($name)));
				$this->assertTrue($p->can_read($permVal));
			}
			else {
				$this->assertNotEquals($hasR, CS_READ, "Permission string '". $name ."' should *not* have 'r', but does (". $hasR ." & ". CS_READ .")");
				$this->assertFalse($p->can_read($name));
				$this->assertFalse($p->can_read($permVal));
			}
			
			#
			$hasU = $permVal & CS_UPDATE;
			if(in_array('u', $bits)) {
				$this->assertEquals($hasU, CS_UPDATE, "Permission string '". $name ."' *should* have 'u', but doesn't (". $hasU ." & ". CS_UPDATE .")");
				$testInt |= CS_UPDATE;
				$this->assertTrue($p->can_update($name));
				$this->assertTrue($p->can_update($permVal));
			}
			else {
			$this->assertNotEquals($hasU, CS_UPDATE, "Permission string '". $name ."' should *not* have 'u', but does (". $hasU ." & ". CS_UPDATE .")");
				$this->assertFalse($p->can_update($name));
				$this->assertFalse($p->can_update($permVal));
			}
			
			$hasD = $permVal & CS_DELETE;
			if(in_array('d', $bits)) {
				$this->assertEquals($hasD, CS_DELETE, "Permission string '". $name ."' *should* have 'd', but doesn't (". $hasD ." & ". CS_DELETE .")");
				$testInt |= CS_DELETE;
				$this->assertTrue($p->can_delete($name));
				$this->assertTrue($p->can_delete($permVal));
			}
			else {
				$this->assertNotEquals($hasD, CS_DELETE, "Permission string '". $name ."' should *not* have 'd', but does (". $hasD ." & ". CS_DELETE .")");
				$this->assertFalse($p->can_delete($name));
				$this->assertFalse($p->can_delete($permVal));
			}
			
			$this->assertEquals($testInt, $permVal);
			
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
			$this->assertEquals($cleanedUrl, $expectedUrl);
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
			$this->assertEquals($val, $checkThis, "Permission '". $str ."' doesn't match numericially... expected '". $val ."', got '". $checkThis ."'");
		}
	}
	
	
	public function test_pathParts() {
		$p = new _perm();
		$tests = array(
			'/x/y/z'	=> array(
				0	=> '/',
				1	=> '/x',
				2	=> '/x/',
				3	=> '/x/y',
				4	=> '/x/y/',
				5	=> '/x/y/z'
			),
			'/X/y/Z/'	=> array(
				0	=> '/',
				1	=> '/x',
				2	=> '/x/',
				3	=> '/x/y',
				4	=> '/x/y/',
				5	=> '/x/y/z',
				6	=> '/x/y/z/'
			),
			'/path/to/data/@testProperty'	=> array(
				0	=> '/',
				1	=> '/path',
				2	=> '/path/',
				3	=> '/path/to',
				4	=> '/path/to/',
				5	=> '/path/to/data',
				6	=> '/path/to/data/',
				'_'	=> 'testProperty'
			),
		);
		
		foreach($tests as $path=>$expected) {
			$actual = $p->get_path_parts($path);
			$this->assertEquals($expected, $actual, "Missing some paths while testing (". $path .")"); #: ". $p->gf->debug_print($actual,0));
		}
	}
}

class _perm extends cs_permission {
	public function __construct() {
		$this->gf = new cs_globalFunctions();
	}
}

