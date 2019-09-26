<?php
/*

  PHPFIWA to PHPFINA conversion script

  How it works:
  PHPFiwa stores its bottom layer in the same format as PHPFina.
  This script copies the bottom layer and reads the start_time and 
  interval from the PHPFiwa meta file in order to create the phpfina feed.

  Script last checked: 18th of June 2016
  
*/

if (posix_geteuid()!=0) {
    print "Please run this script with sudo\n";
    die;
}

print "---------------------------------------------------\n";
print "PHPFIWA to PHPFINA conversion script\n";
print "---------------------------------------------------\n";

define('EMONCMS_EXEC', 1);

// Select emoncms directory
$setup = stdin("Is your setup a standard emonpi or emonbase? (y/n): ");

if ($setup=="y" || $setup=="Y") {
    $emoncmsdir = "/var/www/emoncms";
} else {
    $emoncmsdir = stdin("Please enter root directory of your emoncms installation (i.e /var/www/emoncms): ");
}

chdir($emoncmsdir);

if (!file_exists("process_settings.php")) {
    echo "ERROR: This is not a valid emoncms directory, please retry\n"; die;
}
require "process_settings.php";

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

if ($settings['redis']['enabled']) {
    $redis = new Redis();
    $connected = $redis->connect($settings['redis']['host'], $settings['redis']['port']);
    if (!$connected) { echo "Can't connect to redis at ".$settings['redis']['host'].":".$settings['redis']['port']." , it may be that redis-server is not installed or started see readme for redis installation"; die; }
    if (!empty($settings['redis']['prefix'])) $redis->setOption(Redis::OPT_PREFIX, $settings['redis']['prefix']);
    if (!empty($settings['redis']['auth'])) {
        if (!$redis->auth($settings['redis']['auth'])) {
            echo "Can't connect to redis at ".$settings['redis']['host'].", autentication failed"; die;
        }
    }
    if (!empty($settings['redis']['dbnum'])) {
        $redis->select($settings['redis']['dbnum']);
    }
} else {
    $redis = false;
}

// Either use default phpfiwa and phpfina data directories
// or use user specified directory from emoncms/settings.php
$sourcedir = "/var/lib/phpfiwa/";
if (isset($settings["feed"]["phpfiwa"])) $sourcedir = $settings["feed"]["phpfiwa"]["datadir"];
$targetdir = "/var/lib/phpfina/";
if (isset($settings["feed"]["phpfina"])) $targetdir = $settings["feed"]["phpfina"]["datadir"];

// Find all PHPFiwa feeds
$result = $mysqli->query("SELECT * FROM feeds WHERE `engine`=6");

// Quick check at this point so that conversion can be aborted
if ($result->num_rows>0) {
    print "There are ".$result->num_rows." feeds to convert, would you like to continue? (y/n): ";
} else {
    print "There are no feeds to convert\n"; 
    die; 
}
$handle = fopen ("php://stdin","r");
$line = fgets($handle);
if(trim($line) != 'y') exit;

