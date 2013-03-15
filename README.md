YosLogin
=====

PHP classes to help you handle user authentication and long-term sessions on a website.

Main functionnalities:
* password hashing
* secure PHP session for short-term
* "remember me" cookie based authentication (need server-side implementation)

YosLogin's objective is to handle long-term sessions in a secure and smart way.
Still it is given as is, without any guarantee that it will work and be that secure.

If you find any bug or flaw, please let me know.


## Requirements

YosLogin requires **PHP 5.3** ( **PHP 5.3.7** or above recommanded ). It might work on older versions if Blowfish is installed on the system and can be used via crypt().


## How to use it

The ```example.php``` file shows how to use YosLogin. Here is a step-by-step explanation of how it is build:

1. Add the files (```yoslogin.class.php```, ```yoslogintools.class.php``` and ```srand.php```) to your project in any folder
2. include ```yoslogin.class.php``` in your script. The other ones will be automatically included too:
  ```php
  
  require_once(yoslogin.class.php);
  ```
3. Create your own class inheriting YosLogin and implement all abastract methods:
  ```php
  class MyLogin extends YosLogin {
  
      //required: retrieve user information from your database (or any source of your choice)
      protected function getUser($login) {} //must return an array of the form: array('login' => '', 'password' => '')
      
      //optional: methods to handle save and retrieve long-term (LT) sessions on your server
      //if you don't want to use the "remember me" technique, you won't need those
      protected function setLTSession($login, $sid, $value) {}  //save the LT session on server-side
      protected function getLTSession($cookieValue) {}          //return LT session data as an array
      protected function unsetLTSession($cookieValue) {}        //unset a specific LT session
      protected function unsetLTSessions($login) {}             //unset all server-side LT session for this user
      protected function flushOldLTSessions() {}                //unset all old server-side LT session
  }
  ```
4. Call your newly created class from your code and use its public methods to authenticate your users:
  ```php
  $logger = new MyLogin('customSessionName');
  
  //when user asks to log out from your website, destroy his/her sessions
  $logger->logOut();
  
  //when user just filled the login form
  $user = $logger->logIn($_POST['login'], $_POST['password'], isset($_POST['remember']));
  
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
5. That's all, folks!


## Dependancies

YosLogin depends on the [Secure-random-bytes-in-PHP by GeorgeArgyros](https://github.com/GeorgeArgyros/Secure-random-bytes-in-PHP/), which is released under the New BSD License. The file needed is already included in the project: srand.php.


## Licence

YosLogin is a work by Yosko, all wright reserved.

It is licensed under the [GNU LGPL](http://www.gnu.org/licenses/lgpl.html).

It also was build on the work of other people:
* GeorgeArgyros Secure-random-bytes-in-PHP, mentioned above
* Marginally, some lines of code may be inspired by [shaarli](https://github.com/sebsauvage/Shaarli)'s authentication system (by sebsauvage), espacially the configuration of the PHP session.