# CS Web DB Upgrade

_NOTE:_ this documentation is a work in progress, so take it as more guideline than rule.  If you find something wrong with it, fix it and let me know through a pull request or however you'd like... or tell me about it.  Or deal with it... whatever you prefer.


## Preconceptions

CS Web DB Upgrade is built with a few preconceptions about your web application.

1. there is a concept of a "production" application
1. there is a test environment in which changes are tested
1. upgrades are scripted (schema, database values, filesystem things, etc)

The system can be configured to automatically upgrade every time a page is viewed,
via a custom shell script, or... however you want.  It's your application.

## What It Does

CS Web DB Upgrade is built to make upgrading a database-driven web application 
seamless. Instead of manually changing things in a certain sequence, automate 
that process by scripting it!

The Old Way:

1. Manually "mark" the site as being in maintenance (replacing the site with one that responds to all requests with "down for maintenance" or some such)
1. Manually update the code (overwrite it by extracting a zip, or using an SCM command--```svn update``` or ```git pull```)
1. Manually run schema changes
1. Manually update existing records
1. Manually fix existing configuration files
1. Manually fix existing misc files (eg. paths for images)
1. Hope things worked so far... if not, fix them, maybe do some praying and sweating
1. Manually "unmark" the site as beinig in maintenance mode (see #1)

Did you see a pattern?

Meet your savior!  This system adds the ability to allow your system to upgrade 
itself.  Whenever you increment the version in your VERSION file, it can run a 
custom script that will do everything you need AND keep others out until it is 
done.  Once the system is updated, the next thing to touch the code containing 
the upgrade system will cause it to run.

The New Way:

1. Manually (or automatically) update application
1. Run the upgrade script (generally runs automatically when the application is used)

That's it!  If the upgrade breaks for any reason, a special "lock file" will 
automatically put your application into "maintenance mode", preventing users 
from hitting the database.  And this frees you from having to do anything 
special in order to turn on that "maintenance mode."  Yay!

## How to Work With CS Web DB Upgrade

These steps help you get going with as little fuss as possible.  Once you're
familiar with the system, you'll be able to do just about anything you like.

Create a file, ```upgrades/upgrade.ini```.  The "main" section is the most 
important.  Here's a sample:

```
[main]
initial_version=0.0.1

[v0.0.1]
target_version=0.0.2
script_name=upgradeTo0.0.2.php
class_name=upgrade_to_0_0_2
call_method=run_upgrade

[v0.0.2]
target_version=0.1.0
script_name=upgradeTo0.1.0.php
class_name=upgrade_to_0_1_0
call_method=run_upgrade

[v0.3.5-ALPHA1]
target_version=0.3.5-BETA2
script_name=upgradeTo0.3.5-BETA2.php
class_name=upgrade_to_0_3_5_BETA2
call_method=run_upgrade

[v0.3.6]
target_version=1.0.0
script_name=upgradeTo1.0.0.php
class_name=upgrade_to_1_0_0
call_method=run_upgrade
```

The flow should be somewhat obvious.  Each index (excluding "main") is parsed as 
a version.  So the upgrade path is:

0.0.1 -> 0.0.2 -> 0.0.2 -> 0.1.0 -> 0.3.5-ALPHA1 -> 0.3.6 -> 1.0.0

So, if there's an existing installation that has a version in the database of 
0.2.0, all scripts from 0.3.5-ALPHA1 and beyond are run, to get it to the current 
version, which is probably at least 1.0.0 (though it could easily be 1.0.1 or 
higher; not all upgrades require scripted changes).