$phpfiwafeeds = array();
// For each PHPFIWA feed
while($row = $result->fetch_array())
{
    print $row['id']." ".$row['name']."\n";
    $id = $row['id'];
    $sourcefile = $sourcedir.$id."_0.dat";
    
    $new_or_overwrite = (int) stdin("- Create a new feed or replace? (enter 1:new, 2:replace) ");
    
    if ($new_or_overwrite==1) {
        $userid = $row['userid'];
        $new_feed = $row['name']." (new)";
        $datatype = DataType::REALTIME;
        $setengine = Engine::PHPFINA;
        $mysqli->query("INSERT INTO feeds (userid,name,datatype,public,engine) VALUES ('$userid','$new_feed','$datatype',false,'$setengine')");
        $outid = $mysqli->insert_id;
        
        if ($redis) {
            $redis->sAdd("user:feeds:$userid", $outid);
            $redis->hMSet("feed:$outid",array('id'=>$outid,'userid'=>$userid,'name'=>$new_feed,'datatype'=>$datatype,'tag'=>"",'public'=>false,'size'=>0,'engine'=>$setengine));
        }
    } else {
        $outid = $id;
    }
    
    $targetfile = $targetdir.$outid.".dat";
    //-----------------------------------
    // META FILE COPY
    //-----------------------------------
    // 1. Read PHPFiwa meta file
    $meta = new stdClass();
    if (!$metafile = @fopen($sourcedir.$id.".meta", 'rb')) {
        print "error opening phpfiwa meta file to read\n";
        die;
    }
    $tmp = unpack("I",fread($metafile,4));
    $tmp = unpack("I",fread($metafile,4)); 
    $meta->start_time = $tmp[1];
    $tmp = unpack("I",fread($metafile,4)); 
    $meta->nlayers = $tmp[1]; 
    
    $meta->npoints = array();
    for ($i=0; $i<$meta->nlayers; $i++)
    {
      $tmp = unpack("I",fread($metafile,4));
    }
    
    $meta->interval = array();
    for ($i=0; $i<$meta->nlayers; $i++)
    {
      $tmp = unpack("I",fread($metafile,4)); 
      $meta->interval[$i] = $tmp[1];
    }
    fclose($metafile);

    // 2. Write PHPFina meta file
    if (file_exists($targetdir.$outid.".meta")) {
        print $targetdir.$id.".meta already exists?\n";
        die;
    }
    
    if (!$metafile = @fopen($targetdir.$outid.".meta", 'wb')) {
        print "- error opening phpfina meta file to write\n";
        die;
    }
    
    fwrite($metafile,pack("I",0));
    fwrite($metafile,pack("I",0)); 
    fwrite($metafile,pack("I",$meta->interval[0]));
    fwrite($metafile,pack("I",$meta->start_time)); 
    fclose($metafile);   
    print "- metafile created: start_time=".$meta->start_time.", interval=".$meta->interval[0]."\n";

    //-----------------------------------
    // DATA FILE COPY
    //-----------------------------------
    print "- cp $sourcefile $targetfile\n";
    exec("cp $sourcefile $targetfile");
    
    // Confirm that copy is the same size
    $s1 = filesize($sourcefile);
    $s2 = filesize($targetfile);
    if ($s1==$s2) {
        print "- $id phpfiwa to phpfina complete\n";
        $mysqli->query("UPDATE feeds SET `engine`=5 WHERE `id`='$outid'");
        $redis->hSet("feed:".$outid,"engine",5);
        
        exec("chown www-data:www-data ".$targetdir.$outid.".meta");
        exec("chown www-data:www-data ".$targetdir.$outid.".dat");

        // Register feeds to delete
        $phpfiwafeeds[] = $id;
    } else {
        print "- copy not exact $s1 $s2\n";
    }
}

if ($outid==$id) {
    print "---------------------------------------------------\n";
    print "Delete phpfiwa data files from $sourcedir? (y/n): ";
    $handle = fopen ("php://stdin","r");
    $line = fgets($handle);
    if(trim($line) != 'y') die;

    foreach ($phpfiwafeeds as $id) {
        print "Deleting feed $id\n";
        if (file_exists($sourcedir.$id.".meta")) unlink($sourcedir.$id.".meta");
        if (file_exists($sourcedir.$id."_0.dat")) unlink($sourcedir.$id."_0.dat");
        if (file_exists($sourcedir.$id."_1.dat")) unlink($sourcedir.$id."_1.dat");
        if (file_exists($sourcedir.$id."_2.dat")) unlink($sourcedir.$id."_2.dat");
        if (file_exists($sourcedir.$id."_3.dat")) unlink($sourcedir.$id."_3.dat");
    }
}


function stdin($prompt = null){
    if($prompt){
        echo $prompt;
    }
    $fp = fopen("php://stdin","r");
    $line = rtrim(fgets($fp, 1024));
    return $line;
}
