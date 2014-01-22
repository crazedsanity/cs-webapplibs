<?php


class upgrade_001 {
	
	public function __construct() {
		$this->fsObj = new cs_fileSystem(dirname(__FILE__) .'/rw');
	}
	
	
	public function do_upgrade() {
		$this->fsObj->create_file(__CLASS__ .'.'. __FUNCTION__ .".test");
		return(true);
	}
}
?>
