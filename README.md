YosLogin
=====

Lightweight PHP library to help you handle user authentication and long-term sessions on a website.

Main functionnalities:

* password hashing
* secure PHP session for short-term
* "remember me" cookie based authentication with your own server-side implementation (A no-database, flat files example is given)

YosLogin's purpose is to handle long-term sessions in a secure, simple and smart way.
Still it is given as is, without any guarantee that it will work and be that secure.

If you find any bug or flaw, please let me know.

## Requirements

YosLogin requires **PHP 7.4**.

## How to use

The ```example/``` directory shows a complete example of use of YosLogin. 

### Basic usage

1. Include the file ```yoslogin.lib.php``` in your script. No other file needed.

  ```php
  require_once('yoslogin.lib.php');
  ```
2. YosLogin will need to call back one of your function or method to get a user's data (at least login and password hash):

  ```php
  //retrieve user information from your database (or any source of your choice)
  function myOwnGetUserFunction($login) {
      //must return an array with at least those items: array('login' => '', 'password' => '')
  }
  ```
3. Declare your YosLogin object (including the callback to your user retrieving method). See *Callback principle* below for more information on callback syntax.
  ```php
  $logger = new \Yosko\YosLogin(
      //required: the name to give to the session on your users computers
      'exampleSessionName',

      //required: callback function/method to let YosLogin retrieve a user's login & password hash
      'myOwnGetUserFunction',

      //optional: path to a log file where YosLogin should trace authentication actions
      'yoslogin.log'
  );
  ```
4. Use the following public methods to authenticate your users:
  ```php
  //when user asks to log out from your website, destroy his/her sessions
  $logger->logOut();
  
  //when user just filled the login form
  $user = $logger->logIn($_POST['login'], $_POST['password']);
  
  //if no specific action was done, check if user is logged in
  $user = $logger->authUser();
  
  //when user reenter his password to do important actions such as changing email/password
  $user = $logger->authUser($_POST['password']);
  
  //check if authenticated user or anonymous
  if($user['isLoggedIn'] === true) {
      //check if user recently entered his/her password, and didn't change IP since
      //use it for important actions such as when the user wants to change his/her email or password
      if($user['secure'] === true) {

      }
  } else {
      //show the login form if you are on a user restricted area
  }
  ```
5. That's all, folks!

### Advanced usage: redirection page and local IPs

Right after the call to ```$logger = new \Yosko\YosLogin(...)```, and before anything else, you can define those options:

```php
//optional: redirection page after a successful login/logout/secure/unsecure
//if not used or empty value given (''), redirection to the same URL without GET parameters
//if false, no redirection is done at all
$logger->setRedirectionPage('');

//optional: whether to allow local IPs or not (default if not called: false. Setting it to true can be less secure)
$logger->setAllowLocalIp(true);
```

### Advanced usage: long-term sessions

If you want to handle long-term sessions via a "remember me checkbox" or any other method, you will need to:

1. Define the 5 functions/methods and 1 value needed by YosLogin to handle long-term sessions on a server (here given as a static class):

  ```php
    class MyLongTermSessionManager {
		    //duration of a session (in seconds)
        public static $LTDuration = 2592000;

        //store these information on your server
        public static function setLTSession($login, $sid, $value) {}
        
        //retrieve the $value information based on the two other information
        //or return false if session doesn't exist
        public static function getLTSession($login, $sid) {}
        
        //unset a specific long-term session based on login & sid
        public static function unsetLTSession($login, $sid) {}
        
        //unset all long-term session for this user
        public static function unsetLTSessions($login) {}
        
        //delete sessions older than $LTDuration
        //feel free to also define a maximum number of session to handle at the same time
        public static function flushOldLTSessions() {}
    }
  ```
2. After calling ```$logger = new \Yosko\YosLogin(...);```, use its ```ltSessionConfig()``` method to tell YosLogin which functions/methods and value must be used for long-term session handling:

  ```php
  $logger->ltSessionConfig(
      //callbacks
      array(
          //callback for storing a session
          'setLTSession' => array('MyLongTermSessionManager', 'setLTSession'),

          //callback for retrieving a session
          'getLTSession' => array('MyLongTermSessionManager', 'getLTSession'),

          //callback for deleting a session
          'unsetLTSession' => array('MyLongTermSessionManager', 'unsetLTSession'),

          //callback for deleting all sessions of a user
          'unsetLTSessions' => array('MyLongTermSessionManager', 'unsetLTSessions'),

          //callback for flushing old sessions
          'flushOldLTSessions' => array('MyLongTermSessionManager', 'flushOldLTSessions')
      ),

      //duration allowed for a long-term session
      MyLongTermSessionManager::$LTDuration
  );
  ```
2. When logging the user in, add the "remember me" checkbox status as a third parameter:

  ```php
  //when user just filled the login form
  $user = $logger->logIn($_POST['login'], $_POST['password'], isset($_POST['remember']));
  ```
3. Everything else remains the same
4. Profit!

### Callback principle

We mentioned above the use of callbacks for retrieving a user or handling long-term session. The principle is:

1. You define a function or method (can be static or not)
2. You give its name to YosLogin
3. YosLogin will call it if and when needed

YosLogin uses callbacks as defined in PHP. When asked to give a callback, you are free to give:

```php
// a function name (string)
// this is the method used for the "getUser" example above
$callback = 'myFunction';

// a class name and a function name (for static call)
// this is the method used in the long-term example above
$callback = array('myClass', 'myMethod');

// an object instanciated from a class, and the method name (for non-static call)
$callback = array($myObject, 'myMethod');
```

## Use of ```$_SESSION```

There are 4 variables kept inside of the global ```$_SESSION```. Make sure your project doesn't override them:

1. ```$_SESSION['ip']```: the IP of the current user (when IP changes, ```$_SESSION['secure']``` is set to ```false```)
2. ```$_SESSION['login']```: the current user's name
3. ```$_SESSION['secure']```: whether the current state of the connection is considered secure or not
4. ```$_SESSION['sid']```: unique ID given to the current session (especially useful for long-term session)

## Dependancies

YosLogin depends on the [Secure-random-bytes-in-PHP by GeorgeArgyros](https://github.com/GeorgeArgyros/Secure-random-bytes-in-PHP/), which is released under the New BSD License. The function was included in ```yoslogin.lib.php``` to keep this library in a single file.

## Licence

YosLogin is a work by Yosko, all wright reserved.

It is licensed under the [GNU LGPL](http://www.gnu.org/licenses/lgpl.html) license.

It was build on the work of other people:
* GeorgeArgyros Secure-random-bytes-in-PHP, mentioned above
* Marginally, some lines of code may be inspired by [shaarli](https://github.com/sebsauvage/Shaarli)'s authentication system (by sebsauvage), especially the configuration of the PHP session.