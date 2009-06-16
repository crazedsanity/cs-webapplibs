
Once the appropriate schema has been built, code can be updated easily to start logging:

//Create the class...
$this->log = new cs_webdblogger($dbObj, 'Generic Activity');

//Now call the logger.
$this->log->log_by_class('User viewed page', 'info', $this->userId);

$Id$ 