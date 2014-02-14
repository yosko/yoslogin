<pre><?php

/* 
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

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors','On');
ini_set('log_errors', 'On');
ini_set('error_log', 'errors.log');

//based on http://www.php.net/manual/en/function.microtime.php#85719
function gentime() {
    static $a;
    if($a == 0) $a = microtime(true);
    else return number_format(microtime(true)-$a,6);
}
gentime();

// require_once('../src/authcontroller.class.php');
require_once('../yoslogin.lib.php');

/**
 * requeried function (or class method) used as callback by YosLogin to control
 * a user login and password
 * @param  string $login the user login
 * @return array         associative array with at least a 'login' and a 'password'
 *                       or just false if user doesn't exist
 */
function getUser($login) {
    $foundUser = array();
    $users = json_decode(file_get_contents("user.json"), true);
    foreach($users as $user) {
        if(trim($login) == $user['login']) {
            $foundUser = $user;
            break;
        }
    }
    return($foundUser);
}

/**
 * (optional) Define your own long-term session storing system
 * (either a list of functions or a static class or even a classe to instanciate).
 * This example is based on flat files, but you could use a database or any other system.
 */
class MyLongTermSessionManager {
     // path to where the long-term sessions are stored
    protected static $LTDir = 'cache/';
    // number of sumultaneous long-term sessions allowed
    protected static $nbLTSession = 200;
    // duration (in seconds) for long-term sessions (2592000 seconds = 30 days)
    public static $LTDuration = 2592000;

    //$value is empty for this implementation, but we may need to add data to the LT session in the future
    public static function setLTSession($login, $sid, $value) {
        //create the session directory if needed
        if(!file_exists(self::$LTDir)) { mkdir(self::$LTDir, 0700, true); }

        $fp = fopen(self::$LTDir.$login.'_'.$sid.'.ses', 'w');
        fwrite($fp, gzdeflate(json_encode($value)));
        fclose($fp);
    }
    
    public static function getLTSession($login, $sid) {
        $value = false;
        $file = self::$LTDir.$login.'_'.$sid.'.ses';
        if (file_exists($file)) {
            
            //unset long-term session if expired
            if(filemtime($file)+self::$LTDuration <= time()) {
                $this->unsetLTSession($login, $sid);
                $value = false;
            } else {
                $value = json_decode(gzinflate(file_get_contents($file)), true);
                //update last access time on file
                touch($file);
            }
        }
        return($value);
    }
    
