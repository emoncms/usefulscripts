<?php
    
    $start = microtime(true);
    require "Lib/PHPFina.php";
    require "Lib/PHPFiwa.php";
    require "Lib/PHPTimeSeries.php";
    require "Lib/PHPTimestore.php";
    
    define('EMONCMS_EXEC', 1);
    chdir("/var/www/emoncms");
    require "Modules/log/EmonLogger.php";
    require "process_settings.php";
    
    $engine = array();
    $engine[Engine::PHPFINA] = new PHPFina($feed_settings['phpfina']);
    $engine[Engine::PHPFIWA] = new PHPFiwa($feed_settings['phpfiwa']);
    $engine[Engine::PHPTIMESERIES] = new PHPTimeSeries($feed_settings['phptimeseries']);
    //=============================================================================
    // SETTINGS:
    
    $source = 3009;
    
    $source_engine = Engine::PHPFINA;   // or: Engine::PHPFINA, Engine::PHPTIMESERIES
    
    $target = 3029;                    // must be a PHPFINA feed
    
    $low_memory_mode = true;           // set this to true if you experience low memory errors
                                        // may not make any difference
    //=============================================================================
        
    echo "Power to kWh processor feed $source -> feed $target\n";
        
        
    if ($source_engine==Engine::PHPFINA) {
        echo "Deleting data for ".$feed_settings['phpfina']['datadir'].$target.".dat\n";
        unlink($feed_settings['phpfina']['datadir'].$target.".dat");
        
        echo "Creating new data file\n";
        $fh = fopen($feed_settings['phpfina']['datadir'].$target.".dat", 'wb');
        fclose($fh);
    }
    
    // Starting kWh of feed, default:0
    $kwh = 0;
    $time = 0;
    
    while ($dp = $engine[$source_engine]->readnext($source))
    {
        $last_time = $time;
        
        if (!is_nan($dp['value'])) {
        
            $time = $dp['time'];
            $power = $dp['value'];

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
            
            // print $time." ".$kwh."\n";
            //------------------------------------------------
            // 3) Save value to phpfina feed
            //------------------------------------------------
            
            // Save $kwh to feed
            // print $time." "+$kwh."\n";
            $engine[Engine::PHPFINA]->prepare($target,$time,$kwh);
            if ($low_memory_mode) $engine[Engine::PHPFINA]->save();
        }
    }
    $engine[Engine::PHPFINA]->save();
    
    print "Recalculated in ".round(microtime(true)-$start)."s\n";
