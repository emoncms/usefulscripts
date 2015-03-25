<?php
    define('EMONCMS_EXEC', 1);
    print "timestore/phptimestore to phpfina conversion script\n";
    
    $emoncms_dir = "/var/www/emoncms";
    $timestore_dir = "/var/lib/timestore/";
    $phpfina_dir = "/var/lib/phpfina/";
    
    if (!file_exists($emoncms_dir)) {
        print "Could not open ".$emoncms_dir."\n";
        die;
    }
    
    if (!file_exists($timestore_dir)) {
        print "Could not open ".$timestore_dir."\n";
        die;
    }
    
    if (!file_exists($phpfina_dir)) {
        print "Could not open ".$phpfina_dir."\n";
        die;
    }
    
    chdir($emoncms_dir);
    require "process_settings.php";
    $mysqli = @new mysqli($server,$username,$password,$database);
    
    $redis = false;
    if (class_exists('Redis'))
    {
        $redis = new Redis();
        $connected = $redis->connect("127.0.0.1");
        if (!$connected) {
            print "Could not connect to redis\n";
            $redis = false;
        }
    }
    
    $result = $mysqli->query("Show columns from feeds like 'timestore'");
    $row = $result->fetch_array();
    if ($row) $mysqli->query("UPDATE feeds SET `engine`='1' WHERE `timestore`='1'");
    
    $result = $mysqli->query("SELECT * FROM feeds WHERE `engine`= 4 OR `engine`=1");
    print "There are ".$result->num_rows." feeds to convert, would you like to continue?";
    $handle = fopen ("php://stdin","r");
    $line = fgets($handle);
    if(trim($line) != 'y'){
        exit;
    }
    
    while($row = $result->fetch_array())
    {
        print "userid:".$row['userid']." feed:".$row['id']." name:".$row['name']."\n";
        
        $id = $row['id'];
        
        // read meta data
        $meta = new stdClass();
        
        $feedname = str_pad($id, 16, '0', STR_PAD_LEFT).".tsdb";
        $metafile = fopen($timestore_dir.$feedname, 'rb');
        
        fseek($metafile,(8+8+4+4));
          
        $tmp = unpack("I",fread($metafile,8));
        $meta->start_time = $tmp[1];
        $tmp = unpack("I",fread($metafile,4));
        $meta->interval = $tmp[1];
        
        fclose($metafile);
        
        $metafile = fopen($phpfina_dir.$id.".meta", 'wb');
        fwrite($metafile,pack("I",0));
        fwrite($metafile,pack("I",0)); 
        fwrite($metafile,pack("I",$meta->interval));
        fwrite($metafile,pack("I",$meta->start_time)); 
        fclose($metafile);
        
        $sourcedata = $timestore_dir.str_pad($id, 16, '0', STR_PAD_LEFT)."_0_.dat";
        $targetdata = $phpfina_dir.$id.".dat";
        
        print "cp $sourcedata $targetdata\n";
        exec("cp $sourcedata $targetdata");
        
        if ($redis) $redis->hset("feed:$id","engine",5);
        $mysqli->query("UPDATE feeds SET `engine`=5 WHERE `id`='$id'");
        print "Feed $id is now PHPFina\n";
    }
