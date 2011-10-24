<?php
/*
 * Created on March 8, 2011
 * 
 * FILE INFORMATION:
 * 
 * $HeadURL$
 * $Id$
 * $LastChangedDate$
 * $LastChangedBy$
 * $LastChangedRevision$
 */

abstract class cs_genericChatMessageAbstract extends cs_genericChatRoomAbstract {
	
	/** Database object. */
	public $db;
	
	/** cs_globalFunctions object, for cleaning strings & such. */
	public $gfObj;
	
	/** Table name used to store list of chat messages. */
	protected $myTable = "cswal_chat_message_table";
	
	/** Sequence for chat message table. */
	protected $mySeq = "cswal_chat_message_table_change_message_id_seq";
	
	/** Table handler object for simple SQL handling */
	private $dbTableHandler;
	
	/**   */
	private $categoryId=null;
	
	/**  */
	protected $uid;
	
	/**  */
	protected $chatRoomId;
	
	//============================================================================
	public function __construct(cs_phpDB $db, $uid, $chatRoomId) {
		$this->db = $db;
		$this->gfObj = new cs_globalFunctions;
		
		//setup table handler.
		$cleanString = array(
			'uid'					=> 'int',
			'chat_room_id'			=> 'int',
			'private_message_uid'	=> 'int',
			'message'				=> 'int'
		);
		
		if(is_numeric($uid)) {
			$this->uid = $uid;
		}
		else {
			throw new exception(__METHOD__ .": invalid UID (". $uid .")");
		}
		if(is_numeric($chatRoomId)) {
			$this->chatRoomId = $chatRoomId;
		}
		else {
			throw new exception(__METHOD__ .": invalid room ID (". $chatRoomId .")");
		}
		$this->dbTableHandler = new cs_dbTableHandler($this->db, $this->myTable, $this->mySeq, 'chat_message_id', $cleanString);
	}//end __construct()
	//============================================================================
	
	
	
	//============================================================================
	public function create_message($messageText, $privateMessageUid=NULL) {
		if(is_string($messageName) && strlen($message)) {
			try {
				$sqlArr = array(
					'uid'			=> $this->uid,
					'chat_room_id'	=> $this->chatRoomId,
					
					//TODO: should messageText be encoded?
					'message'		=> $messageText
				);
				if(!is_null($privateMessageUid) && is_numeric($privateMessageUid)) {
					$sqlArr['private_message_uid'] = $privateMessageUid;
				}
				$messageId = $this->dbTableHandler->create_record($sqlArr);
			}
			catch(Exception $e) {
				throw new exception(__METHOD__ .": failed to create record, DETAILS::: ". $e->getMessage());
			}
		}
		else {
			throw new exception(__METHOD__ .":  (". $messageName .")");
		}
		
		return($messageId);
	}//end create()
	//============================================================================
	
	
	
	//============================================================================
	public function get_messages($lastMessageId=NULL, $limit=NULL) {
		$messages = array();
		try {
			//get_records(array $filter=null, $orderBy=null, $limit=null, $offset=null)
			$filterArr = array();
			if(!is_null($lastMessageId) && $lastMessageId > 0) {
				$filterArr['message_id'] => '> '. $lastMessageId;
			}
			$messages = $this->dbTableHandler->get_records_using_custom_filter($filter, NULL, $limit);
			if(!is_array($messages) && $messages === false) {
				$messages = array();
			}
		}
		catch(Exception $e) {
			throw new exception(__METHOD__ .": error while retrieving messages, DETAILS::: ". $e->getMessages());
		}
		return($messages);
	}//end get_messages()
	//============================================================================
	
}

