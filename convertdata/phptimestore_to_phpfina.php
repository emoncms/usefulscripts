<?php
/*

  PHPTIMESTORE/TIMESTORE to PHPFINA conversion script

  How it works:
  PHPTIMESTORE stores its bottom layer in the same format as PHPFina.
  This script copies the bottom layer and reads the start_time and 
  interval from the PHPTIMESTORE meta file in order to create the phpfina feed.

  Script last checked: 18th of June 2016
  
*/

if (posix_geteuid()!=0) {
    print "Please run this script with sudo\n";
    die;
}

print "---------------------------------------------------\n";
print "PHPTIMESTORE/TIMESTORE to PHPFINA conversion script\n";
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

$mysqli = @new mysqli($server,$username,$password,$database,$port);
if ( $mysqli->connect_error ) {
    echo "Can't connect to database, please verify credentials/configuration in settings.php<br />";
    if ( $display_errors ) {
        echo "Error message: <b>" . $mysqli->connect_error . "</b>";
    }
    die();
}

if ($redis_enabled) {
    $redis = new Redis();

    // older emoncms versions do not have redis server name and port defined, use defaults
    if (empty($redis_server['host'])) $redis_server['host'] = "localhost";
    if (empty($redis_server['port'])) $redis_server['port'] = "6379";
        
    $connected = $redis->connect($redis_server['host'], $redis_server['port']);
    if (!$connected) { echo "Can't connect to redis at ".$redis_server['host'].":".$redis_server['port']." , it may be that redis-server is not installed or started see readme for redis installation"; die; }
    if (!empty($redis_server['prefix'])) $redis->setOption(Redis::OPT_PREFIX, $redis_server['prefix']);
    if (!empty($redis_server['auth'])) {
        if (!$redis->auth($redis_server['auth'])) {
            echo "Can't connect to redis at ".$redis_server['host'].", autentication failed"; die;
        }
    }
} else {
    $redis = false;
}

// Either use default phpfina data directories
// or use user specified directory from emoncms/settings.php
$sourcedir = "/var/lib/timestore/";
if (isset($feed_settings["timestore"]["datadir"])) $sourcedir = $feed_settings["timestore"]["datadir"];
$targetdir = "/var/lib/phpfina/";
if (isset($feed_settings["phpfina"])) $targetedir = $feed_settings["phpfina"]["datadir"];

// Find all timestore and PHPTimestore feeds
$result = $mysqli->query("SELECT * FROM feeds WHERE `engine`=1 OR `engine`=4");

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

$phptimestorefeeds = array();
// For each PHPFINA feed
while($row = $result->fetch_array())
{
    print $row['id']." ".$row['name']."\n";
    $id = $row['id'];
    $sourcefile = $sourcedir.str_pad($id, 16, '0', STR_PAD_LEFT)."_0_.dat";
    
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
    // 1. Read PHPTimestore meta file
    $meta = new stdClass();
    if (!$metafile = @fopen($sourcedir.str_pad($id, 16, '0', STR_PAD_LEFT).".tsdb", 'rb')) {
        print "- error opening phptimestore meta file to read\n";
        die;
    }
    fseek($metafile,(8+8+4+4));
    $tmp = unpack("I",fread($metafile,8));
    $meta->start_time = $tmp[1];
    $tmp = unpack("I",fread($metafile,4));
    $meta->interval = $tmp[1];
    fclose($metafile);

    // 2. Write PHPFina meta file
    if (file_exists($targetdir.$outid.".meta")) {
        print $targetdir.$outid.".meta already exists?\n";
        die;
    }
    
    if (!$metafile = @fopen($targetdir.$outid.".meta", 'wb')) {
        print "- error opening phpfina meta file to write\n";
        die;
    }
    
    fwrite($metafile,pack("I",0));
    fwrite($metafile,pack("I",0)); 
    fwrite($metafile,pack("I",$meta->interval));
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
        print "- $id phptimestore to phpfina complete\n";
        $mysqli->query("UPDATE feeds SET `engine`=5 WHERE `id`='$outid'");
        $redis->hSet("feed:".$outid,"engine",5);
        
        exec("chown --reference=".$targetdir." ".$targetdir.$outid.".meta");
        exec("chown --reference=".$targetdir." ".$targetdir.$outid.".dat");
        
        // Register feeds to delete
        $phptimestorefeeds[] = $id;
    } else {
        print "- copy not exact $s1 $s2\n";
    }
}

if ($outid==$id) {
    print "---------------------------------------------------\n";
    print "Delete phptimestore data files from $sourcedir? (y/n): ";
    $handle = fopen ("php://stdin","r");
    $line = fgets($handle);
    if(trim($line)!='y') die;

    foreach ($phptimestorefeeds as $id) {
        print "Deleting feed $id\n";
        if (file_exists($sourcedir.str_pad($id, 16, '0', STR_PAD_LEFT).".tsdb")) unlink($sourcedir.str_pad($id, 16, '0', STR_PAD_LEFT).".tsdb");
        if (file_exists($sourcedir.str_pad($id, 16, '0', STR_PAD_LEFT)."_0_.dat")) unlink($sourcedir.str_pad($id, 16, '0', STR_PAD_LEFT)."_0_.dat");
        if (file_exists($sourcedir.str_pad($id, 16, '0', STR_PAD_LEFT)."_1_.dat")) unlink($sourcedir.str_pad($id, 16, '0', STR_PAD_LEFT)."_1_.dat");
        if (file_exists($sourcedir.str_pad($id, 16, '0', STR_PAD_LEFT)."_2_.dat")) unlink($sourcedir.str_pad($id, 16, '0', STR_PAD_LEFT)."_2_.dat");
        if (file_exists($sourcedir.str_pad($id, 16, '0', STR_PAD_LEFT)."_3_.dat")) unlink($sourcedir.str_pad($id, 16, '0', STR_PAD_LEFT)."_3_.dat");
        if (file_exists($sourcedir.str_pad($id, 16, '0', STR_PAD_LEFT)."_4_.dat")) unlink($sourcedir.str_pad($id, 16, '0', STR_PAD_LEFT)."_4_.dat");
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
