<?php

class IdObfuscatorTest extends testDbAbstract {
	
	
	public function __construct() {
		parent::__construct();
	}//end __construct()
	
	public function setUp() {}
	public function tearDown(){}
	
	
	
	public function test_smallNumbers() {
		
		if(!defined('CRYPT_SALT')) {
			define('CRYPT_SALT', microtime(true));
		}
		
		for($i=1; $i<1000; $i++) {
			
			$idToEncrypt = $i;
			
			$encoded = cs_idObfuscator::encode($idToEncrypt);
			$decoded = cs_idObfuscator::decode($encoded);
			
			$this->assertNotEquals($idToEncrypt, $encoded);
			#$this->assertFalse($encoded == $decoded);
			$this->assertEquals($idToEncrypt, $decoded);
		}
	}
	
	
	public function test_bigNumbers() {
		
		if(!defined('CRYPT_SALT')) {
			define('CRYPT_SALT', microtime(true));
		}
		
		for($i=1; $i<1000; $i++) {
			
			$idToEncrypt = ($i + rand(99999, 999999999));
			
			$encoded = cs_idObfuscator::encode($idToEncrypt);
			$decoded = cs_idObfuscator::decode($encoded);
			
			$this->assertNotEquals($idToEncrypt, $encoded);
			#$this->assertFalse($encoded == $decoded);
			$this->assertEquals($idToEncrypt, $decoded);
		}
	}
	
	
	/*
	 * This test represents a known failure with the ID Obfuscator.
	 */
	public function test_failureOnBigNumbers() {
		//This test represents where obfuscation works, and where it fails
		
		$startPoint = 2147483647;// maximum integer on a 32-bit system.
		$this->assertEquals($startPoint, cs_idObfuscator::decode(cs_idObfuscator::encode($startPoint)));
		
		for($i=1; $i<1000; $i++) {
			$testVal = $startPoint + 1 + $i;
			$this->assertEquals(0, cs_idObfuscator::decode(cs_idObfuscator::encode($testVal)));
		}
	}
}