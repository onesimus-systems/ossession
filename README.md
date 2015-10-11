OSSession
--------

OSSession provides a centralized, database backed PHP session manager. It requires minimal setup and provides a reliable easy way to manage PHP sessions.

Requirements
------------

- PHP >= 5.4.0

Usage
-----

```php
use \Onesimus\Session\SessionManager

// First we need to register the session handler
$pdo = new PDO(...);
$options = [
	'timeout' => 6 // hours
	'gclotto' => [1, 100] // Chances a garbage collection will occur
	'table' => 'sessions' // Database table that houses the session data. It must have three fields called 'id', 'data', and 'last_accessed'. 'last_accessed' is an int as times are stored in Unix time.
];

SessionManager::register($pdo, $options);
SessionManager::startSession('php-session-name');

// Manipulate session data
SessionManager::set('userid', 2);
SessionManager::get('themename', 'default'); // get will either return the session value if it exists or whatever is passed as the second argument. By default it will return null if the session data doesn't exist.

SessionManager::clear(); // Clear a session
```

License
-------

OSSession is released under the BSD 3-clause license. The license text can be found in LICENSE.md.
