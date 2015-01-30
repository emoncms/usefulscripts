<?php

    define('EMONCMS_EXEC', 1);
    chdir("/var/www/emoncms");
    require "process_settings.php";
    $mysqli = @new mysqli($server,$username,$password,$database);
    
    $redis = new Redis();
    $redis->connect("127.0.0.1");
    
    // Directory of phpfina feeds, see: settings.php
    $dir = "/var/lib/timestore/";
    $phpfina_dir = "/var/lib/phpfina/";
    
    $result = $mysqli->query("SELECT * FROM feeds WHERE `engine`= 4");
    
    // Feed id to read: 
    
    while($row = $result->fetch_array())
    {
        print $row['userid']." ".$row['id']." ".$row['name']."\n";
        
        $id = $row['id'];
        
        // read meta data
        $meta = new stdClass();
        
        $feedname = str_pad($id, 16, '0', STR_PAD_LEFT).".tsdb";
        $metafile = fopen($dir.$feedname, 'rb');
        
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
        
        $sourcedata = $dir.str_pad($id, 16, '0', STR_PAD_LEFT)."_0_.dat";
        $targetdata = $phpfina_dir.$id.".dat";
        
        exec("mv $sourcedata $targetdata");
        
        $redis->hset("feed:$id","engine",5);
        $mysqli->query("UPDATE feeds SET `engine`=5 WHERE `id`='$id'");
    }
