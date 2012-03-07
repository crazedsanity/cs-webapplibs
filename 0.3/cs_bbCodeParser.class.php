<?php
/**
 * Created on 2007-09-26
 * 
 *  
 * SVN INFORMATION:::
 * ------------------
 * SVN Signature::::::: $Id$
 * Last Author::::::::: $Author$ 
 * Current Revision:::: $Revision$ 
 * Repository Location: $HeadURL$ 
 * Last Updated:::::::: $Date$
 * 
 * 
 * Originally from a snippet (just the function) on PHPFreaks.com: http://www.phpfreaks.com/quickcode/BBCode/712.php
 * The original code had parse errors, so it had to be fixed... While it was posted as just a basic function, 
 * the code within (such as the reference to "$this->bbCodeData" indicated it was from a class... so it has 
 * been converted.
 */


class cs_bbCodeParser extends cs_webapplibsAbstract {
	
	/** Array containing all the codes & how to parse them. */
	private $bbCodeData = NULL;
	
	//=========================================================================
	/**
	 * Setup internal structures.
	 */
	function __construct() {
		parent::__construct(false);
		# Which BBCode is accepted here
		$this->bbCodeData = array(
			'bold' => array(
				'start'	=> array('[b]', '\[b\](.*)', '<b>\\1'),
				'end'	=> array('[/b]', '\[\/b\]', '</b>'),
			),
			
			'underline' => array(
				'start'	=> array('[u]', '\[u\](.*)', '<u>\\1'),
				'end'	=> array('[/u]', '\[\/u\]', '</u>'),
			),
			
			'italic' => array(
				'start'	=> array('[i]', '\[i\](.*)', '<i>\\1'),
				'end'	=> array('[/i]', '\[\/i\]', '</i>'),
			),
			
			'image' => array(
				'start'	=> array('[img]', '\[img\](http:\/\/|https:\/\/|ftp:\/\/|\/)(.*)(.jpg|.jpeg|.bmp|.gif|.png)', '<img src=\'\\1\\2\\3\' />'),
				'end'	=> array('[/img]', '\[\/img\]', ''), 
			),
			
			#  [url]http://x.com[/url]
			'url1' => array(
				'start'	=> array('[url]', '\[url\](http:\/\/|https:\/\/|ftp:\/\/)(.*)', '<a target="_blank" href=\'\\1\\2\'>\\1\\2'),
				'end'	=> array('[/url]', '\[\/url\]', '</a>'),
			),
			
			# [url=http://x.com]stuff[/url]
			'url2' => array(
				'start'	=> array('[url]', '\[url=(http:\/\/|https:\/\/|ftp:\/\/)(.*)\](.*)', '<a target="_blank" href=\'\\1\\2\'>\\3'), 
				'end'	=> array('[/url]', '\[\/url\]', '</a>'),
			),
			
			'code' => array(
				'start'	=> array('[code]', '\[code\](.*)', '<br /><br /><b>CODE</b>:<div class="code">\\1'),
				'end'	=> array('[/code]', '\[\/code\]', '</div><br />'),
			),
		);
	}//end __construct()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Ensure the object is initialized properly, throw exception if not.
	 */
	private function isInitialized() {
		if(!is_array($this->bbCodeData) || !count($this->bbCodeData)) {
			throw new exception(__METHOD__ .": BBCode array not initialized");
		}
	}//end isInitialized()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Parse BBCode from the given string & return it with formatting.
	 */
	function parseString($data, $newlines2BR=FALSE) {
		if(is_string($data) && strlen($data) > 10) {
			$this->isInitialized();
			$data = str_replace("\n", '||newline||', $data); 
			
			foreach( $this->bbCodeData as $k => $v ) {
				if(isset($this->bbCodeData[$k]['special'])) {
					$myMatches = array();
					$regex = '/'. $this->bbCodeData[$k]['start'][1] . $this->bbCodeData[$k]['end'][1] .'/';
					$x = preg_match_all($regex .'U', $data, $myMatches);
					
					if(count($myMatches[1])) {
						$funcName = $v['special'];
						$myArgs = $myMatches[1];
						$myArgs = array_unique($myArgs);
						
						foreach($myArgs as $index=>$value) {
							$showThis = $this->$funcName($value);
							$replaceThis = str_replace(array('[', ']'), array('\\[', '\\]'), $myMatches[0][$index]);
							$data = preg_replace('/'. $replaceThis .'/U', $showThis, $data);
						}
					}
				}
				else {
					$data = preg_replace("/".$this->bbCodeData[$k]['start'][1].$this->bbCodeData[$k]['end'][1]."/U", $this->bbCodeData[$k]['start'][2].$this->bbCodeData[$k]['end'][2], $data);
				}
			}
			
			$replaceNewlineStr = "\n";
			if($newlines2BR) {
				$replaceNewlineStr = "<br />\n";
			}
			$data = str_replace('||newline||', $replaceNewlineStr, $data); 
			
		}
		return $data;
	}//end parseString()
	//=========================================================================
	
	
	
	//=========================================================================
	/**
	 * Enables extending classes to register a bbCode with special parsing.
	 * 
	 * NOTE: right now, this will only handle syntax like "[{bbCodeString}={arg}]".
	 */
	protected function register_code_with_callback($bbCodeString, $method) {
		
		if(method_exists($this, $method)) {
			$this->bbCodeData[$bbCodeString] = array(
				'special'	=> $method,
				'start'		=> array(
					'['. $bbCodeString .']',
					'\['. $bbCodeString .'=(.*)'
				),
				'end'		=> array(
					'',
					'\]'
				)
			);
		}
		else {
			throw new exception(__METHOD__ .": method (". $method .") doesn't exist");
		}
		
	}//end register_code_with_callback()
	//=========================================================================
	
}
?>
