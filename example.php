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

//based on http://www.php.net/manual/en/function.microtime.php#85719
function gentime() {
    static $a;
    if($a == 0) $a = microtime(true);
    else return number_format(microtime(true)-$a,6);
}
gentime();

require_once('yoslogin.class.php');


//extends YosLogin to declare app specific methods (like how to store & retrieve users and sessions)
class ExampleLogin extends YosLogin {
    
    //must return an array of the form: array('login' => '', 'password' => '')
    protected function getUser($login) {
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
    
    //$value is empty for this implementation, but we may need to add data to the LT session in the future
    protected function setLTSession($login, $sid, $value) {
        $fp = fopen($this->LTDir.$login.'_'.$sid.'.ses', 'w');
        fwrite($fp, gzdeflate(json_encode($value)));
        fclose($fp);
    }
    
    protected function getLTSession($cookieValue) {
        $value = false;
        $file = $this->LTDir.$cookieValue.'.ses';
        if (file_exists($file)) {
            
            //unset long-term session if expired
            if(filemtime($file)+$this->LTDuration <= time()) {
                unsetLTSession($cookieValue);
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
    protected function unsetLTSession($cookieValue) {
        $filePath = $this->LTDir.$cookieValue.'.ses';
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
    
    //unset all server-side LT session for this user
    protected function unsetLTSessions($login) {
        $files = glob( $this->LTDir.$login.'_*', GLOB_MARK );
        foreach( $files as $file ) {
            unlink( $file );
        }
    }
    
    protected function flushOldLTSessions() {
        $dir = $this->LTDir;
        
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
            if ($i > $this->nbLTSession || $date+$this->LTDuration <= time()) {
                $this->unsetLTSession(basename($file));
            }
            ++$i;
        } 
    }
}

$logger = new ExampleLogin('exampleSessionName', 200, 2592000, 'cache/');
//use the following instead for local use (to allow local IP address)
// $logger = new ExampleLogin('exampleSessionName', 200, 2592000, 'cache/', true);

if(isset($_GET['logout'])) {
    $logger->logOut();

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
                if($user['isLoggedIn']) {
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
                        <li>Last access : <b>at <?php if (isset($_COOKIE['exampleSessionNamelt'])) { echo date('Y-m-d H:i:s', filemtime('cache/'.$_COOKIE['exampleSessionNamelt'].'.ses')); } ?></b></li>
                        <li>Expire (server-side) : <b>at <?php if (isset($_COOKIE['exampleSessionNamelt'])) { echo date('Y-m-d H:i:s', filemtime('cache/'.$_COOKIE['exampleSessionNamelt'].'.ses')+2592000); } ?></b> (If the page isn&apos;t reloaded in the meantime)</li>
                        <li>Expire (client-side) : not available from the server</li>
                        <li>Secure: <?php echo ($user['secure']) ? '<span class="success">Yes</span>':'<span class="error">No</span>'; ?> (indicates wether the password was entered recently and on THIS ip)</li>
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
                The "secure" flag may always be "false" if you work with a local ip.
            </li>
        </ul>
    </div>
    <footer>
        Yoslogin, code by <a href="http://www.yosko.net/">Yosko</a>, page generated in <?php echo gentime(); ?> s
    </footer>
</body>
</html>