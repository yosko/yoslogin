<pre><?php

//based on http://www.php.net/manual/en/function.microtime.php#85719
function gentime() {
    static $a;
    if($a == 0) $a = microtime(true);
    else return number_format(microtime(true)-$a,6);
}
gentime();

require_once('YosLogin.php');


//extends YosLogin to declare app specific methods (like how to store & retrieve users and sessions)
class MyLogin extends YosLogin {
    public function __construct($sessionName, $LTDir, $nbLTSession, $LTDuration) {
		parent::__construct($sessionName, $LTDir, $nbLTSession, $LTDuration);
	}
    
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
        $fp = fopen($this->LTDir.$login.'|'.$sid, 'w');
        fwrite($fp, gzdeflate(json_encode($value)));
        fclose($fp);
    }
    
    protected function getLTSession($cookieValue) {
        $value = false;
        if (file_exists($this->LTDir.$cookieValue)) {
            
            //unset long-term session if expired
            if(filemtime($this->LTDir.$cookieValue)+$this->LTDuration <= time()) {
                unsetLTSession($cookieValue);
                $value = false;
            } else {
                $value = json_decode(gzinflate(file_get_contents($this->LTDir.$cookieValue)), true);
                //update last access time on file
                touch($this->LTDir.$cookieValue);
            }
        }
        return($value);
    }
    
    //unset a specific LT session
    protected function unsetLTSession($cookieValue) {
        $filePath = $this->LTDir.$cookieValue;
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
    
    protected function unsetLTSessions($login) {
        //unset all server-side LT session for this user
        $files = glob( $this->LTDir.$login.'|*', GLOB_MARK );
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
                unsetLTSession($file);
            }
            ++$i;
        } 
    }
}

$logger = new MyLogin('yoslogin', 'cache/', 200, 2592000, 'a3kFo89eN5Vh');
if(isset($_GET['logout'])) {
    $logger->logOut();

} elseif(isset($_POST['submitLogin']) && isset($_POST['login']) && isset($_POST['password'])) {
    $user = $logger->logIn($_POST['login'], $_POST['password'], isset($_POST['remember']));
    //DO: check if $user have any error and display the login form again
    
} else {
    //anon or already authenticated?
    $user = $logger->authUser();
    //DO: ...
}

?></pre><!DOCTYPE html>
<html lang="fr">
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
    <h1>Yoslogin</h1>
    <div class="content">
<?php if(!isset($user) || $user['isLoggedIn'] === false) { ?>
        <h2>Connexion sur Yoslogin</h2>
        <form id="loginForm" method="post" action="">
            <label for="login">Identifiant</label>
            <input type="text" autofocus="autofocus" name="login" id="login">
            <br>
            <label for="password">Mot de passe</label>
            <input type="password" name="password" id="password">
            <br>
            <input type="checkbox" name="remember" id="remember" value="remember">
            <label for="remember">Se souvenir de moi</label>
            <br>
            <input type="submit" name="submitLogin" id="submitLogin" value="Se connecter" />
        </form>
<?php } else { ?>
        <div>
            <a href="?logout">Se déconnecter</a>
        </div>
<?php } ?>

        <h2>Etat</h2>
        <ul>
            <li>Page chargée <b>le <?php echo date('d/m/Y à H:i:s', time()); ?></b> (heure serveur)</li>
            <li><b>Session PHP :</b>
                <?php
                if($user['isLoggedIn']) {
                    ?>
                    <span class="success">Connecté</span>
                    <?php
                } else {
                    echo "<span class=\"error\">Non connecté</span>";
                }
                ?>
                    <ul>
                        <li>Id de session (cookie) : <?php echo $_COOKIE['yoslogin'] ?></li>
                        <li>Données de session : <?php echo json_encode($_SESSION); ?></li>
                        <li>Expire (côté serveur) : <b>le <?php echo date('d/m/Y à H:i:s', time()+ini_get('session.gc_maxlifetime')); ?></b> (Si la page n&apos;est pas rechargée entre-temps)</li>
                        <li>Expire (côté client) : à la fermeture du navigateur</li>
                    </ul>
            </li>
            <li><b>Session long-terme</b> :
                <?php
                if(isset($_COOKIE['yosloginlt'])) {
                    ?>
                    <span class="success">Connecté</span> (servira à recréer une nouvelle session PHP lorsque la précédente sera terminée)
                    <ul>
                        <li>Valeur du cookie (login|id) : <?php echo $_COOKIE['yosloginlt'] ?></li>
                        <li>Dernier accès : <b>le <?php if (isset($_COOKIE['yosloginlt'])) { echo date('d/m/Y à H:i:s', filemtime('cache/'.$_COOKIE['yosloginlt'])); } ?></b></li>
                        <li>Expire (côté serveur) : <b>le <?php if (isset($_COOKIE['yosloginlt'])) { echo date('d/m/Y à H:i:s', filemtime('cache/'.$_COOKIE['yosloginlt'])+2592000); } ?></b> (Si la page n&apos;est pas rechargée entre-temps)</li>
                        <li>Expire (côté client) : information inaccessible depuis le serveur</li>
                    </ul>
                    <?php
                } else {
                    echo "<span class=\"error\">Non connecté</span> ";
                }
                ?>
            </li>
        </ul>

    </div>
    <footer>
        Yoslogin2, réalisé par <a href="http://www.yosko.net/">Yosko</a>, généré en <?php echo gentime(); ?> s
    </footer>
</body>
</html