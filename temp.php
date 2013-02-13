<pre><?php

require_once('srand.php');

//read random value from /dev/urandom
//$random = file_get_contents('/dev/urandom', false, null, 0, 31);
$random = secure_random_bytes(31);

//$string = bin2hex($random);
//$number = current(unpack('L', $random));

$base64 = base64_encode($random);
$base64 = str_replace('+', '.', $base64);
$base64 = str_replace('=', '', $base64);

//var_dump($random);
var_dump($base64);

?></pre>