<?php
/*
 * Created on February 25, 2011
 * 
 * FILE INFORMATION:
 * 
 * $HeadURL$
 * $Id$
 * $LastChangedDate$
 * $LastChangedBy$
 * $LastChangedRevision$
 */

abstract class cs_genericChatRoomAbstract extends cs_chatCategoryAbstract {
	
	/** Database object. */
	public $db;
	
	/** cs_globalFunctions object, for cleaning strings & such. */
	public $gfObj;
	
	/** Table name used to store list of chat rooms. */
	protected $myTable = "cswal_chat_room_table";
	
	/** Sequence for chat room table. */
	protected $mySeq = "cswal_chat_room_table_change_room_id_seq";
	
	/** Table handler object for simple SQL handling */
	private $dbTableHandler;
	
	//============================================================================
	public function __construct(cs_phpDB $db) {
		$this->db = $db;
		$this->gfObj = new cs_globalFunctions;
		
		//setup table handler.
		$cleanString = array(
			'category_id'		=> "int",
			'room_name'			=> "text",
			'room_description'	=> "text",
			'is_private'		=> "bool",
			'is_closed'			=> "bool",
			//'encoding'		=> "text"
		);
		$this->dbTableHandler = new cs_dbTableHandler($this->db, $this->myTable, $this->mySeq, 'chat_room_id', $cleanString);
	}//end __construct()
	//============================================================================
	
	
	
	//============================================================================
	public function create_room($roomName, $roomDescription=null, $isPrivate=false) {
		if(is_string($roomName) && strlen($room)) {
			try {
				if(!is_bool($isPrivate)) {
					$isPrivate=false;
				}
				$insertArr = array(
					'room_name'			=> $roomName,
					'room_description'	=> $roomDescription,
					'is_private'		=> $isPrivate
				);
				if(is_numeric($this->categoryId)) {
					$insertArr['category_id'] = $this->categoryId;
				}
				$roomId = $this->dbTableHandler->create_record($insertArr);
			}
			catch(Exception $e) {
				throw new exception(__METHOD__ .": failed to create record, DETAILS::: ". $e->getMessage());
			}
		}
		else {
			throw new exception(__METHOD__ .": invalid room name (". $roomName .")");
		}
		
		return($roomId);
	}//end create_room()
	//============================================================================
	
	
	
	//============================================================================
	public function update_room($roomId, array $updates) {
		try {
			$retval = $this->dbTableHandler->update_record($roomId, $updates);
		}
		catch(Exception $e) {
			throw new exception(__METHOD__ .": failed to update room, DETAILS::: ". $e->getMessage());
		}
		return($retval);
	}//end update_room();
	//============================================================================
	
	
	
	//============================================================================
	public function close_room($roomId) {
		if(is_numeric($roomId)) {
			$retval = $this->update_room($roomId, array('is_closed'=>true));
		}
		else {
			throw new exception(__METHOD__ .": roomId (". $roomId .")");
		}
		return($retval);
	}//end close_room()
	//============================================================================
	
	
}

