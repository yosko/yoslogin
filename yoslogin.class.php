<?php

/**
 * Yoslogin - Copyright 2013 Yosko (www.yosko.net)
 * 
 * This file is part of Yoslogin.
 * 
 * Yoslogin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * Yoslogin is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with Yoslogin.  If not, see <http://www.gnu.org/licenses/>.
 * 
 */

require_once('yoslogintools.class.php');

/**
 * Utility class to handle login verification and short/long term sessions
 * 
 * You need to implement the abstract methods to handle the way you retrieve
 * user information and the way you store and retrieve long-term sessions
 */
abstract class YosLogin {
    protected $sessionName, $nbLTSession, $LTDuration, $LTDir, $ltCookie;
    
    /**
     * Initialize the session handler
     * @param string $sessionName base name for the PHP and the long-term sessions
     * @param int    $nbLTSession number of sumultaneous long-term sessions
     * @param int    $LTDuration  duration (in seconds) for long-term sessions
     * @param string $LTDir       optional: path to where the long-term sessions are stored (with trailing '/')
     */
    public function __construct($sessionName, $nbLTSession = 200, $LTDuration = 2592000, $LTDir = 'cache/') {
        $this->sessionName = $sessionName;
        $this->nbLTSession = $nbLTSession;
        $this->LTDuration = $LTDuration;
        $this->LTDir = $LTDir;
        
        $this->ltCookie = $this->loadLtCookie();
    }

    /**
     * Get the user informations. Used to check the password
     * @param  string $login login sent via login form
     * @return array()       required items: array("login" => <login>, "password" => <password hash>)
     *                       or empty array if user not found
     */
    abstract protected function getUser($login);

    /**
     * Save the long term session for the given user and id
     * @param string $login  user login
     * @param string $sid    long-term session id (stored in a cookie too)
     * @param array() $value optional: array of data you want to keep in long-term session on server side
     */
    abstract protected function setLTSession($login, $sid, $value);

    /**
     * Retrieve a long-term session based on the cookie value
     * @param  string $cookieValue the concatenation of <login>_<id> used in the cookie value
     * @return array()             optional: array of data stored in the session (empty if no data)
     *                             or false if long-term session not found or expired
     */
    abstract protected function getLTSession($cookieValue);

    /**
     * Remove a long-term session based on the cookie value
     * @param  string $cookieValue the concatenation of <login>_<id> used in the cookie value
     */
    abstract protected function unsetLTSession($cookieValue);

    /**
     * Remove all existing long-term sessions for a given user
     * @param  string $login user login
     */
    abstract protected function unsetLTSessions($login);

    /**
     * Remove all expired or exceeding long-term sessions
     */
    abstract protected function flushOldLTSessions();
    
    /**
     * Initialize and configure the PHP (short-term) session
     */
    protected function initPHPSession() {
        //force cookie path
        $cookie=session_get_cookie_params();
        $cookieDir = (dirname($_SERVER['SCRIPT_NAME'])!='/') ? dirname($_SERVER['SCRIPT_NAME']) : '';
        session_set_cookie_params($cookie['lifetime'], $cookieDir, $_SERVER['SERVER_NAME']);
        
        // If allowed, shorten the PHP session to 10 minutes
        ini_set('session.gc_maxlifetime', 600);
        // Use cookies to store session.
        ini_set('session.use_cookies', 1);
        // Force cookies for session (phpsessionID forbidden in URL)
        ini_set('session.use_only_cookies', 1);
        // Prevent php to use sessionID in URL if cookies are disabled.
        ini_set('session.use_trans_sid', false);
        
        //Session management
        session_name($this->sessionName);
        session_start();
    }
    
    /**
     * Set the long-term cookie on client side
     * @param string $login user login
     * @param string $id    session id
     */
    protected function setLTCookie($login, $id) {
        $this->ltCookie['login'] = $login;
        $this->ltCookie['id'] = $id;
        
        //set or update the long term session on client-side
        setcookie($this->sessionName.'lt', $this->ltCookie['login'].'_'.$this->ltCookie['id'], time()+$this->LTDuration, dirname($_SERVER['SCRIPT_NAME']).'/', '', false, true);
    }
    
    /**
     * Delete the long-term cookie on client side
     */
    protected function unsetLTCookie() {
        //delete long-term cookie client-side
        setcookie($this->sessionName.'lt', null, time()-31536000, dirname($_SERVER['SCRIPT_NAME']).'/', '', false, true);
        $this->ltCookie = false;
    }
    
    /**
     * Load long-term cookie informations
     */
    protected function loadLTCookie() {
        if( isset($_COOKIE[$this->sessionName.'lt']) ) {
            $this->ltCookie = array();
            $cookieValues = explode('_', $_COOKIE[$this->sessionName.'lt']);
            $this->ltCookie['login'] = $cookieValues[0];
            $this->ltCookie['id'] = $cookieValues[1];
        }
    }
    
    /**
     * Test if the client has a long-term cookie set
     * @return bool if the cookie exists or not
     */
    protected function issetLTCookie() {
        return (isset($this->ltCookie) && !empty($this->ltCookie));
    }
    
