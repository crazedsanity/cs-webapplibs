<?php
/*
 *  SVN INFORMATION:::
 * -------------------
 * Last Author::::::::: $Author$ 
 * Current Revision:::: $Revision$ 
 * Repository Location: $HeadURL$ 
 * Last Updated:::::::: $Date$
 */

class cs_dbTableHandler extends cs_singleTableHandlerAbstract {
	
	
	//-------------------------------------------------------------------------
	/**
	 * Generic way of using a class to define how to update a single database table.
	 * 
	 * @param $dbObj			(object) Connected instance of cs_phpDB{}.
	 * @param $tableName		(str) Name of table inserting/updating.
	 * @param $seqName			(str) Name of sequence, used with PostgreSQL for retrieving the last inserted ID.
	 * @param $pkeyField		(str) Name of the primary key field, for performing updates & retrieving specific records.
	 * @param $cleanStringArr	(array) Array of {fieldName}=>{dataType} for allowing updates & creating records.
	 */
    public function __construct(cs_phpDB $dbObj, $tableName, $seqName, $pkeyField, array $cleanStringArr) {
		try {
			parent::__construct($dbObj, $tableName, $seqName, $pkeyField, $cleanStringArr);
		}
		catch(Exception $e) {
			throw new exception(__METHOD__ .": unable to start, DETAILS::: ". $e->getMessage());
		}
    }//end __construct()
	//-------------------------------------------------------------------------

}

