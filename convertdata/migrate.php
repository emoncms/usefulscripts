<?php

if (file_exists("/tmp/migratelock")) {
    echo "Already running\n";
    die;
}

$timestore_dir = "/var/lib/timestore/";

$apply = false;
if (isset($_GET['apply']) && $_GET['apply']=="yes") $apply = true;

$fp = fopen("/tmp/migratelock", "a");
fwrite($fp,"scipt: ".time()."\n");
fclose($fp);

?>

<div style="margin:20px; padding:20px; background-color:#eee; font-family:arial">

<h3>Emoncms migration script</h3>
<p>From v6 & v7 to v8.5</p>

<pre>
<?php
set_time_limit(600);

define('EMONCMS_EXEC', 1);

require "process_settings.php";
require "core.php";
require "Modules/log/EmonLogger.php";

$phpfina_dir = $feed_settings['phpfina']['datadir'];
$phptimeseries_dir = $feed_settings['phptimeseries']['datadir'];

$mysqli = @new mysqli($server,$username,$password,$database);

require "Modules/feed/engine/PHPTimeSeries.php";
$phptimeseries = new PHPTimeSeries(array('datadir'=>$phptimeseries_dir));

if ( $mysqli->connect_error ) {
    print "Error connecting to mysql database<br>";
    exit;
} else {
    print "Connected to mysql database: $database\n";
}

if (class_exists('Redis') && $redis_enabled) {
    $redis = new Redis();
    $connected = $redis->connect("127.0.0.1");
    if (!$connected) {
        print "Can't connect to redis database\n";
    } else {
        print "Connected to redis-server\n";
    }
} else {
    $redis = false;
}

require "Lib/dbschemasetup.php";
if (!db_check($mysqli,$database)) {
    print "No tables present in database\n";
    exit;
}

$changes = db_schema_setup($mysqli,load_db_schema(),false);
print "Found ".count($changes)." changes to the mysql database schema\n";

if ($apply) {
    $changes = db_schema_setup($mysqli,load_db_schema(),true);
    print "Applied ".count($changes)." changes to the mysql database schema\n";
}

print "\n";

if (file_exists($timestore_dir)) {
    print "$timestore_dir exists\t\t[OK]\n"; 
} else {
    print "timestore directory does not exist at: $timestore_dir, is this the correct location?\n";
    exit;
}

if (file_exists($phpfina_dir)) {
    print "$phpfina_dir exists\t\t[OK]\n"; 
} else {
    print "phpfina directory does not exist at: $phpfina_dir, please create directory\n";
    exit;
}

if (is_writeable($phpfina_dir)) {
    print "$phpfina_dir writeable\t\t[OK]\n"; 
} else {
    print "phpfina directory is not writeable at: $phpfina_dir, please set ownership to www-data\n";
    exit;
}

if (file_exists($phptimeseries_dir)) {
    print "$phptimeseries_dir exists\t[OK]\n"; 
} else {
    print "phptimeseries directory does not exist at: $phptimeseries_dir, please create directory\n";
    exit;
}

if (is_writeable($phptimeseries_dir)) {
    print "$phptimeseries_dir writeable\t[OK]\n"; 
} else {
    print "phptimeseries directory is not writeable at: $phptimeseries_dir, please set ownership to www-data\n";
    exit;
} 




$result = $mysqli->query("Show columns from feeds like 'timestore'");
$row = $result->fetch_array();
if ($row) {
    print "feeds table with timestore field found\n";
    print "setting engine field in feeds table\n";
    $mysqli->query("UPDATE feeds SET `engine`='1' WHERE `timestore`='1' AND `engine`<4");
}

print "\n";
$result = $mysqli->query("SELECT * FROM feeds WHERE `engine`= 4 OR `engine`=1");
print "Found ".$result->num_rows." timestore/phptimestore feeds to convert\n";

if ($apply) {
    if ($result->num_rows==0) print "Nothing to apply\n";
    
    while($row = $result->fetch_array())
    {
        print "converting feed userid:".$row['userid']." feed:".$row['id']." name:".$row['name']."\n";
        

        $id = $row['id'];
        // read meta data
        $meta = new stdClass();
        $feedname = str_pad($id, 16, '0', STR_PAD_LEFT).".tsdb";
        if ($metafile = fopen($timestore_dir.$feedname, 'rb'))
        {
            fseek($metafile,(8+8+4+4));
            $tmp = unpack("I",fread($metafile,8));
            $meta->start_time = $tmp[1];
            $tmp = unpack("I",fread($metafile,4));
            $meta->interval = $tmp[1];
            fclose($metafile);
            print "found timestore metafile:\n";
            print "- start_time: ".$meta->start_time."\n";
            print "- interval: ".$meta->interval."\n";
            
            if (file_exists($phpfina_dir.$id.".meta")) {
                print "phpfina metafile already exists\n";
            } else {
            
            if ($metafile = fopen($phpfina_dir.$id.".meta", 'wb'))
            {
                print "creating phpfina metafile\n";
                fwrite($metafile,pack("I",0));
                fwrite($metafile,pack("I",0));
                fwrite($metafile,pack("I",$meta->interval));
                fwrite($metafile,pack("I",$meta->start_time));
                fclose($metafile);
            
                $sourcedata = $timestore_dir.str_pad($id, 16, '0', STR_PAD_LEFT)."_0_.dat";
                $targetdata = $phpfina_dir.$id.".dat";
                
                if (file_exists($targetdata)) {
                    print "phpfina data file already exists\n";
                } else {
                    print "copying timestore data over to phpfina data folder\n";
                    print "cp $sourcedata $targetdata\n";
                    exec("cp $sourcedata $targetdata");
                
                    if ($redis) $redis->hset("feed:$id","engine",5);
                    $mysqli->query("UPDATE feeds SET `engine`=5 WHERE `id`='$id'");
                    print "Feed $id is now PHPFina\n";
                }
            } else {
                print "could not create phpfina meta file\n";
            }
            }
        } else {
            print "could not open timestore meta file!\n";
        }
    }

}

print "\n";
$result = $mysqli->query("SELECT * FROM feeds WHERE `engine`= 0 AND `datatype`= 2");
print "Found ".$result->num_rows." daily mysql feeds to convert\n";

if ($apply) {
    if ($result->num_rows==0) print "Nothing to apply\n";

    while($row = $result->fetch_array())
    {
        print "converting feed userid:".$row['userid']." feed:".$row['id']." name:".$row['name']."\n";
        
        $id = $row['id'];
        $current_feed_value = $row['value'];
        print "- current value is: $current_feed_value\n";
        
        $engineresult = $phptimeseries->create($id,0);
        if ($engineresult == true) {
            print "created phptimeseries feed\n";
	        $data = $mysqli->query("SELECT * FROM feed_{$id}");
	        print "copying ".$data->num_rows." datapoints\n";
            while($row = $data->fetch_array()) {
                $postresult = $phptimeseries->post($id,$row['time'],$row['data']);
            }
            
            $mysqli->query("UPDATE feeds SET `engine`=2 WHERE `id`='$id'");
            $mysqli->query("UPDATE feeds SET `value`=$current_feed_value WHERE `id`='$id'");

            if ($redis) $redis->hset("feed:$id","engine",2);
            print "Feed $id is now PHPTimeseries\n";        
            
        } else {
            print "could not create phptimeseries feed\n";
        }
    }

}
if ($redis) $redis->flushall();
unlink("/tmp/migratelock");
?>
</pre>

<p><a href="?apply=yes">Click on this link to start migration</a></p>
<p><b>Migration may take a few minutes, window may become unresponsive, wait until complete before refreshing the page</b></p>
</div>
