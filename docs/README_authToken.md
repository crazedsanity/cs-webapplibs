# CS Auth Token (cs_authToken)

This system is built to handle either authentication or authorization, or both.  A common usage for this system is for storing temporary tokens for changing passwords: a user requests that their password get changed, so they are sent an email with a link containing some weird complicated hash.  This is the system that handles the logic of creating the tokens and storing them in the database, along with handling automatic expiration.

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

The only value currently stored or returned by cs_authToken is a UID (user ID).  So right now it's only really useful for handling lost passwords or something linking directly to a specific user account.
