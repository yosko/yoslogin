<?php

require_once('PasswordHash.php');
require_once('srand.php');

abstract class YosLogin {
    protected $hasher, $sessionName, $LTDir, $nbLTSession, $LTDuration, $appSalt, $ltCookie;
    
    public function __construct($sessionName, $LTDir, $nbLTSession, $LTDuration, $appSalt = '') {
        $this->sessionName = $sessionName;
        $this->LTDir = $LTDir;
        $this->nbLTSession = $nbLTSession;
        $this->LTDuration = $LTDuration;
        /*
        if($appSalt != '') {
            $this->appSalt = $appSalt;
        } else {
            $this->appSalt = $this->generateSalt();
        }
        */
        
        $this->hasher = new PasswordHash(8, FALSE);
        $this->ltCookie = $this->loadLtCookie();
    }
    
    abstract protected function getUser($login);
    abstract protected function setLTSession($login, $sid, $value);
    abstract protected function getLTSession($cookieValue);
    abstract protected function unsetLTSession($cookieValue);
    abstract protected function unsetLTSessions($login);
    abstract protected function flushOldLTSessions();
    
    protected function initPHPSession() {
        //force cookie path
        $cookie=session_get_cookie_params();
        session_set_cookie_params($cookie['lifetime'], dirname($_SERVER['SCRIPT_NAME']).'/');
        
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
    
    protected function setLTCookie($login, $id) {
        //TODO: set the real cookie
        $this->ltCookie['login'] = $login;
        $this->ltCookie['id'] = $id;
        //$this->ltCookie['hash'] = $this->hasher->HashPassword($id);
        
        //set or update the long term session on client-side
        setcookie('yosloginlt', $this->ltCookie['login'].'|'.$this->ltCookie['id'], time()+$this->LTDuration, dirname($_SERVER['SCRIPT_NAME']).'/', '', false, true);
    }
    
    protected function unsetLTCookie() {
        //delete long-term cookie client-side
        setcookie($this->sessionName.'lt', null, time()-31536000, dirname($_SERVER['SCRIPT_NAME']).'/', '', false, true);
        $this->ltCookie = false;
    }
    
    protected function loadLTCookie() {
        //TODO: load from the real cookie
        //TODO: hash ID
        //TODO: return array with login and hash
    }
    
    protected function issetLTCookie() {
        return (isset($this->ltCookie) && !is_null($this->ltCookie) && $this->ltCookie !== false);
    }
    
    //generate a random 42 long string with [._A-Za-z0-9]
    protected function generateSessionId() {
        if(file_exists('/dev/urandom')) {
            //31 characters from urandom will give us 42 long base 64 encoded string (+ possible trailing '=')
            $random = file_get_contents('/dev/urandom', false, null, 0, 31);
            
            $sessionId = base64_encode($random);
            $sessionId = str_replace('+', '.', $sessionId);
            $sessionId = str_replace('/', '_', $sessionId);
            $sessionId = str_replace('=', '', $sessionId);    //remove trailing '='
        }
        
        return $sessionId;
    }
    
    /*
    //generate a PHP 5.3.7+ blowfish-compatible salt
    protected function generateSalt(){
        //16 characters from urandom will give us 22 long base 64 encoded string (+ possible trailing '=')
        $random = file_get_contents('/dev/urandom', false, null, 0, 16);
        
        $salt = '$2y$31$';
        $salt = $salt.base64_encode($salt);
        $salt = str_replace('+', '.', $salt);
        $salt = str_replace('=', '', $salt);    //remove trailing '='
        
        //if for some reason the string is not long enough, return false
        if (strlen($salt)>=29)
            return $salt;
        else
            return false;
    }
    
    public function appSalt() {
        return $this->appSalt;
    }
    */
    
    //user is logging out
    public function logOut() {
        $userName = "";
        
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
        unset($_SESSION['uid']);
        unset($_SESSION['login']);
        session_set_cookie_params(time()-31536000, dirname($_SERVER['SCRIPT_NAME']).'/');
        session_destroy();
        
        //to avoid any problem when using the browser's back button
        header("Location: $_SERVER[PHP_SELF]");
    }
    
    //user is trying to log in
    public function logIn($login, $password, $rememberMe = false) {
        
        $user = array();
        
        $this->initPHPSession();
        
        //find user
        $user = $this->getUser($login);
        
        //check user/password
        $user['isLoggedIn'] = false;
        if(empty($user)) {
            $user = array();
            $user['error']['unknownLogin'] = true;
        } elseif(!$this->hasher->CheckPassword($password, $user['password'])) {
            $user['error']['wrongPassword'] = true;
        } else {
            //set session
            $_SESSION['uid']=$this->generateSessionId();
            //$_SESSION['ip']=getIpAddress();
            $_SESSION['login']=$user['login'];
            
            //TODO: load userRelatedInformation in session
            
            var_dump($rememberMe);
            if($rememberMe) {
                //set long-term cookie on client-side
                $this->setLTCookie($_SESSION['login'], $_SESSION['uid']);
                
                //save the long term session on server-side
                $this->setLTSession($this->ltCookie['login'], $this->ltCookie['id'], array());
                
                //maintenance: delete old sessions
                $this->flushOldLTSessions();
            }
            
            $user['isLoggedIn'] = true;
            
            //to avoid any problem when using the browser's back button
            //header("Location: $_SERVER[REQUEST_URI]");
        }
        
        //wrong login or password: return user with errors
        return $user;
    }
    
    //authenticate user if already logged in
    public function authUser() {
        
        $user = array();
        
        $this->initPHPSession();
        
        //user has a PHP session
        if(isset($_SESSION['uid']) && $_SESSION['login']) {
            $user = $this->getUser($_SESSION['login']);
            $user['isLoggedIn'] = true;
            
        //user has LT cookie but no PHP session
        } elseif (isset($_COOKIE[$this->sessionName.'lt']) && !isset($_SESSION['uid'])) {
            //TODO: check if LT session exists on server-side
            $LTSession = $this->getLTSession($_COOKIE[$this->sessionName.'lt']);
            
            if($LTSession !== false) {
                //set session
                $_SESSION['uid']=$_COOKIE[$this->sessionName.'lt']; // generate unique random number (different than phpsessionid)
                $_SESSION['login']=$LTSession['login'];
                $user = $this->getUser($_SESSION['login']);
                $user['isLoggedIn'] = true;
            } else {
                //delete long-term cookie client-side
                setcookie($this->sessionName.'lt', null, time()-31536000, dirname($_SERVER['SCRIPT_NAME']).'/', '', false, true);
            }
        
        //user isn't logged in: anonymous
        } else {
            $user['isLoggedIn'] = false;
        }
        
        return $user;
    }
    
    /*
    public function logUser($logging) {
        
        $this->initPHPSession();
        
        //user is logging out
        if($logging['out'] == true) {
        
        //user has LT cookie but no PHP session
        } elseif (isset($_COOKIE[$this->sessionName.'lt']) && !isset($_SESSION['uid'])) {
            //TODO: check if LT session exists on server-side
            $LTSession = getLTSession($_COOKIE[$this->sessionName.'lt']);
            
            if($LTSession !== false) {
                //set session
                $_SESSION['uid']=$_COOKIE[$this->sessionName.'lt']; // generate unique random number (different than phpsessionid)
                $_SESSION['ip']=$LTSession['ip'];
                $_SESSION['login']=$LTSession['login'];
            } else {
                //delete long-term cookie client-side
                setcookie($this->sessionName.'lt', null, time()-31536000, dirname($_SERVER['SCRIPT_NAME']).'/', '', false, true);
            }
        
        //user is trying to log in
        } elseif (isset($logging['login']) && isset($logging['password'])) {
        }
        
        //DO: return user?
        
        
        
        //if user is logging out or if IP doesn't match
        if(isset($_GET['logout']) || isset($_SESSION['ip']) && $_SESSION['ip']!=getIpAddress()) {
            
            
        //user doesn't have a PHP session but have a long-term cookie, reload session
        } elseif(!isset($_SESSION['uid']) && isset($_COOKIE[$this->sessionName.'lt'])) {
            
            
        //if user trying to log in
        } elseif (isset($_POST['submitLogin']) && isset($_POST['login']) && trim($_POST['login']) != "" && isset($_POST['password']) && trim($_POST['password']) != "") {
            
        }
        
    }
    
    //get client IP from the best source possible (even through a server proxy)
    //based on: http://stackoverflow.com/questions/1634782/what-is-the-most-accurate-way-to-retrieve-a-users-correct-ip-address-in-php
    protected function getIpAddress() {
        foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key){
            if (array_key_exists($key, $_SERVER) === true){
                foreach (explode(',', $_SERVER[$key]) as $ip){
                    $ip = trim($ip); // just to be safe
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false){
                        return $ip;
                    }
                }
            }
        }
    }
    */
}

?>