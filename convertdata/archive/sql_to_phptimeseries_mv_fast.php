<?php

    define('EMONCMS_EXEC', 1);
    print "MySQL to phptimestore conversion script\n";
    
    //used for after migration chown uid:gid of the output file, set to desired value
    $uid = "root";
    $gid = "root";
    
    $emoncms_dir = "/home/emoncms/sites/emondev.gtronica.com";
    //$timeseries_dir = "/var/lib/phptimeseries/";
    $timeseries_dir = "/home/emoncms/data/phptimeseries/";
    
    if (!file_exists($emoncms_dir)) {
        print "Could not open ".$emoncms_dir."\n";
        die;
    }
    
    if (!file_exists($timeseries_dir)) {
        print "Could not open ".$timeseries_dir."\n";
        die;
    }
    
    chdir($emoncms_dir);
    require "process_settings.php";
    $mysqli = @new mysqli(
        $settings["sql"]["server"],
        $settings["sql"]["username"],
        $settings["sql"]["password"],
        $settings["sql"]["database"],
        $settings["sql"]["port"]
    );

        
    include "lib/PHPTimeSeries.php";
    $engine = new PHPTimeSeries(array('datadir'=>$timeseries_dir));
    
    $result = $mysqli->query("SELECT * FROM feeds WHERE `engine`= 0");
    print "There are ".$result->num_rows." feeds to convert\n";
    
    print "Do you want to create a CSV backup in your PHPTimeSeries folder?(y/n) ";
    $handle = fopen ("php://stdin","r");
    $do_backup = (trim(fgets($handle)) == "y" ? true : false);
    print "\n";
    
    $fast_engine= true;
    
    while($rowfeed = $result->fetch_array())
    {
        print "userid:".$rowfeed['userid']." feed:".$rowfeed['id']." name:".$rowfeed['name']."\n";
        
        $id = $rowfeed['id'];
        $current_feed_value = $rowfeed['value'];
        
        $engineresult = $engine->create($id,0);
        
        if ($engineresult == true) {
            
            $data = $mysqli->query("SELECT * FROM feed_{$id} order by time", MYSQLI_USE_RESULT);
            if ($data) {
                print "### Feed $id converting...\n";
                if ($do_backup) {
                    $backupfile = fopen("{$timeseries_dir}feed_{$id}backup.csv", "w") or die("Unable to open backup file!");
                    fwrite($backupfile, "timestamp;data\n");
                }
        
                if ($fast_engine) {
                    $fh = fopen($timeseries_dir."feed_$id.MYD", 'w');
                    if (!$fh) {
                        print("Could not open data file");
                        exit;
                    }
                }
               
                $last_time = 0;
                while ($row = $data->fetch_assoc()) {
                    //print "timestamp:".$row['time']." data:".$row['data']."\n";
                    if ($fast_engine) {
                        $time = (int) $row['time'];
                        if ($time == $last_time)  {
                            print "\nRepeated time: $time " . date('Y-m-d H:m:s', $time);
                            $last_time = $time;
                            continue;
                        }
                        $last_time = $time;
                        $postresult = fwrite($fh, pack("CIf",249,$time,(float) $row['data']));
                    } else {
                        $postresult = $engine->post($id,$row['time'],$row['data']);
                    }
                    
                    if ($postresult == false) print "Error at ".$row['time']."\n";
                    else if ($row['time'] % 86400 == 0) print ".";
                    if ($do_backup) {fwrite($backupfile, "{$row['time']};{$row['data']}\n");}
                }
                
                if ($do_backup) {fclose($backupfile);}
                
                $mysqli->query("UPDATE feeds SET `engine`=2 WHERE `id`='$id'");
                //$mysqli->query("UPDATE feeds SET `value`=$current_feed_value WHERE `id`='$id'");

                //exec("chown {$uid}:{$gid} {$timeseries_dir}feed_{$id}.MYD");
                //if ($do_backup) {exec("chown {$uid}:{$gid} {$timeseries_dir}feed_{$id}backup.csv");}
                
                print "Feed $id is now PHPTimeseries\n";
            
            } else {
                print "Feed $id no data\n";
            }


        }
        else {
            print("PHPTimeSeries file create failed for feedid=$id");
        }
    
        
    }
    
    
?>

