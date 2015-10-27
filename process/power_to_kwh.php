<?php
    $low_memory_mode = false;
    define('EMONCMS_EXEC', 1);

    require "Lib/PHPFina.php";
    require "Lib/PHPFiwa.php";
    require "Lib/PHPTimeSeries.php";
    require "Lib/EmonLogger.php";
    
    echo "------------------------------------------------------\n";
    echo "Power to kWh feed processor\n";
    echo "------------------------------------------------------\n";

    // Select emoncms directory
    echo "\n";
    $setup = stdin("Is your setup a standard emonpi or emonbase? (Y/N): ");

    if ($setup=="y" || $setup=="Y") {
        $emoncmsdir = "/var/www/emoncms";
    } else {
        $emoncmsdir = stdin("Please enter root directory of your emoncms installation (i.e /var/www/emoncms): ");
    }

    chdir($emoncmsdir);

    if (!file_exists("process_settings.php")) {
        echo "ERROR: This is not a valid emoncms directory, please retry\n"; die;
    }

    // Load emoncms install
    require "process_settings.php";
    
    $mysqli = @new mysqli($server,$username,$password,$database);

    if (class_exists('Redis') && $redis_enabled) {
        $redis = new Redis();
        $connected = $redis->connect("127.0.0.1");
        if (!$connected) {
            echo "Can't connect to redis database, it may be that redis-server is not installed or started see readme for redis installation"; die;
        }
    } else {
        $redis = false;
    }

    $engine = array();
    $engine[Engine::PHPFINA] = new PHPFina($feed_settings['phpfina']);
    $engine[Engine::PHPFIWA] = new PHPFiwa($feed_settings['phpfiwa']);
    $engine[Engine::PHPTIMESERIES] = new PHPTimeSeries($feed_settings['phptimeseries']);

    // Power feed selection
    echo "\n";
    $source = (int) stdin("Please enter feedid of the power feed you wish to use to generate the accumulating kWh feed: ");

    $result = $mysqli->query("SELECT * FROM feeds WHERE id='".$source."'");
    if (!$result->num_rows) {
        echo "ERROR: Power feed does not exist\n"; die;
    }
    $row = $result->fetch_array();

    $enginename = "";
    if ($row["engine"]==5) $enginename = "PHPFINA";
    echo "Power feed selected: ".$row['name']." Engine: ".$enginename."\n";

    $userid = $row["userid"];
    $source_engine = $row["engine"];
    
    $interval = 10;
    if ($row["engine"]==Engine::PHPFINA) $sourcemeta = $engine[Engine::PHPFINA]->get_meta($source);
    if ($row["engine"]==Engine::PHPFIWA) $sourcemeta = $engine[Engine::PHPFIWA]->get_meta($source);
    if ($row["engine"]==Engine::PHPTIMESERIES) {
        $sourcemeta = new stdClass();
        $sourcemeta->interval = 60;
    }
    
    // Create new feed or overwrite existing
    echo "\n";
    $new_or_overwrite = (int) stdin("Would you like to create a new feed or overwrite an existing feed? enter 1 for new, 2 for overwrite: ");
    
    $target = 0;
    
    if ($new_or_overwrite==1) {   
        $new_kwh_feed = $row['name']." kwh";

        $datatype = DataType::REALTIME;
        $setengine = Engine::PHPFINA;
        $result = $mysqli->query("INSERT INTO feeds (userid,name,datatype,public,engine) VALUES ('$userid','$new_kwh_feed','$datatype',false,'$setengine')");
        $target = $mysqli->insert_id;
    }
    
    if ($new_or_overwrite==2) {
        $target = (int) stdin("Please enter feedid of the accumulating kwh feed you wish to overwrite: ");
        if ($target==$source) {
            echo "ERROR: kWh feedid must be different from the power feedid\n"; die;
        }
        $result = $mysqli->query("SELECT * FROM feeds WHERE id='".$target."'");
        if (!$result->num_rows) {
            echo "ERROR: kWh feed does not exist\n"; die;
        }
        
        $row = $result->fetch_array();
        if ($row["engine"]!=5) {
            echo "ERROR: kWh feed must be a PHPFINA feed\n"; die;
        }
        echo "Power feed selected: ".$row['name']." Engine: PHPFINA\n";
    }

    // force a reload of the feeds table
    if ($redis && $redis->exists("user:feeds:$userid")) {
        $redis->del("user:feeds:$userid");
        $redis->del("feed:lastvalue:$target");
    }
        
    if ($target==0) die;

    $start = microtime(true);
  
    //=============================================================================
    echo "\n";
    echo "Power to kWh processor feed $source -> feed $target\n";
    
    if ($source_engine==5) $interval = $sourcemeta->interval;
    if ($source_engine==6) $interval = $sourcemeta->interval[0];
    echo "Output interval: ".$interval."s\n";
    
    echo "Would you like to modify the kwh feed interval to be longer than the interval above?\n";
    echo "This will reduce disk space use\n";
    
    $modinterval = (int) stdin(":");
    
    if ($modinterval>$interval) {
        if ($modinterval%10!=0) {
            echo "ERROR: interval given needs to be a multiple of 10, using source interval\n";
        } else {
            $interval = $modinterval;
        }
    }
    
    echo "\n Would you like to remove erroneous high power spikes?\n"; 
    $maxpowerlevel = (int)stdin("set max power level to accept here:");
    
    $engine[Engine::PHPFINA]->delete($target);
    $engine[Engine::PHPFINA]->create($target,array("interval"=>$interval));
    
    // Starting kWh of feed, default:0
    $kwh = 0;
    $time = 0;
    
    $ptime = time();
    
    while ($dp = $engine[$source_engine]->readnext($source))
    {
        if ((time()-$ptime)>1.0) {
            $ptime = time();
            print ".";
        }
        
        if (!is_nan($dp['value'])) {
            
            $power = $dp['value'];
            
            if ($power<$maxpowerlevel) {

                $last_time = $time;
                $time = $dp['time'];
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
    }
    $engine[Engine::PHPFINA]->save();
    
    print "Recalculated in ".round(microtime(true)-$start)."s\n";
    
    exec("chown www-data:www-data ".$feed_settings['phpfina']['datadir'].$target.".meta");
    exec("chown www-data:www-data ".$feed_settings['phpfina']['datadir'].$target.".dat");
    
    // force a reload of the feeds table
    if ($redis && $redis->exists("user:feeds:$userid")) {
        $redis->del("user:feeds:$userid");
        $redis->del("feed:lastvalue:$target");
    }
    


    function stdin($prompt = null){
        if($prompt){
            echo $prompt;
        }
        $fp = fopen("php://stdin","r");
        $line = rtrim(fgets($fp, 1024));
        return $line;
    }
