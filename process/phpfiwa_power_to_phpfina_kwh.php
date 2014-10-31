<?php

    
    define('EMONCMS_EXEC', 1);
    chdir("/var/www/emoncms");
    require "Modules/log/EmonLogger.php";
    require "process_settings.php";
    require "Modules/feed/engine/PHPFiwa.php";
    require "Modules/feed/engine/PHPFina.php";
    
    $phpfiwadir = $feed_settings['phpfiwa']['datadir'];
    $phpfiwa = new PHPFiwa(array('datadir'=>$phpfiwadir));
    
    $phpfinadir = $feed_settings['phpfina']['datadir'];
    $phpfina = new PHPFina(array('datadir'=>$phpfinadir));
    
    $source = 45852;
    $target = 50479;
    
    // Starting kWh of feed, default:0
    $kwh = 0;
    
    $meta = $phpfiwa->get_meta($source);
    
    $fh = fopen($phpfiwadir.$source."_0.dat", 'rb');
    $filesize = filesize($phpfiwadir.$source."_0.dat");
    $npoints = floor($filesize / 4.0);

    $time = 0;
    
    for ($i=0; $i<$npoints; $i++)
    {
        //------------------------------------------------
        // 1) Read in datapoint from PHPFina feed
        //------------------------------------------------
   
        $val = unpack("f",fread($fh,4));

        $last_time = $time;
        
        if (!is_nan($val[1])) {
        
            $time = $meta->start_time + $i * $meta->interval[0];
            $power = $val[1];

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
            $phpfina->post($target,$time,$kwh);
        }
    }
