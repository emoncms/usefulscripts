<?php

  function import_timestore($mysqli,$userid,$server,$apikey,$feed)
  {     
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

        if (file_exists($feedname)) {
          $downloadfrom = filesize($feedname); 

          if (intval($downloadfrom/4.0)!=($downloadfrom/4.0)) { echo "Timestore feed ".$feed->id." corrupt\n"; die; }
        } else { 
          $downloadfrom = 0;
        }

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
