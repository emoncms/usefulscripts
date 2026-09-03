<?php
print "=======================================\n";
print "EMONCMS PASSWORD RESET\n";
print "=======================================\n";

define('EMONCMS_EXEC', 1);
chdir("/var/www/emoncms");
require "process_settings.php";
// Required explicitly: this script loads process_settings.php but not
// core.php, which is what pulls in the password helpers everywhere else.
require "Lib/password.php";

$mysqli = @new mysqli(
    $settings["sql"]["server"],
    $settings["sql"]["username"],
    $settings["sql"]["password"],
    $settings["sql"]["database"],
    $settings["sql"]["port"]
);
if ( $mysqli->connect_error ) {
    echo "Can't connect to database, please verify credentials/configuration in settings.php<br />";
    if ( $display_errors ) {
        echo "Error message: <b>" . $mysqli->connect_error . "</b>";
    }
    die();
}

$userid = (int)stdin("Select userid, or press enter for default: ");
if ($userid==0) {
    echo "Using default user 1\n";
    $userid = 1;
}

$newpass = stdin("Enter new password, or press enter to auto generate: ");
if ($newpass=="") {
    // Generate new random password. random_bytes, not uniqid(rand()): this
    // value is a password, and rand() is seeded predictably enough that a
    // generated one should not depend on it.
    $newpass = bin2hex(random_bytes(6));
    print "Auto generated password: $newpass\n";
}

// Hash with whatever settings['password']['algo'] selects, bcrypt or
// argon2id. The salt column is cleared with it: both algorithms carry their
// own salt inside the hash, and only rows still on the legacy sha256 format
// read that column. See Lib/password.php.
//
// This used to write the legacy format, which still verified but left the
// account on a fast hash until its owner next logged in. That is the wrong
// way round for a password reset: the person whose password was just set for
// them is exactly who should land on the current algorithm immediately.
$password = hash_password($newpass);
$salt = '';

// Save password and salt
$stmt = $mysqli->prepare("UPDATE users SET password = ?, salt = ? WHERE id = ?");
$stmt->bind_param("ssi", $password, $salt, $userid);
$stmt->execute();
$stmt->close();

echo "Complete: new password set\n";


function stdin($prompt = null){
    if($prompt){
        echo $prompt;
    }
    $fp = fopen("php://stdin","r");
    $line = rtrim(fgets($fp, 1024));
    return $line;
}
