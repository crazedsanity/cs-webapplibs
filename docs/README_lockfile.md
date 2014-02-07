# Lock File (cs_lockfile)

This class is intended to avoid having multiple instances of a certain process (like an upgrade, such as with cs_webdbupgrade) from "tripping" over each other.  Create a lock file somewhere on the system (which is readable + writable), and remove it when the operation completes.  The file should stay if there's a problem that keeps the operation from completing (because trying again would probably fail, or would make things worse).

```php
$lock = new cs_lockfile('/path/to/rw/dir', 'file.lock');

if(!$lock->is_lockfile_present()) {
	$lock->create_lockfile($upgradeWording);
	
	// ... do some stuff...
	// Only delete the lockfile if it all succeeded
	$lock->delete_lockfile();
}
else {
	throw new exception($lock->read_lockfile());
}

```

You may want to look at [cs_webdbupgrade](README_webdbupgrade.md) for an implementation example.
