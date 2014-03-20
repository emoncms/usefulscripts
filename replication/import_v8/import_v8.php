<?php

    // Emoncms importer

    require "mysql_import.php";
    require "timestore_import.php";
    require "phptimestore_import.php";
    require "phptimeseries_import.php";

    define('EMONCMS_EXEC', 1);
    chdir("/var/www/localnew");
    require "process_settings.php";

    $mysqli = @new mysqli($server,$username,$password,$database);

    $redis = new Redis();
    $redis->connect("127.0.0.1");

    $server = "http://emoncms.org";
    $apikey = "";

    // Fetch user's feed list:
    $feeds = file_get_contents($server."/feed/list.json?apikey=$apikey");
    $feeds = json_decode($feeds);
    
    $userid = 59;

 
    foreach ($feeds as $feed)
    {

        // Check if feed exists in meta data:
        
        $result = $mysqli->query("SELECT id FROM feeds WHERE `id` = '".$feed->id."'");
        if (!$result->num_rows)
        {
            // Create feed
            echo "create feed ".$feed->id."\n";
            
            $result = $mysqli->query("INSERT INTO feeds (id,userid,name,tag,datatype,public,size,engine) VALUES ('".$feed->id."','$userid','".$feed->name."','".$feed->tag."','".$feed->datatype."','".$feed->public."','".$feed->size."','".$feed->engine."')");

            $redis->hMSet("feed:$feed->id",array(
                'id'=>$feed->id,
                'userid'=>$feed->userid,
                'name'=>$feed->name,
                'datatype'=>$feed->datatype,
                'tag'=>$feed->tag,
                'public'=>$feed->public,
                'size'=>$feed->size,
                'engine'=>$feed->engine
            ));
        }
        else
        {
            echo "feed exists ".$feed->id."\n";
        }
        
        // IMPORT
        
        if ($feed->engine==0) {
            import_mysql($mysqli,$userid,$server,$apikey,$feed);
        }

        if ($feed->engine==1 && $feed->datatype==1) {
            import_timestore($mysqli,$userid,$server,$apikey,$feed);
        }
        
        if ($feed->engine==1 && $feed->datatype!=1) {
            error_log("Feed: ".$feed->id." failed to download, incorrect datatype"); 
        }

        if ($feed->engine==2) {
            import_phptimeseries($mysqli,$userid,$server,$apikey,$feed);
        }

        if ($feed->engine==4 && $feed->datatype==1) {
            import_phptimestore($mysqli,$userid,$server,$apikey,$feed);
        }
        
    }
    
    $result = $mysqli->query("SELECT id,userid,name,datatype,tag,public,size,engine FROM feeds WHERE `userid` = '$userid'");
    while ($row = $result->fetch_object())
    {
        $redis->sAdd("user:feeds:$userid", $row->id);
        $redis->hMSet("feed:$row->id",array(
        'id'=>$row->id,
        'userid'=>$row->userid,
        'name'=>$row->name,
        'datatype'=>$row->datatype,
        'tag'=>$row->tag,
        'public'=>$row->public,
        'size'=>$row->size,
        'engine'=>$row->engine
        ));
    }
       
