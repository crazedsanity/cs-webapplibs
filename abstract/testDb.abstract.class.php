<?php

//TODO: make this work for more than just PostgreSQL.
abstract class testDbAbstract extends UnitTestCase {
	
	protected $config = array();
	protected $db;
	private $templateDb;
	
	//-----------------------------------------------------------------------------
	public function __construct($superUserName, $password, $hostname, $port) {
		/*
				'host'		=> $this->host,
				'port'		=> $this->port,
				'dbname'	=> $this->dbname,
				'user'		=> $this->user,
				'password'	=> $this->password
		*/
		$this->config = array(
			'dsn'		=> "pgsql:hostname=". $hostname .";port=". $port .";database=",
			'user'		=> $superUserName,
			'password'	=> $password,
		);
		$this->gfObj = new cs_globalFunctions;
		$this->create_db();
	}//end __construct()
	//-----------------------------------------------------------------------------
	
	
	
	//-----------------------------------------------------------------------------
	protected function create_db() {
		$myDbName = strtolower(__CLASS__ .'_'. preg_replace('/\./', '', microtime(true)));
		$this->templateDb = new cs_phpDB($this->config['dsn']. 'template1', $this->config['username'], $this->config['password']);
		
		$this->templateDb->exec("CREATE DATABASE ". $myDbName);
		$this->templateDb = null;
		
		//now run the SQL file.
		$this->db = new cs_phpdb($this->config['dsn']. $myDbName, $this->config['username'], $this->config['password']);
		$this->db->run_sql_file(dirname(__FILE__) .'/../tests/files/test_db.sql');
	}//end create_db()
	//-----------------------------------------------------------------------------
	
	
	
	//-----------------------------------------------------------------------------
	protected function destroy_db() {
		$this->db->close();
		$this->templateDb->exec("DROP DATABASE ". $this->config['dbname']);
	}//end destroy_db()
	//-----------------------------------------------------------------------------
	
	
	
	//-----------------------------------------------------------------------------
	public function __destruct() {
		#$this->destroy_db();
	}//end __destruct()
	//-----------------------------------------------------------------------------



}//end testDbAbstract{}

?>
