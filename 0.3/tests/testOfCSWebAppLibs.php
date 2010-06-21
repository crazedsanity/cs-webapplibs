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

class testOfCSWebAppLibs extends UnitTestCase {
	
	//--------------------------------------------------------------------------
	function __construct() {
		$this->gfObj = new cs_globalFunctions;
		$this->gfObj->debugPrintOpt=1;
		if(!defined('CS_UNITTEST')) {
			throw new exception(__METHOD__ .": FATAL: constant 'CS_UNITTEST' not set, can't do testing safely");
		}
	}//end __construct()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	private function create_dbconn() {
		$dbParams = array(
			'host'		=> constant('cs_webapplibs-DB_CONNECT_HOST'),
			'dbname'	=> constant('cs_webapplibs-DB_CONNECT_DBNAME'),
			'user'		=> constant('cs_webapplibs-DB_CONNECT_USER'),
			'password'	=> constant('cs_webapplibs-DB_CONNECT_PASSWORD'),
			'port'		=> constant('cs_webapplibs-DB_CONNECT_PORT')
		);
		$db = new cs_phpDB(constant('DBTYPE'));
		$db->connect($dbParams);
		return($db);
	}//end create_dbconn()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	private function remove_tables() {
		$tableList = array(
			'cswal_auth_token_table', 'cswal_version_table', 'cswal_attribute_table', 
			'cswal_category_table', 'cswal_class_table', 'cswal_event_table', 
			'cswal_log_attribute_table', 'cswal_log_table', 'cswal_session_store_table',
			'cswal_gdl_object_table', 'cswal_gdl_path_table', 'cswal_gdl_attribute_table'
		);
		
		$db = $this->create_dbconn();
		foreach($tableList as $name) {
			try {
				$db->run_update("DROP TABLE ". $name ." CASCADE", true);
			}
			catch(exception $e) {
				//force an error.
				//$this->assertTrue(false, "Error while dropping (". $name .")::: ". $e->getMessage());
			}
		}
	}//end remove_tables()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	function test_token_basics() {
		$db = $this->create_dbconn();
		$this->remove_tables();
		$tok = new authTokenTester($db);
		
		//Generic test to ensure we get the appropriate data back.
		{
			$tokenData = $tok->create_token(1, 'test', 'abc123');
			$this->do_tokenTest($tokenData, 1, 'test');
			
			$this->assertEqual($tok->authenticate_token($tokenData['id'], 'test', $tokenData['hash']), 1);
			$this->assertFalse($tok->authenticate_token($tokenData['id'], 'testx', $tokenData['hash']));
			$this->assertFalse($tok->authenticate_token($tokenData['id'], 'test', 'abcdefg'));
			$this->assertFalse($tok->authenticate_token($tokenData['id'], 'test', '12345678901234567890123456789012'));
			$this->assertFalse($tok->authenticate_token(99999, 'test', '12345678901234567890123456789012'));
			
			//check to make sure the data within this token shows only ONE attempt.
			$checkData = $tok->tokenData($tokenData['id']);
			$this->assertEqual($checkData['auth_token_id'], $tokenData['id']);
			$this->assertEqual($checkData['total_uses'], 1);
		}
		
		//create a token with only 1 available use and try to authenticate it twice.
		{
			//Generic test to ensure we get the appropriate data back.
			$tokenData = $tok->create_token(1, 'test', 'abc123', null, 1);
			$this->do_tokenTest($tokenData, 1, 'test');
			
			$this->assertEqual($tok->authenticate_token($tokenData['id'], 'test', $tokenData['hash']), 1);
			$this->assertTrue(($tok->authenticate_token($tokenData['id'], 'test', $tokenData['hash']) === null), 
					"Able to authenticate twice on a token with only 1 use");
			$this->assertFalse($tok->tokenData($tokenData['id'], true));
			$this->assertFalse($tok->tokenData($tokenData['id'], false));
		}
		
		
		//now create a token with a maximum lifetime (make sure we can call it a ton of times)
		{
			//Generic test to ensure we get the appropriate data back.
			$tokenData = $tok->create_token(1, 'test', 'abc123', '2 years');
			$this->do_tokenTest($tokenData, 1, 'test');
			
			$this->assertEqual($tok->authenticate_token($tokenData['id'], 'test', $tokenData['hash']), 1);
			$checkAttempts = 100;
			$successAttempts = 0;
			for($i=0; $i < 100; $i++) {
				$id = $tok->authenticate_token($tokenData['id'], 'test', $tokenData['hash']);
				if($this->assertEqual($tok->authenticate_token($tokenData['id'], 'test', $tokenData['hash']), 1)) {
					$successAttempts++;
				}
			}
			$this->assertEqual($checkAttempts, $successAttempts);
		}
		
		//try to create a token with max_uses of 0.
		{
			$tokenData = $tok->create_token(2, 'test', 'xxxxyyyyyxxxx', null, 0);
			$this->do_tokenTest($tokenData, 2, 'test');
			$checkData = $tok->tokenData($tokenData['id']);
			
			$this->assertTrue(is_array($checkData));
			$this->assertEqual($tokenData['id'], $checkData['auth_token_id']);
			$this->assertEqual($checkData['max_uses'], null);
		}
		
		//try creating a token that is purposely expired, make sure it exists, then make sure authentication fails.
		{
			$tokenData = $tok->create_token(88, 'test', 'This is a big old TEST', '-3 days');
			if($this->assertTrue(is_array($tokenData))) {
				$this->do_tokenTest($tokenData, 88, 'test');
				$this->assertFalse($tok->authenticate_token($tokenData['id'], 'test', $tokenData['hash']));
			}
		}
		
		//make sure we don't get the same hash when creating multiple tokens with the same data.
		//NOTE: this pushes the number of tests up pretty high, but I think it is required to help ensure hash uniqueness.
		{
			$uid=rand(1,999999);
			$checksum = 'multiple ToKEN check';
			$hashThis = "Lorem ipsum dolor sit amet. ";
			
			$numTests = 30;
			$numPass = 0;
			$tokenList = array();
			for($i=0;$i<$numTests;$i++) {
				$tokenList[$i] = $tok->create_token($uid, $checksum, $hashThis);
			}
			$lastItem = ($numTests -1);
			for($i=0;$i<$numTests;$i++) {
				$checkHash = $tokenList[$i]['hash'];
				$uniq=0;
				foreach($tokenList as $k=>$a) {
					//check against everything BUT itself.
					if($i != $k && $this->assertNotEqual($checkHash, $a['hash'])) {
						$uniq++;
					}
				}
				$this->assertEqual($uniq, ($numTests -1));
			}
		}
		
		//make sure the hash string isn't guessable, even if they can access our super-secret encryption algorithm. ;)
		{
			$uid = rand(1,99999);
			$checksum = "my birfday";
			$hashThis = "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Quisque ut.";
			
			$tokenData = $tok->create_token($uid, $checksum, $hashThis);
			$this->do_tokenTest($tokenData, $uid, $checksum);
			
			$this->assertNotEqual($tokenData['hash'], $tok->doHash($tokenData['id'], $uid, $checksum, $hashThis), 
					"hash is guessable");
		}
		
		//test expiring tokens...
		{
			//create a token that is immediately expired.
			$tokenData = $tok->create_token(22, 'token expiration test', 'Lorem ipsum dolor sit amet, consectetur.', '-5 days');
			$this->do_tokenTest($tokenData, 22, 'token expiration test');
			
			$this->assertFalse(is_array($tok->tokenData($tokenData['id'], true)));
			$this->assertTrue(is_array($tok->tokenData($tokenData['id'], false)));
			$this->assertTrue(count($tok->tokenData($tokenData['id'],false)) == 9);
			
			//REMEMBER: we've created other tokens that will now expire...
			$removedTokens = $tok->remove_expired_tokens();
			$this->assertEqual(2, $removedTokens);
		}
		
	}//end test_token_basics()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	function test_genericDataLinker() {
		
		$x = new gdlTester($this->create_dbconn());
		
		//test objects & paths first.
		{
			$myPath = '/character/sheet///Tetra Tealeaf';
			
			$this->assertEqual(array('character', 'sheet', 'Tetra Tealeaf'), $x->explode_path($myPath));
			$x->set_base_path('/testing');
			$this->assertEqual('/testing/character/sheet/Tetra Tealeaf', $x->clean_path($myPath));
			$this->assertEqual(array('testing', 'character', 'sheet', 'Tetra Tealeaf'), $x->explode_path($myPath));
			$x->set_base_path(null);
			$this->assertNotEqual(array('testing', 'character', 'sheet', 'Tetra Tealeaf'), $x->explode_path($myPath));
			$this->assertEqual(array('character', 'sheet', 'Tetra Tealeaf'), $x->explode_path($myPath));
			$this->assertEqual('/character/sheet/Tetra Tealeaf', $x->clean_path($myPath));
			
			//now create some objects.
			$pathBits = array();
			foreach($x->explode_path($myPath) as $name) {
				$pathBits[$x->create_object($name)] = $name;
			}
			$newPathIdList = $x->create_path($myPath);
			$myPathIdList = ':'. $this->gfObj->string_from_array(array_keys($pathBits), null, '::') .':';
			$this->assertEqual($newPathIdList, $myPathIdList);
			
			$newId = $x->create_object('testing');
			$t = array_keys($pathBits);
			$t = array_pop($t);
			$lastPathId = $t;
			$this->assertEqual($newId, ($lastPathId +1));
			
			$oldBits = $pathBits;
			$pathBits = array();
			$pathBits[$newId] = 'testing';
			foreach($oldBits as $id=>$name) {
				$pathBits[$id] = $name;
			}
			
			$newPathIdList = $x->create_path('/testing/'. $myPath);
			$myPathIdList = ':'. $this->gfObj->string_from_array(array_flip($pathBits), null, '::') .':';
			$this->assertEqual($newPathIdList, $myPathIdList);
			
			$this->assertEqual($newPathIdList, $x->create_path('/testing/'. $myPath));
			
			$myRearrangedPath = array_reverse($pathBits, true);
			$rPathIdList = ':'. $this->gfObj->string_from_array(array_flip($myRearrangedPath), null, '::') .':';
			$rPath = '/'. $this->gfObj->string_from_array($myRearrangedPath, null, '/');
			$this->assertEqual($x->create_path($rPath), $rPathIdList);
			
			$this->assertEqual($x->get_path_from_idlist($x->create_path($rPath)), $x->get_path_from_idlist($rPathIdList));
			$this->assertEqual($x->get_path_from_idlist($x->create_path($rPath)), $x->get_path_from_idlist($rPathIdList));
			$this->assertEqual($x->get_path_from_idlist($x->create_path($rPath)), $x->get_path_from_idlist($rPathIdList));
			$this->assertEqual($x->get_path_from_idlist($x->create_path($rPath)), $x->get_path_from_idlist($rPathIdList));
		}
		
		
		//basic tests for building text-based paths vs. id-based paths.
		{
			$myPath = '/character/sheet/Tetra Tealeaf';
			
			
			$idList = $x->createPathObjects($myPath);
			
			$testObjectIdList = explode('/', preg_replace('/^\//', '', $myPath));
			$this->assertEqual($idList, $x->build_object_id_list($testObjectIdList));
			
			$idList2 = $x->create_path($myPath);
			$this->assertEqual($x->create_id_path(array_values($idList)), $idList2);
			
			$this->assertEqual($myPath, $x->get_path_from_idlist($x->create_id_path($idList)));
			$this->gfObj->debug_var_dump($x->get_path_from_idlist($x->create_id_path($idList)));
			
			$this->gfObj->debug_var_dump($idList);
			$this->gfObj->debug_var_dump($idList2);
			
			$this->assertEqual(':1::2::3:', $idList2);
			$this->assertEqual($idList2, ':'. $this->gfObj->string_from_array($idList, null, '::') .':');
			
			$this->assertEqual(':1::2::3:', $x->create_id_path($idList));
			
			$this->assertEqual(':1:', $x->create_id_path(array(1)));
			$this->assertEqual(':000010:', $x->create_id_path(array('000010')));
			
		}
		
		
		// now REALLY test paths.
		{
			$gdl = new gdlTester($this->create_dbconn());
			
			$myBasePath = '/character/sheet/Xander Cage';
			$x->set_base_path($myBasePath);
			
			//now add something that should be BENEATH that path.
			$idList = $x->create_path('attributes/str');
			
			$this->gfObj->debug_var_dump($idList);
		}
		#*/
		
		
		
		
		/*/test some basics first.
		$translations = array(
			'good'	=> array(
				'a_int'		=> array('int', 'integer', 'integraIsACarNotAnArgument'),
				'a_dec'		=> array('dec', 'decimal', 'decemberIsAMonth'),
				'a_bool'	=> array('bool', 'boolean', 'boolJustLooksLikeSomethingJiggly'),
				'a_text'	=> array('text', 'textual', 'textPaperJustLooksPink')
			),
			'bad'	=> array(
				'a_int'		=> array('num', 'a_int', 'nittedStuff'),
				'a_dec'		=> array('dce', 'a_dec', 'dceStuff'),
				'a_bool'	=> array('bolo', 'a_bool', 'boloMeansBeOnLookOut'),
				'a_text'	=> array('txt', 'a_text', 'txtIsABadAbbreviation')
			)
		);
		
		foreach($translations as $goodBad=>$matchesArray) {
			foreach($matchesArray as $matchThis => $tryThese) {
				foreach($tryThese as $k) {
					try {
						$this->assertEqual($matchThis, $x->translate_attrib_name($k));
					}
					catch(exception $e) {
						if($goodBad == 'good') {
							$this->assertFalse(true, "test (". $k .") should have been good, but was bad::: ". $e->getMessage());
						}
					}
				}
			}
		}
		$testData = array(
			'skills/Appraise/cc'		=> array('bool' => true),
			'skills/Appraise/mod'		=> array('int' => 3),
			'skills/Appraise/ab_mod'	=> array('int' => 3),
			'skills/Appraise/rank'		=> array('int' => 3),
			'skills/Appraise/misc_mod'	=> array('int' => 3),
			'skills/Appraise/notes'		=> array('text' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit."),
			'skills/Appraise/test'		=> array(
				'text'	=> "Lorem ipsum dolor sit amet, consectetur adipiscing elit.",
				'bool'	=> true,
				'dec'	=> 0.03
			),
		);
		
		
		$myBasePath = '/characters/Tetra Tealeaf/';
		$x->set_base_path($myBasePath);
		foreach($testData as $path=>$subData) {
			$this->assertEqual($myBasePath . $path, $x->clean_path($path));
			$this->assertTrue(is_numeric($x->create_object($path, $subData)));
		}
		
		//get each individual item about the skill 'Appraise' individually.
		$x->get_object_attribs('skills/Appraise/cc', 'bool');
		$x->get_object_attribs('skills/Appraise/mod', 'int');
		$x->get_object_attribs('skills/Appraise/ab_mod', 'int');
		$x->get_object_attribs('skills/Appraise/rank', 'int');
		$x->get_object_attribs('skills/Appraise/misc_mod', 'int');
		$x->get_object_attribs('skills/Appraise/notes', 'text');
		$x->get_object_attribs('skills/Appraise/test',	array('text', 'bool', 'dec'));
		$returnVal = array(
			'text'		=> "Lorem ipsum dolor sit amet, consectetur adipiscing elit.",
			'bool'		=> true,
			'dec'		=> 0.03
		);
		
		
		//another way to retrieve it, without caring about what types are returned:::
		$returnVal = $x->get_all_object_attribs('skills/Appraise', false);
		$returnVal = array(
			'cc'	=> array(
						'test'	=> null,
						'bool'	=> true,
						'int'	=> null,
						'dec'	=> null
					),
			'mod'	=> array(
						'test'	=> null,
						'bool'	=> null,
						'int'	=> 10,
						'dec'	=> null
					),
			'ab_mod'	=> array(
						'test'	=> null,
						'bool'	=> null,
						'int'	=> 3,
						'dec'	=> null
					),
			'rank'	=> array(
						'test'	=> null,
						'bool'	=> null,
						'int'	=> 6,
						'dec'	=> null
					),
			'misc_mod'	=> array(
						'test'	=> null,
						'bool'	=> null,
						'int'	=> 1,
						'dec'	=> null
					),
			'notes'	=> array(
						'test'	=> "Lorem ipsum dolor sit amet, consectetur adipiscing elit.",
						'bool'	=> null,
						'int'	=> null,
						'dec'	=> null
					),
			'test'	=> array(
						'text'	=> "Lorem ipsum dolor sit amet, consectetur adipiscing elit.",
						'bool'	=> true,
						'int'	=> null,
						'dec'	=> 0.03
					)
		);
		
		//a better way to retrieve that data:
		$returnVal = $x->get_all_object_attribs('skills/Appraise', false);
		$returnVal = array(
			'cc'		=> true,
			'mod'		=> 10,
			'ab_mod'	=> 3,
			'rank'		=> 6,
			'misc_mod'	=> 1,
			'notes'		=> "Lorem ipsum dolor sit amet, consectetur adipiscing elit.",
			'test'		=> array(
							'text'	=> "Lorem ipsum dolor sit amet, consectetur adipiscing elit.",
							'bool'	=> true,
							'dec'	=> 0.03
						)
		);
		
		//if we don't want all that junk in test, we can specify what to get for EACH one:
		$types = array(
			'cc'	=> "bool",
			'test'	=> "dec"
		);
		$returnVal = $x->get_all_object_attribs('skills/Appraise', false, $types);
		$returnVal = array(
			'cc'		=> true,
			'mod'		=> 10,
			'ab_mod'	=> 3,
			'rank'		=> 6,
			'misc_mod'	=> 1,
			'notes'		=> "Lorem ipsum dolor sit amet, consectetur adipiscing elit.",
			'test'		=> 0.03
		);
		#*/
	}//end test_genericDataLinker()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	public function test_genericPermissions() {
	}//end test_genericPermissions()
	//--------------------------------------------------------------------------
	
	
	
	//--------------------------------------------------------------------------
	private function do_tokenTest(array $tokenData, $uid, $checksum) {
		
		if($this->assertTrue(is_array($tokenData)) && $this->assertTrue(is_numeric($uid)) && $this->assertTrue(strlen($checksum))) {
			
			$this->assertTrue(is_array($tokenData));
			$this->assertTrue((count($tokenData) == 2));
			$this->assertTrue(isset($tokenData['id']));
			$this->assertTrue(isset($tokenData['hash']));
			$this->assertTrue(($tokenData['id'] > 0));
			$this->assertTrue((strlen($tokenData['hash']) == 40));
		}
		
	}//end do_tokenTest()
	//--------------------------------------------------------------------------
	
}


class authTokenTester extends cs_authToken {
	public $isTest=true;
	
	public function tokenData($id, $onlyNonExpired=true) {
		return($this->get_token_data($id, $onlyNonExpired));
	}
	public function doHash($tokenId, $uid, $checksum, $hash) {
		return($this->create_hash_string($tokenId, $uid, $checksum, $hash));
	}
}

class gdlTester extends cs_genericDataLinker {
	public $isTest = true;
	
	public function __construct($db) {
		parent::__construct($db);
	}
	
	public function createPathObjects($path) {
		return($this->create_path_objects($path));
	}
}

?>
