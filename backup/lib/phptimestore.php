<?php

  function import_phptimestore($id,$server,$apikey,$datadir)
  {     
    echo "PHPTIMESTORE: $id\n";
    // Get the timestore meta deta for the remote feed: start time, interval and number of rows
    $result = file_get_contents($server."/feed/getmeta.json?apikey=$apikey&id=$id");
    $remote_meta = json_decode($result);
    if (!isset($remote_meta->start) || !isset($remote_meta->interval))
    {
        echo "Error in fetching remote meta data, received: $result\n";
        return false;
    }
        
    // Check if there is a local timestore feed (REPEAT OF ABOVE)
    $feedname = $datadir.str_pad($id, 16, '0', STR_PAD_LEFT).".tsdb";
    
    if (file_exists($feedname))
    {
        $local_meta = new stdClass();
        if (!$metafile = @fopen($feedname, 'rb')) {
            echo "Cannot open local meta data file\n";
            return false;
        }
        fseek($metafile,8);
        $d = fread($metafile,8);
        $tmp = unpack("h*",$d);
        $local_meta->id = (int) strrev($tmp[1]);
        $tmp = unpack("I",fread($metafile,4));
        $local_meta->nmetrics = $tmp[1];
        $tmp = unpack("I",fread($metafile,4));
        $legacy_npoints = $tmp[1];
        $tmp = unpack("I",fread($metafile,8));
        $local_meta->start = $tmp[1];
        $tmp = unpack("I",fread($metafile,4));
        $local_meta->interval = $tmp[1];
        fclose($metafile);
    }
    else
    {
        $local_meta = $remote_meta;
        
        if (!$metafile = @fopen($feedname, 'wb')) {
            echo "Cannot open local meta data file\n";
            return false;
        }
        fwrite($metafile,pack("I",0));
        fwrite($metafile,pack("I",0));
        fwrite($metafile,pack("h*",strrev(str_pad($id, 16, '0', STR_PAD_LEFT))));
        fwrite($metafile,pack("I",$local_meta->nmetrics));
        fwrite($metafile,pack("I",0));                  // Legacy
        fwrite($metafile,pack("I",$local_meta->start));
        fwrite($metafile,pack("I",0));
        fwrite($metafile,pack("I",$local_meta->interval));
        fclose($metafile);
    }
    
    // We now check if the local meta data is the same as the remote meta data.
    // Given that the starttime, the interval and the feedname is the same we assume
    // that we are dealing with the same feed
    if ($local_meta->start == $remote_meta->start && $local_meta->interval == $remote_meta->interval)
    {   
      // Download all 6 timestore layers
      for ($layer=0; $layer<6; $layer++)
      {
        
        echo "--layer: $layer ";
        $feedname = $datadir.str_pad($id, 16, '0', STR_PAD_LEFT)."_".$layer."_.dat";

        if (file_exists($feedname)) {
          $downloadfrom = filesize($feedname); 

          if (intval($downloadfrom/4.0)!=($downloadfrom/4.0)) { 
            echo "Timestore feed ".$id." corrupt\n"; 
            die; 
          }
        } else { 
          $downloadfrom = 0;
        }

        $url = $server."/feed/export.json?apikey=$apikey&id=".$id."&layer=$layer&start=$downloadfrom";
        if (!$primary = @fopen( $url, 'r' )) {
            echo "Failed to access remote server\n";
            return false;
        }

        if ($downloadfrom>=4) {
          // update last datapoint
          $firstdp = fread($primary,4);
          
          if (!$backup = @fopen($feedname, 'c')) {
             echo "Cannot open local data file\n";
             return false;
          }
          fseek($backup,$downloadfrom-4);
          fwrite($backup,$firstdp);
          fclose($backup);
        }
        
        if (!$backup = @fopen($feedname, 'a')) {
          echo "Cannot open local data file\n";
          return false;
        }
        
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
      
      $feedname = $datadir.str_pad($id, 16, '0', STR_PAD_LEFT)."_0_.dat";
      $local_meta->npoints = floor(filesize($feedname) / 4.0);
      if (!$metafile = @fopen($datadir.str_pad($id, 16, '0', STR_PAD_LEFT).".npoints", 'wb')) {
          echo "Cannot open local meta data file\n";
          return false;
      }
      fwrite($metafile,pack("I",$local_meta->npoints));
      fclose($metafile);
    }
    else
    {
      echo "local and remote meta data do not match\n";
    }
  }