    //unset a specific LT session
    public static function unsetLTSession($login, $sid) {
        $filePath = self::$LTDir.$login.'_'.$sid.'.ses';
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
    
    //unset all server-side LT session for this user
    public static function unsetLTSessions($login) {
        $files = glob( self::$LTDir.$login.'_*', GLOB_MARK );
        foreach( $files as $file ) {
            unlink( $file );
        }
    }
    
    public static function flushOldLTSessions() {
        $dir = self::$LTDir;
        
        //list all the session files
        $files = array();
        if ($dh = opendir($dir)) {
            while ($file = readdir($dh)) {
                if(!is_dir($dir.$file)) {
                    if ($file != "." && $file != "..") {
                        $files[$file] = filemtime($dir.$file);
                    }
                }
            }
            closedir($dh);
        }
        
        //sort files by date (descending)
        arsort($files);
        
        //check each file
        $i = 1;
        foreach($files as $file => $date) {
            if ($i > self::$nbLTSession || $date+self::$LTDuration <= time()) {
                $this->unsetLTSession(basename($file));
            }
            ++$i;
        } 
    }
}

//
$logger = new \Yosko\YosLogin(
    //required: the name to give to the session on your users computers
    'exampleSessionName',

    //required: callback function/method to let YosLogin retrieve a user's login & password hash
    'getUser',

    //optional: redirection page after a successful login/logout/secure/unsecure
    //if empty, redirection to the sale URL without GET parameters
    '',

    //optional: whether to allow local IPs or not (default: false. Setting it to true can be less secure)
    true,

    //optional: path to a log file where YosLogin should trace authentication actions
    'yoslogin.log'
);

//optional: define a long-term session handling that YosLogin can use
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
//use the following instead for local use (to allow local IP address)
// $logger = new ExampleLogin('exampleSessionName', 200, 2592000, 'cache/', true);

if(isset($_GET['logout'])) {
    $logger->logOut();

} elseif(isset($_GET['unsecure'])) {
    $logger->unsecure();

} elseif(isset($_POST['submitLogin']) && isset($_POST['login']) && isset($_POST['password'])) {
    $user = $logger->logIn($_POST['login'], $_POST['password'], isset($_POST['remember']));
    //DO: check if $user have any error and display the login form again
    
} else {
    //anon or already authenticated?
    $password = '';
    if(isset($_POST['password'])) {
        $password = $_POST['password'];
    }
    $user = $logger->authUser($password);
    //DO: ...
}

?></pre><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Yoslogin</title>
    <style>
body {
    background-color: #eee;
    font-family: Verdana, Helvetica, Arial, sans-serif;
    font-size: 12px;
    color: #000000;
}
html, h1, h2, h3, h4, h5, h6 { margin: 0; padding: 0; }
form { border:1px solid #666; margin:3px; padding:3px; }
label { width:200px; }
footer { font-size: 0.8em; color: #666; }
.success { font-weight:bold; color:green; }
.error { font-weight:bold; color:red; }
    </style>
</head>
<body>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <h1>Yoslogin</h1>
    <div class="content">
<?php if(!isset($user) || $user['isLoggedIn'] === false) { ?>
        <h2>Connection on Yoslogin</h2>
        <form id="loginForm" method="post" action="">
            <label for="login">Login</label>
            <input type="text" autofocus="autofocus" name="login" id="login">
            <br>
            <label for="password">Password</label>
            <input type="password" name="password" id="password">
            <br>
            <input type="checkbox" name="remember" id="remember" value="remember">
            <label for="remember">Remember me</label>
            <br>
            <input type="submit" name="submitLogin" id="submitLogin" value="Sign in" />
        </form>
<?php } else { ?>
    <?php if($user['secure'] === false) { ?>
        <div>
            You are logged in, but we can't be absolutely sure your session wasn't hijacked.
            If you wan't to make your session secure again (example: for admin access), please
            enter your password again
        </div>
        <form id="secureForm" method="post" action="">
            <label for="password">Password</label>
            <input type="password" name="password" id="password">
            <br>
            <input type="submit" name="submitPassword" id="submitPassword" value="Secure me" />
        </form>
    <?php } else { ?>
        <div>
            <a href="?unsecure">Unsecure my connection</a>
        </div>
    <?php } ?>
        <div>
            <a href="?logout">Sign out</a>
        </div>
<?php } ?>

        <h2>State</h2>
        <ul>
            <li>Page loaded <b>at <?php echo date('Y-m-d H:i:s', time()); ?></b> (server hour)</li>
            <li><b>PHP Session :</b>
                <?php
                if(isset($user) && $user['isLoggedIn']) {
                    ?>
                    <span class="success">Connected</span>
                    <?php
                } else {
                    echo "<span class=\"error\">Not connected</span>";
                }
                ?>
                    <ul>
                        <li>Cookie value: <?php echo $_COOKIE['exampleSessionName'] ?></li>
                        <li>Session data ($_SESSION): <?php echo json_encode($_SESSION); ?></li>
                        <li>Expire (server-side): <b>at <?php echo date('Y-m-d H:i:s', time()+ini_get('session.gc_maxlifetime')); ?></b> (If the page isn&apos;t reloaded in the meantime)</li>
                        <li>Expire (client-side): when the browser is closed</li>
                    </ul>
            </li>
            <li><b>Long-term session</b> :
                <?php
                if(isset($_COOKIE['exampleSessionNamelt'])) {
                    ?>
                    <span class="success">Connected</span> (will be used to generate a new PHP session when the old one expires)
                    <ul>
                        <li>Cookie value (login_id) : <?php echo $_COOKIE['exampleSessionNamelt'] ?></li>
                        <li>Last access : <b><?php if (isset($_COOKIE['exampleSessionNamelt']) && file_exists('cache/'.$_COOKIE['exampleSessionNamelt'].'.ses')) { echo 'at '.date('Y-m-d H:i:s', filemtime('cache/'.$_COOKIE['exampleSessionNamelt'].'.ses')); } else { echo 'Session file not found on server side.'; } ?></b></li>
                        <li>Expire (server-side) : <b><?php if (isset($_COOKIE['exampleSessionNamelt']) && file_exists('cache/'.$_COOKIE['exampleSessionNamelt'].'.ses')) { echo 'at '.date('Y-m-d H:i:s', filemtime('cache/'.$_COOKIE['exampleSessionNamelt'].'.ses')+2592000); } else { echo 'Session file not found on server side.'; } ?></b> (If the page isn&apos;t reloaded in the meantime)</li>
                        <li>Expire (client-side) : not available from the server</li>
                        <li>Secure: <?php echo (isset($user) && $user['secure']) ? '<span class="success">Yes</span>':'<span class="error">No</span>'; ?> (indicates wether the password was entered recently and on THIS ip)</li>
                    </ul>
                    <?php
                } else {
                    echo "<span class=\"error\">Not connected</span> ";
                }
                ?>
            </li>
        </ul>
        <h2>Notes</h2>
        <ul>
            <li>
                Displayed cookie values refer to the cookie state before the page is executed on the server.
                The real value of your cookie might have change with the server response.
            </li>
            <li>
                The "secure" flag may always be "false" if you work locally and haven't set YosLogin to accept local IPs.
            </li>
        </ul>
    </div>
    <footer>
        <a href="https://github.com/yosko/yoslogin">Yoslogin <?php echo $logger->getVersion(); ?></a>, code by <a href="http://www.yosko.net/">Yosko</a>, page generated in <?php echo gentime(); ?> s
    </footer>
</body>
</html>