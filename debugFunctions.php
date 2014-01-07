<?php


function cs_debug_backtrace($printItForMe=NULL,$removeHR=NULL) {
	$gf = new cs_globalFunctions;
	if(is_null($printItForMe)) {
		if(defined('DEBUGPRINTOPT')) {
			$printItForMe = constant('DEBUGPRINTOPT');
		}
		elseif(isset($GLOBALS['DEBUGPRINTOPT'])) {
			$printItForMe = $GLOBALS['DEBUGPRINTOPT'];
		}
	}
	if(is_null($removeHR)) {
		if(defined('DEBUGREMOVEHR')) {
			$removeHR = constant('DEBUGREMOVEHR');
		}
		elseif(isset($GLOBALS['DEBUGREMOVEHR'])) {
			$removeHR = $GLOBALS['DEBUGREMOVEHR'];
		}
	}
//	if(function_exists("debug_print_backtrace")) {
//		//it's PHP5.  use output buffering to capture the data.
//		ob_start();
//		debug_print_backtrace();
//		
//		$myData = ob_get_contents();
//		ob_end_clean();
//	}
//	else {
		//create our own backtrace data.
		$stuff = debug_backtrace();
		if(is_array($stuff)) {
			$i=0;
			foreach($stuff as $num=>$arr) {
				if($arr['function'] !== "debug_print_backtrace") {
					
					$fromClass = '';
					if(isset($arr['class']) && strlen($arr['class'])) {
						$fromClass = $arr['class'] .'::';
					}
					
					$args = '';
					foreach($arr['args'] as $argData) {
						$args = $gf->create_list($args, $gf->truncate_string($gf->debug_print($argData, 0, 1, false), 600), ', ');
					}
					
					$fileDebug = "";
					if(isset($arr['file'])) {
						$fileDebug = " from file <u>". $arr['file'] ."</u>, line #". $arr['line'];
					}
					$tempArr[$num] = $fromClass . $arr['function'] .'('. $args .')'. $fileDebug;
					
				}
			}
			
			array_reverse($tempArr);
			$myData = null;
			foreach($tempArr as $num=>$func) {
				$myData = $gf->create_list($myData, "#". $i ." ". $func, "\n");
				$i++;
			}
		}
		else {
			//nothing available...
			$myData = $stuff;
		}
//	}
	
	$backTraceData = $gf->debug_print($myData, $printItForMe, $removeHR);
	return($backTraceData);
}//end cs_debug_backtrace()

function cs_get_where_called() {
	$stuff = debug_backtrace();
//	foreach($stuff as $num=>$arr) {
//		if($arr['function'] != __FUNCTION__ && $arr['function'] != 'debug_backtrace' && (!is_null($fromMethod) && $arr['function'] != $fromMethod)) {
//			#$retval = $arr['function'];
//			$fromClass = $arr['class'];
//			if(!$fromClass) {
//				$fromClass = '**GLOBAL**';
//			}
//			$retval = $arr['function'] .'{'. $fromClass .'}';
//			break;
//		} 
//	}
	$myData = $stuff[2];
	$fromClass = $myData['class'];
	if(!$fromClass) {
		$fromClass = '**GLOBAL**';
	}
	$retval = $fromClass .'::'. $myData['function'];
	return($retval);
}


?>
