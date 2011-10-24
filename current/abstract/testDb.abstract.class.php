<?php


abstract class testDbAbstract extends UnitTestCase {
	
	protected $config = array();
	protected $db;
	private $templateDb;
	private $templateConfig;
	
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
			'user'		=> $superUserName,
			'password'	=> $password,
			'host'		=> $hostname,
			'port'		=> $port,
			
			//make sure the database name is unique and has (almost) no chance of clashing.
			'dbname'	=> $this->set_dbname(__CLASS__)
		);
		$this->templateConfig = $this->config;
		$this->templateConfig['dbname'] = 'template1';
		$this->templateDb = new cs_phpdb('pgsql');
		$this->templateDb->connect($this->templateConfig);

		$this->gfObj = new cs_globalFunctions;
		
		$this->db = new cs_phpdb('pgsql');
		$this->create_db();
	}//end __construct()
	//-----------------------------------------------------------------------------
	
	
	
	//-----------------------------------------------------------------------------
	private function set_dbname($prefix) {
		return(strtolower(__CLASS__ .'_'. preg_replace('/\./', '', microtime(true))));
	}//end set_dbname()
	//-----------------------------------------------------------------------------
	
	
	
	//-----------------------------------------------------------------------------
	protected function create_db() {
		$sql = "CREATE DATABASE ". $this->config['dbname'];
		$this->templateDb->exec($sql);
		
		//now run the SQL file.
		$this->db->connect($this->config);
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
