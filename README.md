YosLogin
=====

Lightweight PHP library to help you handle user authentication and long-term sessions on a website.

Main functionnalities:
* password hashing
* secure PHP session for short-term
* "remember me" cookie based authentication (Need server-side implementation. A no-database example is given)

YosLogin's purpose is to handle long-term sessions in a secure, simple and smart way.
Still it is given as is, without any guarantee that it will work and be that secure.

If you find any bug or flaw, please let me know.

## Requirements

YosLogin requires **PHP 5.3** ( **PHP 5.3.7** or above recommanded ).

## How to use

The ```example/``` directory shows a complete example of use of YosLogin. 

### Basic usage

1. Include the file ```yoslogin.lib.php``` in your script. No other file needed

  ```php
  require_once(yoslogin.lib.php);
  ```
2. Create your own class inheriting YosLogin and implement the abastract method ```getUser()```:
  ```php
  class ExampleLogin extends YosLogin {
  
      //retrieve user information from your database (or any source of your choice)
      protected function getUser($login) {} //must return an array with at least those items: array('login' => '', 'password' => '')
  }
  ```
3. Call your newly created class from your code and use its public methods to authenticate your users:
  ```php
  $logger = new ExampleLogin('customSessionName');
  
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
      //use user specific information retrieved from your implementation of getUser
      echo $user['mySpecificData'];

      //check if user recently entered his/her password, and didn't change IP since
      //use it for important actions such as when the user wants to change his/her email or password
      if($user['secure'] === true) {

      }
  } else {
      //show the login form if you are on a user restricted area
  }
  ```
4. That's all, folks!

### Advance usage: long-term sessions

If you want to handle long-term sessions via a "remember me checkbox" or any other method, you will need to :

1. In addition to extending the class ```YosLogin```, implement the interface ```YosLTSession``` and its methods:
  ```php
  class ExampleLogin extends YosLogin implements YosLTSession {
  
      //retrieve user information from your database (or any source of your choice)
      protected function getUser($login) {} //must return an array of the form: array('login' => '', 'password' => '')
  
      //methods to handle save and retrieve long-term (LT) sessions on your server
      protected function setLTSession($login, $sid, $value) {}  //save the LT session on server-side
      protected function getLTSession($cookieValue) {}          //return LT session data as an array
      protected function unsetLTSession($cookieValue) {}        //unset a specific LT session
      protected function unsetLTSessions($login) {}             //unset all server-side LT session for this user
      protected function flushOldLTSessions() {}                //unset all old server-side LT session
  }
  ```
2. When logging the user in, add the "remember me" checkbox status as a third parameter:

  ```php
  //when user just filled the login form
  $user = $logger->logIn($_POST['login'], $_POST['password'], isset($_POST['remember']));
  ```
3. Everything else remains the same
4. Profit!

## Dependancies

YosLogin depends on the [Secure-random-bytes-in-PHP by GeorgeArgyros](https://github.com/GeorgeArgyros/Secure-random-bytes-in-PHP/), which is released under the New BSD License. The function was included in ```yoslogin.lib.php``` to keep this library in a single file.

## Licence

YosLogin is a work by Yosko, all wright reserved.

It is licensed under the [GNU LGPL](http://www.gnu.org/licenses/lgpl.html) license.

It was build on the work of other people:
* GeorgeArgyros Secure-random-bytes-in-PHP, mentioned above
* Marginally, some lines of code may be inspired by [shaarli](https://github.com/sebsauvage/Shaarli)'s authentication system (by sebsauvage), especially the configuration of the PHP session.