<?php

    
    define('EMONCMS_EXEC', 1);
    chdir("/var/www/emoncms");
    require "Modules/log/EmonLogger.php";
    //----------------------------------------------------
    
    // CONVERT: 

        // Directory of phptimeseries feeds, see: settings.php
        $dir = "/var/lib/phptimeseries/";
        // Feed id to read: 
        $feedid = 1;
    
    // TO:
        
        // Feed id to write to: 
        $target_feedid = 2; 
        
        // Using the PHPFina class here to save writting the post function directly
        // it will be quite a bit slower than a version written to keep the feed open
        // while making the conversion - which would be an approach worth looking at.
        require "Modules/feed/engine/PHPFina.php";
        $phpfina = new PHPFina(array('datadir'=>"/var/lib/phpfina/"));
    
    // Starting kWh of feed, default:0
    $kwh = 0;
    
    //----------------------------------------------------
    
    $fh = fopen($dir."feed_$feedid.MYD", 'rb');
    $filesize = filesize($dir."feed_$feedid.MYD");
    
    $npoints = floor($filesize / 9.0);
    $time = 0;
    
    for ($i=0; $i<$npoints; $i++)
    {
        //------------------------------------------------
        // 1) Read in datapoint from PHPTimeSeries feed
        //------------------------------------------------
    
        // Read next datapoint
        $d = fread($fh,9);

        // Itime = unsigned integer (I) assign to 'time'
        // fvalue = float (f) assign to 'value'
        $array = unpack("x/Itime/fvalue",$d);

        $last_time = $time;
        $time = $array['time'];
        $power = $array['value'];   // power in Watts

        //------------------------------------------------
        // 2) Calculate increase in kWh and next total kwh value
        //------------------------------------------------
           
        // only update if last datapoint was less than 2 hour old
        // this is to reduce the effect of monitor down time on creating
        // often large kwh readings.
        if ($last_time && ($time-$last_time)<7200)
        {
            // kWh calculation
            $time_elapsed = ($time - $last_time);
            $kwh_inc = ($time_elapsed * $power) / 3600000.0;
            $kwh += $kwh_inc;
        } else {
            // in the event that redis is flushed the last time will
            // likely be > 7200s ago and so kwh inc is not calculated
            // rather than enter 0 we enter the last value
            $kwh = $kwh;
        }
        //print $time." ".$kwh."\n";
        //------------------------------------------------
        // 3) Save value to phpfina feed
        //------------------------------------------------
        
        // Save $kwh to feed
        // print $time." "+$kwh."\n";
        $phpfina->post($target_feedid,$time,$kwh);
    }
