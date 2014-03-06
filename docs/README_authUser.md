# CS Auth User (cs_authUser)

This class authenticates users against a stored username/password in the database.  It depends upon the schema laid out in [the setup folder](../setup/schema.pgsql.sql).

At present, it works with the Session DB system to handle authentication and storing information in the session.

Password hashing/verification is done using PHP's built-in ```password_hash()``` and ```password_verify()```.  Support for PHP 5.3 and 5.4 is achieved by using the compatibility library "[Password_Compat](https://packagist.org/packages/ircmaxell/password-compat)", which is available using Composer (via Packagist).