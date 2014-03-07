# CS Auth Token (cs_authToken)

This system is built to handle either authentication or authorization, or both.  A common usage for this system is for storing temporary tokens for changing passwords: a user requests that their password get changed, so they are sent an email with a link containing some weird complicated hash.  This is the system that handles the logic of creating the tokens and storing them in the database, along with handling automatic expiration.

## What Are Tokens For?

Imagine you've got a web application, and there's authentication involved. You're going to create a "simple" system wherein a user can click something and generate a "lost password" request.  Since you want to be user-friend *and* security-conscious, your system generates an email with a link for them to follow.  With CS Auth Token, you can create one that:
 
 * only works for the specified user's account
 * expires after a single (successful) use
 * expires after a given period of time
 * is cryptographically secure

## How Do I Use It?

I could explain it, but really, it's easier just to show you:

```php
$x = new cs_authToken($db, $uidOfUser);
$hash = $x->create_token($email);
```

The email might look something like:

```
Hello {friendlyName},

To reset your password, please click this link:

http://www.cs.local/lost?hash={hash}?key={email}
```

The code to handle verification looks like:

```php
$x = new cs_authToken($db);

$authData = $x->authenticate_token($hash, $key);

if($authData['result'] == true) {
	//authenticated! use 'stored_value' to help in resetting their password
	$myData = $authData['stored_value'];
}
```

## Expiration Possibilities

Currently, there are a number of ways that a token can be expired:
 * a given number of maximum uses (e.g. 1 use)
 * an specific date of expiration (e.g. 15 minutes from when it was created)
 * a limited number of uses + a specific expiration date (e.g. 1 use in the next 15 minutes)
 * no specific expiration (the token will never be automatically removed)

Some things these tokens could be used for (they're just ideas):
 * token API calls
 	* 1000 uses until token expires
 	* 30 days until token expires
 	* limit of 1000 uses during the next 30 days
 * sessions:
 	* 1000 page views before need to login again
 	* must expire before a certain date
 	* 1000 page views OR until a certain date (whichever comes first)
 * licensing (requires something to keep token from being destroyed, depending upon implementation)
 	* 5 allowed licenses for 1 year
 	* unlimited users for 1 year
 	* 5 users, no expiration
 	* unlimited users for 1 year

