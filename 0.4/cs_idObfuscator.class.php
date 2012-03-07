<?php
/*
 * Created on May 16th, 2011
 * 
 * FILE INFORMATION:
 * 
 * $HeadURL$
 * $Id$
 * $LastChangedDate$
 * $LastChangedBy$
 * $LastChangedRevision$
 * 
 * Originally pulled from http://raymorgan.net/web-development/how-to-obfuscate-integer-ids/
 */

class cs_IdObfuscator {
 
	public static function encode($id) {
		if (!is_numeric($id) or $id < 1) {return FALSE;}
		$id = (int)$id;
		if ($id > pow(2,31)) {return FALSE;}
		$segment1 = self::getHash($id,16);
		$segment2 = self::getHash($segment1,8);
		$dec	  = (int)base_convert($segment2,16,10);
		$dec	  = ($dec>$id)?$dec-$id:$dec+$id;
		$segment2 = base_convert($dec,10,16);
		$segment2 = str_pad($segment2,8,'0',STR_PAD_LEFT);
		$segment3 = self::getHash($segment1.$segment2,8);
		$hex	  = $segment1.$segment2.$segment3;
		$bin	  = pack('H*',$hex);
		$oid	  = base64_encode($bin);
		$oid	  = str_replace(array('+','/','='),array('$',':',''),$oid);
		return $oid;
	}
 
	public static function decode($oid) {
		if (!preg_match('/^[A-Z0-9\:\$]{21,23}$/i',$oid)) {return 0;}
		$oid	  = str_replace(array('$',':'),array('+','/'),$oid);
		$bin	  = base64_decode($oid);
		$hex	  = unpack('H*',$bin); $hex = $hex[1];
		if (!preg_match('/^[0-9a-f]{32}$/',$hex)) {return 0;}
		$segment1 = substr($hex,0,16);
		$segment2 = substr($hex,16,8);
		$segment3 = substr($hex,24,8);
		$exp2	 = self::getHash($segment1,8);
		$exp3	 = self::getHash($segment1.$segment2,8);
		if ($segment3 != $exp3) {return 0;}
		$v1	   = (int)base_convert($segment2,16,10);
		$v2	   = (int)base_convert($exp2,16,10);
		$id	   = abs($v1-$v2);
		return $id;
	}
 
	private static function getHash($str,$len) {
		return substr(sha1($str.CRYPT_SALT),0,$len);
	}
}
?>
