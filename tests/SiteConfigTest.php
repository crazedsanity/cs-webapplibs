<?php


class SiteConfigTest extends PHPUnit_Framework_TestCase {
	
	/**
	 * To ensure SimpleXML-based queries work (for removing dependencies on 
	 * PHPXML)...
	 */
	public function testSimpleXML() {
		$myFile = dirname(__FILE__) .'/files/siteConfig.xml';
		$this->assertFileExists($myFile);
		
		$x = new SimpleXMLElement(file_get_contents($myFile));
		
		$this->assertEquals('2008-12-18 10:21:00', $x->attributes()->created);
		$this->assertEquals('', $x->attributes()->updated);
		
		$this->assertTrue(isset($x->website));
		$this->assertEquals('sanitizeDirs', $x->website->attributes()->fix);
		
		$siteRoot = $x->website->SITE_ROOT;
		$this->assertTrue(is_object($siteRoot));
		$this->assertEquals('{_DIRNAMEOFFILE_}/..', "$siteRoot");//basically, the object is cast into a string which is the value of the tag.
		
		
	}
	
	
	/**
	 * Turns out there's no real good way to view the parsed data without 
	 * evaluating GLOBALS and constants.  Ick.
	 */
	public function testConfig() {
		$this->assertFalse(defined('SITE_ROOT'));
		
		$configFile = dirname(__FILE__) .'/files/siteConfig.xml';
		$this->assertTrue(file_exists($configFile));
		$x = new cs_siteConfig($configFile, null);
		$this->assertTrue(is_object($x));
		$this->assertTrue(is_array($x->config));
		
		
		$this->assertTrue(is_array($GLOBALS));
		
		$myFs = new cs_fileSystem(dirname(__FILE__));
		
		$this->assertEquals($myFs->resolve_path_with_dots(dirname($configFile) .'/..'), $GLOBALS['SITE_ROOT']);
		$this->assertEquals($GLOBALS['SITE_ROOT'], $GLOBALS['SITEROOT']);
		
		//BUG!!!! see https://github.com/crazedsanity/cs-webapplibs/issues/26 
		$this->assertEquals(constant('SITE_ROOT'), $GLOBALS['SITE_ROOT']);
		
		
		$this->assertEquals('CS_SESSID', constant('SESSION_NAME'));
		$this->assertTrue(!isset($GLOBALS['SESSION_NAME']));
		
		$this->assertEquals(constant('session_db_host'), constant('DB_PG_HOST'));
		$this->assertEquals(constant('cs_webdbupgrade-RWDIR'), constant('CS_RWDIR'));
		
		$this->assertFalse(isset($GLOBALS['API_AUTHTOKEN']));
		$this->assertFalse(defined('API_AUTHTOKEN'));
	}

}
