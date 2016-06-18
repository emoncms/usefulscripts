<?php
/*

  PHPTIMESERIES to PHPFINA conversion script

  Script last checked: 18th of June 2016
  
*/
if (posix_geteuid()!=0) {
    print "Please run this script with sudo\n";
    die;
}

print "---------------------------------------------------\n";
print "PHPTIMESERIES to PHPFINA conversion script\n";
print "---------------------------------------------------\n";

$low_memory_mode = false;

define('EMONCMS_EXEC', 1);
require "Lib/PHPFina.php";
require "Lib/EmonLogger.php";

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

// Either use default phptimeseries and phpfina data directories
// or use user specified directory from emoncms/settings.php
$sourcedir = "/var/lib/phptimeseries/";
if (isset($feed_settings["phptimeseries"])) $sourcedir = $feed_settings["phptimeseries"]["datadir"];
$targetdir = "/var/lib/phpfina/";
if (isset($feed_settings["phpfina"])) $targetdir = $feed_settings["phpfina"]["datadir"];

$phpfina = new PHPFina($feed_settings['phpfina']);

// Find all phptimeseries feeds
$result = $mysqli->query("SELECT * FROM feeds WHERE `engine`=2");

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

print "\n";
$phptimeseriesfeeds = array();
// For each PHPTIMESERIES feed
while($row = $result->fetch_array())
{
    print "Feedid:".$row['id']." name:".$row['name']."\n";
    $id = $row['id'];
    $targetfile = $targetdir.$id.".dat";
    
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
    
    print "- Enter interval for PHPFina feed, i.e enter 10 for 10 seconds: ";
    $handle = fopen ("php://stdin","r");
    $interval = (int) trim(fgets($handle));
    
    if (($interval%5)!=0 && $interval>5) {
        print "Interval must be an integer multiple of 5 and more than 10s\n";
    }
    
    $phpfina->create($outid,array("interval"=>$interval));

    // Open phptimeseries data to read
    if (!$fh = @fopen($sourcedir."feed_$id.MYD", 'rb')) {
        print "error opening phptimeseries data file to read\n";
        die;
    }
    
    $filesize = filesize($sourcedir."feed_$id.MYD");
    $npoints = floor($filesize / 9.0);
    
    // Read through file
    for ($i=0; $i<$npoints; $i++)
    {
        // Read next datapoint
        $d = fread($fh,9);

        // Itime = unsigned integer (I) assign to 'time'
        // fvalue = float (f) assign to 'value'
        $array = unpack("x/Itime/fvalue",$d);

        $time = $array['time'];
        $value = $array['value'];
        
        $phpfina->prepare($outid,$time,$value);
        if ($low_memory_mode) $phpfina->save();
    }
    
    $phpfina->save();
    
    // Update last time/value
    /*
    if ($redis) {
        $redis->hMset("feed:$id", array('value' => $value, 'time' => $time));
    } else {
        $mysqli->query("UPDATE feeds SET `time` = '$time', `value` = $value WHERE `id`= '$id'");
    }
    */
    
    print "- Coversion complete, $npoints datapoints\n";
    $mysqli->query("UPDATE feeds SET `engine`=5 WHERE `id`='$outid'");
    $redis->hSet("feed:".$outid,"engine",5);

    exec("chown www-data:www-data ".$targetdir.$outid.".meta");
    exec("chown www-data:www-data ".$targetdir.$outid.".dat");

    // Register feeds to delete
    $phptimeseriesfeeds[] = $id;
}

if ($outid==$id) {
    print "---------------------------------------------------\n";
    print "Delete phptimeseries data files from $sourcedir? (y/n): ";
    $handle = fopen ("php://stdin","r");
    if(trim(fgets($handle)) != 'y') die;

    foreach ($phptimeseriesfeeds as $id) {
        print "Deleting feed $id\n";
        if (file_exists($sourcedir."feed_$id.MYD")) unlink($sourcedir."feed_$id.MYD");
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
