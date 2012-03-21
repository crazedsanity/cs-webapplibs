<?php

class upgrade_to_1_2_0_ALPHA4 extends dbAbstract {
	
	private $logsObj;
	
	//=========================================================================
	public function __construct(cs_phpDB &$db) {
		if(!$db->is_connected()) {
			throw new exception(__METHOD__ .": database is not connected");
		}
		$this->db = $db;
		
		$this->logsObj = new logsClass($this->db, 'Upgrade');
		
		$this->gfObj = new cs_globalFunctions;
		$this->gfObj->debugPrintOpt = 1;
	}//end __construct()
	//=========================================================================
	
	
	
	//=========================================================================
	public function run_upgrade() {
		
		$this->db->beginTrans(__METHOD__);
		
		
		$this->run_schema_changes();
		$this->update_tag_icons();
		$this->update_config_file();
		
		
		$this->db->commitTrans(__METHOD__);
		
		return('Upgrade complete');
	}//end run_upgrade()
	//=========================================================================
	
	
	
	//=========================================================================
	private function run_schema_changes() {
		
		$this->gfObj->debug_print(__METHOD__ .": running SQL file...");
		$this->run_sql_file(dirname(__FILE__) .'/../docs/sql/upgrades/upgradeTo1.2.0-ALPHA2.sql');
		
		$details = "Executed SQL file, '". $this->lastSQLFile ."'.  Encoded contents::: ". 
			base64_encode($this->fsObj->read($this->lastSQLFile));
		$this->logsObj->log_by_class($details, 'system');
		
		
		
		$this->gfObj->debug_print(__METHOD__ .": running SQL file...");
		$this->run_sql_file(dirname(__FILE__) .'/../docs/sql/upgrades/upgradeTo1.2.0-ALPHA4.sql');
		
		$details = "Executed SQL file, '". $this->lastSQLFile ."'.  Encoded contents::: ". 
			base64_encode($this->fsObj->read($this->lastSQLFile));
		$this->logsObj->log_by_class($details, 'system');
		
	}//end run_schema_changes()
	//=========================================================================
	
	
	
	//=========================================================================
	private function update_tag_icons() {
		
		$sql = "SELECT tag_name_id, name, icon_name FROM tag_name_table ORDER BY tag_name_id";
		if($this->run_sql($sql) && $this->lastNumrows > 1) {
			$allTags = $this->db->farray_fieldnames('name', 'tag_name_id');
			
			$iconMods = array(
				'critical'			=> 'red_x',
				'bug'				=> 'bug',
				'feature request'	=> 'feature_request',
				'committed'			=> 'check_red',
				'verified'			=> 'check_yellow',
				'released'			=> 'check_green'
			);
			
			$updates = 0;
			foreach($iconMods as $name=>$icon) {
				if(isset($allTags[$name])) {
					//update.
					$sql = "UPDATE tag_name_table SET icon_name='". $icon ."' WHERE tag_name_id=". $allTags[$name]['tag_name_id'];
				}
				else {
					//insert.
					$sql = "INSERT INTO tag_name_table (name, icon_name) VALUES ('". $name ."', '". $icon ."');";
				}
				$this->run_sql($sql);
				$updates += $this->lastNumrows;
			}
		}
		else {
			throw new exception(__METHOD__ .": failed to retrieve tag names");
		}
		
	}//end update_tag_modifiers()
	//=========================================================================
	
	
	
	//=========================================================================
	public function update_config_file() {
		$fs = new cs_fileSystemClass(dirname(__FILE__) .'/../');
		$sampleXmlObj = new XMLParser($fs->read('docs/samples/sample_config.xml'));
		$siteXmlObj = new XMLParser($fs->read(CONFIG_FILE_LOCATION));
		
		$updateXml = new xmlCreator();
		$updateXml->load_xmlparser_data($siteXmlObj);
		
		
		//BACKUP ORIGINAL XML CONFIG...
		$backupFile = 'lib/__BACKUP__'. time() .'__'. CONFIG_FILENAME;
		$fs->create_file($backupFile);
		$fs->openFile($backupFile);
		$fs->write($updateXml->create_xml_string());
		
		$sampleIndexes = $sampleXmlObj->get_tree(TRUE);
		$sampleIndexes = $sampleIndexes['CONFIG'];
		
		$siteConfigIndexes = $siteXmlObj->get_tree(TRUE);
		$siteConfigIndexes = $siteConfigIndexes['CONFIG'];
		
		foreach($sampleIndexes as $indexName=>$indexValue) {
			$path = '/CONFIG/'. $indexName;
			$attributes = $sampleXmlObj->get_attribute($path);
			#debug_print(__METHOD__ .": attributes from sample (/CONFIG/". $indexName ."::: ",1);
			#debug_print($attributes,1);
			debug_print(__METHOD__ .': indexName=('. $indexName .'), indexValue=('. $indexValue .'), original config value=('. $siteConfigIndexes[$indexName] .')');
			
			//add tag if it's not there, update values otherwise.
			$tagValue = $attributes['DEFAULT'];
			if(isset($siteConfigIndexes[$indexName])) {
				$tagValue = $siteConfigIndexes[$indexName];
			}
			elseif($indexName == 'PHPMAILER_HOST' && isset($siteConfigIndexes['CONFIG_EMAIL_SERVER_IP'])) {
				$tagValue = $siteConfigIndexes['CONFIG_EMAIL_SERVER_IP'];
				$updateXml->remove_path('/CONFIG/CONFIG_EMAIL_SERVER_IP');
			}
			$updateXml->add_tag($path, $tagValue, $attributes);
		}
		
		$this->gfObj->debug_print($this->gfObj->cleanString($updateXml->create_xml_string(), 'htmlentity_plus_brackets'));
		$fs->openFile(CONFIG_FILE_LOCATION);
		$fs->write($updateXml->create_xml_string());
	}//end update_config_file()
	//=========================================================================
}

?>
