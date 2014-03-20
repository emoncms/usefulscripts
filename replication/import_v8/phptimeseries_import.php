<?php

  function import_phptimeseries($mysqli,$userid,$server,$apikey,$feed)
  {   
    $feedid = $feed->id;
    if ($feedid<1) return false;
    
    echo " Downloading feed $feedid ".$feed->name."\n";         
        
    $feedname = "/var/lib/phptimeseries/feed_$feedid.MYD";
    
    if (file_exists($feedname)) {
      $downloadfrom = filesize($feedname); 

      if (intval($downloadfrom/9.0)!=($downloadfrom/9.0)) { echo "PHPTimeSeries feed $feedid corrupt\n"; die;}
    } else { 
      $downloadfrom = 0;
    }

    $url = $server."/feed/export.json?apikey=$apikey&id=$feedid&start=$downloadfrom";
    
    $primary = @fopen( $url, 'r' );
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
        
    echo $dnsize." bytes\n";
  }
  
