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


require_once(dirname(__FILE__) .'/tests/testOfCSGenericChat.php');
require_once(dirname(__FILE__) .'/tests/testOfCSGenericPermissions.php');
require_once(dirname(__FILE__) .'/tests/testOfCSPHPDB.php');
require_once(dirname(__FILE__) .'/tests/testOfCSWebAppLibs.php');
/*
tests/testOfCSGenericChat.php:class testOfCSGenericChat extends testDbAbstract {
tests/testOfCSGenericPermissions.php:class testOfCSGenericPermissions extends testDbAbstract {
tests/testOfCSGenericPermissions.php:class _gpTester extends cs_genericPermission {
tests/testOfCSPHPDB.php:class TestOfCSPHPDB extends UnitTestCase {
tests/testOfCSWebAppLibs.php:class testOfCSWebAppLibs extends testDbAbstract {
tests/testOfCSWebAppLibs.php:class authTokenTester extends cs_authToken {
*/

$test = new TestSuite('Tests for CS-WebAppLibs');
$test->addTestCase(new TestOfCSPHPDB());
$test->addTestCase(new testOfCSWebAppLibs());
$test->addTestCase(new testOfCSGenericChat());
$test->addTestCase(new testOfCSGenericPermissions());
?>
