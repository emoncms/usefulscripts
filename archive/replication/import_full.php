<?php

  define('EMONCMS_EXEC', 1);
  
  chdir("/var/www/emoncms");
  // 1) Load settings and core scripts
  require "process_settings.php";
  
  $mysqli = @new mysqli($server,$username,$password,$database);

  $redis = new Redis(); 
  $redis->connect("127.0.0.1");  
    
  include "Modules/feed/feed_model.php";
  $feedclass = new Feed($mysqli,$redis,$timestore_adminkey);
  
  // local userid
  $userid = 1;

  // 1) Fetch remote server feeds
  $server = "http://192.168.1.69/emoncms";
  $apikey = "enter your apikey here";
  
  $feeds = file_get_contents($server."/feed/list.json?apikey=$apikey");
  $feeds = json_decode($feeds);
  
  // 2) For each feed on the remote
  foreach ($feeds as $feed)
  {
    if ($feed->datatype==1 && $feed->engine==1) import_timestore($mysqli,$userid,$server,$apikey,$feed);
    if ($feed->engine==0) import_mysql($mysqli,$userid,$server,$apikey,$feed);
    //echo " Downloading: ".$feed->id." ".$feed->name."\n"; 
  }
  
  function import_timestore($mysqli,$userid,$server,$apikey,$feed)
  { 
    // We start by checking if there is a local feed that has the same name as the remote feed
    // Decided against this - best to use same feedid's as target
    //$result = $mysqli->query("SELECT id FROM feeds WHERE `name` = '".$feed->name."' AND `userid` = '$userid'");
    //$row = $result->fetch_array();
    //$feedid = $row['id'];    
    
    $result = $mysqli->query("SELECT id FROM feeds WHERE `id` = '".$feed->id."'");
    if (!$result->num_rows)
    {
      // Create feed
      echo "create feed ".$feed->id."\n";
      $result = $mysqli->query("INSERT INTO feeds (id,userid,name,tag,datatype,public,engine) VALUES ('".$feed->id."','$userid','".$feed->name."','".$feed->tag."','".$feed->datatype."','false','1')");
      
      $feedclass->load_feed_to_redis($feed->id);
    }
    else
    {
      echo "feed exists ".$feed->id."\n";
    }
    
    // Get the timestore meta deta for the remote feed: start time, interval and number of rows
    $remote = json_decode(file_get_contents($server."/feed/getmeta.json?apikey=$apikey&id=".$feed->id));
        
    // Check if there is a local timestore feed (REPEAT OF ABOVE)
    $feedname = "/var/lib/timestore/".str_pad($feed->id, 16, '0', STR_PAD_LEFT).".tsdb";
    if (!file_exists($feedname))
    {
      $feedname = "/var/lib/timestore/".str_pad($feed->id, 16, '0', STR_PAD_LEFT).".tsdb";
      $url = $server."/feed/exportmeta.json?apikey=$apikey&id=".$feed->id;
      $primary = @fopen( $url, 'r' );
      $backup = fopen($feedname, 'a');
      
      for (;;)
      {
        $data = fread($primary,272);
        fwrite($backup,$data);
        if (feof($primary)) break;
      }
      
      fclose($backup);
      fclose($primary);
    }
    
    // This block of code extracts the meta data from the local timestore feed by opening the .tsdb meta file directly
    $local = array();
    $metafile = fopen($feedname, 'rb');
    fseek($metafile,20);
    $tmp = unpack("I",fread($metafile,4)); 
    $local['npoints'] = $tmp[1];
    $tmp = unpack("I",fread($metafile,8)); 
    $local['start'] = $tmp[1];
    $tmp = unpack("I",fread($metafile,4)); 
    $local['interval'] = $tmp[1];
    fclose($metafile);
    
    // We now check if the local meta data is the same as the remote meta data.
    // Given that the starttime, the interval and the feedname is the same we assume
    // that we are dealing with the same feed
    if ($local['start'] == $remote->start && $local['interval'] == $remote->interval)
    {
      // We want to download only the data that has not been downloaded already
      // which will be any data after the last datapoint in our local file.
      // So here we calculate the timestamp of the last datapoint in the file
      $downloadfrom = $local['start'] + ($local['npoints'] * $local['interval']);

      //-----------------------------------------------------------------------------------------------
      // START DOWNLOADING
      
      echo " Downloading: r:".$feed->id." to l:".$feed->id." ".$feed->name."\n";         
      // Download all 6 timestore layers
      for ($layer=0; $layer<6; $layer++)
      {
        
        echo "--layer: $layer ";
        $feedname = "/var/lib/timestore/".str_pad($feed->id, 16, '0', STR_PAD_LEFT)."_".$layer."_.dat";

        
        $downloadfrom = filesize($feedname);
        $url = $server."/feed/export.json?apikey=$apikey&id=".$feed->id."&layer=$layer&start=$downloadfrom";
        $primary = @fopen( $url, 'r' );

        if ($downloadfrom>=4) {
          // update last datapoint
          $firstdp = fread($primary,4);
          
          $backup = fopen($feedname, 'c');
            fseek($backup,$downloadfrom-4);
            fwrite($backup,$firstdp);
          fclose($backup);
        }
        
        $backup = fopen($feedname, 'a');
        
        $dnsize = 0;
        if ($primary)
        {
          for (;;)
          {
            $data = fread($primary,8192);
            $p1 = ftell($backup);
            fwrite($backup,$data);
            $dnsize += ftell($backup) - $p1;
            if (feof($primary)) break;
          }
        }

        fclose($backup);
        fclose($primary);
        
        $fh = fopen("/var/lib/timestore/".str_pad($feed->id, 16, '0', STR_PAD_LEFT).".tsdb", 'c');
        fseek($fh,20);
        fwrite($fh, pack("I",$remote->npoints));
        fclose($fh);
        
        echo $dnsize." bytes\n";
      }
      //----------------------------------------------------------------------------------------------
    }
    else
    {
      echo "local and remote meta data do not match\n";
    }
  }
  
  //--------------------------------------------------------------------------------------------------
  // IMPORT MYSQL
  //--------------------------------------------------------------------------------------------------
  function import_mysql($mysqli,$userid,$server,$apikey,$feed)
  { 
    $feedname = "feed_".trim($feed->id)."";
  
    $result = $mysqli->query("SELECT id FROM feeds WHERE `id` = '".$feed->id."'");
    
    if (!$result->num_rows)
    {
      // Create feed
      echo "create feed ".$feed->id."\n";
      $result = $mysqli->query("INSERT INTO feeds (id,userid,name,tag,datatype,public,engine) VALUES ('".$feed->id."','$userid','".$feed->name."','".$feed->tag."','".$feed->datatype."','false','0')");
      
      
      if ($feed->datatype==1 || $feed->datatype==2)
      {
        $result = $mysqli->query(
        "CREATE TABLE $feedname (
    time INT UNSIGNED, data float,
        INDEX ( `time` ))");
      }

      elseif ($feed->datatype==3) {										
        $result = $mysqli->query(										
        "CREATE TABLE $feedname (
    time INT UNSIGNED, data float, data2 float,
        INDEX ( `time` ))");
      }
      
    }
    else
    {
      echo "feed exists ".$feed->id."\n";
    }  
  


    // Check if we have already downloaded part of the feed and get the last 
    // value entered so that we dont download and insert data that has already 
    // been inserted this makes this utility useful for syncing in general 
    // and in particlar backup that only downloads the latest changes.
    $start = 0;  
    $feed_result = $mysqli->query("SELECT * FROM $feedname ORDER BY time Desc LIMIT 1");
    if ($feed_result)
    {
      $feed_row = $feed_result->fetch_array();
      if ($feed_row[0]) $start = $feed_row[0];
    }

    // Open the file served from the export page on the remote server
    $url = $server.'/feed/export.json?apikey='.$apikey.'&id='.$feed->id.'&start='.$start;

    echo "Opening file $url\n";
    $fh = @fopen( $url, 'r' );

    $histogram = false;

    if ($fh)
    {
      // The first line is to be updated
      if ($start!=0)
      {
        $data = fgetcsv($fh, 0, ",");
        if (isset($data[0]) && isset($data[1]) && count($data)!=3)
        {
          $feedtime = $data[0]; $value = $data[1];
          $mysqli->query("UPDATE $feedname SET `data` = '$value' WHERE `time` = '$feedtime'");
        }
        
        if (isset($data[0]) && isset($data[1]) && count($data)==3)
        {
          $feedtime = $data[0]; $value = $data[1]; $value2 = $data[2];
          $mysqli->query("UPDATE $feedname SET `data` = '$value', `data2`='$value2' WHERE `time` = '$feedtime'");
        }
      }
    
      // Read through the file
      $i = 0; $vals = "";
      while (($data = fgetcsv($fh, 0, ",")) !== FALSE) 
      {
        $feedtime = $data[0]; 
        
        if (isset($data[1])) {
          $value = $data[1];

          if (count($data)==3) $histogram = true;

          if ($feedtime!='' && $value!='')
          {
            $i++;
            //Contruct values part of the query
            if ($i!=1) $vals .= ',';

            if (!$histogram) {
              $vals .= "('$feedtime','$value')";
            } else {
              $value2 = $data[2];
              $vals .= "('$feedtime','$value','$value2')";
            }

            // Execute query every 400 rows (same block size as export script)
            if ($i>400)
            {
              $i = 0;
              if ($vals && !$histogram) $mysqli->query("INSERT INTO $feedname (`time`,`data`) VALUES ".$vals);
              if ($vals && $histogram) $mysqli->query("INSERT INTO $feedname (`time`,`data`,`data2`) VALUES ".$vals);
              $vals = "";
            }
          }
        }
      }

      // If there are lines to be inserted left over insert them here at the end
      if ($vals && !$histogram) $mysqli->query("INSERT INTO $feedname (`time`,`data`) VALUES ".$vals);
      if ($vals && $histogram) $mysqli->query("INSERT INTO $feedname (`time`,`data`,`data2`) VALUES ".$vals);
      $vals = "";
      fclose($fh);
    }
  }
  