    /**
     * Log the user out and redirect him
     */
    public function logOut() {
        $userName = '';
        $this->initPHPSession();
        
        //determine user name
        if(isset($_SESSION['login'])) {
            $userName = $_SESSION['login'];
        } elseif($this->issetLTCookie()) {
            $userName = $this->ltCookie['login'];
        }
        
        //if user wasn't automatically logged out before asking to log out
        if(!empty($userName)) {
            //unset long-term session
            $this->unsetLTSessions($userName);
            $this->unsetLTCookie();
        }
        
        //unset PHP session
        unset($_SESSION['sid']);
        unset($_SESSION['login']);
        unset($_SESSION['secure']);
        $cookieDir = (dirname($_SERVER['SCRIPT_NAME'])!='/') ? dirname($_SERVER['SCRIPT_NAME']) : '';
        session_set_cookie_params(time()-31536000, $cookieDir, $_SERVER['SERVER_NAME']);
        session_destroy();
        
        //to avoid any problem when using the browser's back button
        header("Location: $_SERVER[PHP_SELF]");
    }
    
    /**
     * Try to log the user in
     * @param  string  $login      login sent via sign in form
     * @param  string  $password   clea password sent via sign in form
     * @param  boolean $rememberMe wether we should use a long-term session or not
     * @return array()             user informations (from getUser()) + the values of 'isLoggedIn' and optionally 'error'
     */
    public function logIn($login, $password, $rememberMe = false) {
        $user = array();
        $this->initPHPSession();
        
        //find user
        $user = $this->getUser($login);

        //check user/password
        if(empty($user)) {
            $user = array();
            $user['error']['unknownLogin'] = true;
            $user['isLoggedIn'] = false;
        } elseif(!YosLoginTools::checkPassword($password, $user['password'])) {
            $user['error']['wrongPassword'] = true;
            $user['isLoggedIn'] = false;
        } else {
            //set session
            $_SESSION['login'] = $user['login'];
            $_SESSION['ip'] = YosLoginTools::getIpAddress();
            $_SESSION['secure'] = true; //session is scure, for now
            $user['secure'] = $_SESSION['secure'];
            $user['isLoggedIn'] = true;
            
            //also create a long-term session
            if($rememberMe) {

                $_SESSION['sid'] = YosLoginTools::generateRandomString(42, true);

                if(!empty($_SESSION['sid'])) {
                    $this->setLTCookie($_SESSION['login'], $_SESSION['sid']);
                    $this->setLTSession($this->ltCookie['login'], $this->ltCookie['id'], array());
                    
                    //maintenance: delete old sessions
                    $this->flushOldLTSessions();
                } else {
                    //make sure there is no lt sid set
                    unset($_SESSION['sid']);
                }
            }
            
            //to avoid any problem when using the browser's back button
            header("Location: $_SERVER[REQUEST_URI]");
        }
        
        //wrong login or password: return user with errors
        return $user;
    }
    
    /**
     * Try to authenticate the user (check if already logged in or not)
     * @param  string $password if user reentered his/her password (example: for admin actions)
     * @return array()          user informations (from getUser()) + the values of 'isLoggedIn'
     */
    public function authUser($password = '') {
        $user = array();
        $this->initPHPSession();

        //user has a PHP session
        if(isset($_SESSION['login']) && isset($_COOKIE[$this->sessionName])) {
            $user = $this->getUser($_SESSION['login']);
            $user['isLoggedIn'] = true;

            //if ip change, the session isn't secure anymore, even if legitimate
            //  it might be because the user was given a new one
            //  or because if a session hijacking
            if(!isset($_SESSION['ip']) || $_SESSION['ip'] != YosLoginTools::getIpAddress()) {
                $_SESSION['secure'] = false;
            }
            
        //user has LT cookie but no PHP session
        } elseif (isset($_COOKIE[$this->sessionName.'lt'])) {
            //TODO: check if LT session exists on server-side
            $LTSession = $this->getLTSession($_COOKIE[$this->sessionName.'lt']);
            
            if($LTSession !== false) {
                //set php session
                $cookieValues = explode('_', $_COOKIE[$this->sessionName.'lt']);
                $_SESSION['login'] = $cookieValues[0];
                $_SESSION['secure'] = false;    //supposedly not secure anymore
                $user = $this->getUser($_SESSION['login']);
                $user['isLoggedIn'] = true;

                //regenerate long-term session
                $this->unsetLTSession($_COOKIE[$this->sessionName.'lt']);
                $_SESSION['sid']=YosLoginTools::generateRandomString(42, true);
                $this->setLTCookie($_SESSION['login'], $_SESSION['sid']);
                $this->setLTSession($this->ltCookie['login'], $this->ltCookie['id'], array());
            } else {
                //delete long-term cookie
                setcookie($this->sessionName.'lt', null, time()-31536000, dirname($_SERVER['SCRIPT_NAME']).'/', '', false, true);
                
                header("Location: $_SERVER[REQUEST_URI]");
            }
        
        //user isn't logged in: anonymous
        } else {
            $user['isLoggedIn'] = false;
        }
        
        //if a password was given, check it
        if($user['isLoggedIn']) {
            if(!empty($password)) {
                if(YosLoginTools::checkPassword($password, $user['password'])) {
                    $_SESSION['ip'] = YosLoginTools::getIpAddress();
                    $_SESSION['secure'] = true;
                    
                    header("Location: $_SERVER[REQUEST_URI]");
                } else {
                    $user['error']['wrongPassword'] = true;
                }
            }
            $user['secure'] = $_SESSION['secure'];
        }
        
        return $user;
    }
}

?>