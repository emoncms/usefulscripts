<?php
print "=======================================\n";
print "EMONCMS PASSWORD RESET\n";
print "=======================================\n";

define('EMONCMS_EXEC', 1);
chdir("/var/www/emoncms");
require "process_settings.php";

$mysqli = @new mysqli($server,$username,$password,$database);
if ( $mysqli->connect_error ) {
    echo "Can't connect to database, please verify credentials/configuration in settings.php<br />";
    if ( $display_errors ) {
        echo "Error message: <b>" . $mysqli->connect_error . "</b>";
    }
    die();
}

$userid = (int)stdin("Select userid, or press enter for default: ");
if ($userid==0) echo "Using default user 1\n";

$newpass = stdin("Enter new password, or press enter to auto generate: ");
if ($newpass=="") {
    // Generate new random password
    $newpass = hash('sha256',md5(uniqid(rand(), true)));
    $newpass = substr($newpass, 0, 10);
    print "Auto generated password: $newpass\n";
}

// Hash and salt
$hash = hash('sha256', $newpass);
$salt = md5(uniqid(rand(), true));
$password = hash('sha256', $salt . $hash);

// Save password and salt
$mysqli->query("UPDATE users SET password = '$password', salt = '$salt' WHERE id = '$userid'");

echo "Complete: new password set\n";


function stdin($prompt = null){
    if($prompt){
        echo $prompt;
    }
    $fp = fopen("php://stdin","r");
    $line = rtrim(fgets($fp, 1024));
    return $line;
}
