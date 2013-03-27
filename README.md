## Web Application Libraries

*For info about upgrades, check the "upgrades" folder*

(a.k.a. "CS-WebAppLibs" or "CSWAL")

__WARNING #1:__ Version 0.5.x and above utilize PDO and prepared statements. 
Applications/libraries/frameworks that were written against a prior version may 
need to be rewritten to handle the differences.  In theory, it is still fully 
backwards-compatible... but I make no guarantees.

__WARNING #2:__ If you don't read what sparse documentation there is, you 
probably won't get it.

__WARNING #3:__ This code was not written for the faint of heart. The naming 
conventions may be inconsistent. Some of these classes, such as the WebDBUpgrade 
system, is made to be transparent, so interacting with it can be difficult; 
others, such as the logging system, are meant to be used with little need to 
understand their inner-workings. 

__WARNING #4:__ Due to lack of help, the only officially-supported database is 
PostgreSQL.  Most things should be fairly well database-agnostic, though some of 
the fancier features (such as transactions within the upgrade system) may not 
work as expected: MySQL can sometimes automatically commits changes without 
warning, such as when transactions cross transactionable and transactionless 
tables.

*On to the documentation...*

This is a set of libraries for working with PHP-based web applications.  It 
builds upon the foundation of CS-Content, which can be found at 
[ http://github.com/crazedsanity/cs-content ]; it also builds upon CS-PHPXML, 
which is just an XML library, and can be found at 
[ http://github.com/crazedsanity/cs-phpxml ].

Look at the library-specific documentation:
 * [Basic Database Interaction](docs/README_phpDB.md)
 * [CS Web DB Logger](docs/README_webdblogger.md)
 * [CS Web DB Upgrade](docs/README_webdbupgrade.md)
 * [Lock File](docs/README_lockfile.md)
 * [Auth Token](docs/README_authToken.md)
 * [Session DB](docs/README_sessionDB.md)
 * [Site Configuration](docs/README_siteConfig.md)

### NOTE REGARDING OTHER CLASSES

There are other classes implemented.  As they're tested (and I have time), more 
documentation will be added here.  For more (or less) up-to-date information, 
take a look at the "Developer's Corner" on CrazedSanity.com: 
[http://www.crazedsanity.com/content/devCorner/cs_webapplibs]
