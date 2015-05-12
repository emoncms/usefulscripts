<?php

	define('EMONCMS_EXEC', 1);
	print "MySQL to phptimestore conversion script\n";
	
	//used for after migration chown uid:gid of the output file, set to desired value
	$uid = "root";
	$gid = "root";
	
	$emoncms_dir = "/var/www/emoncms";
	$timeseries_dir = "/var/lib/phptimeseries/";
	
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
	$mysqli = @new mysqli($server,$username,$password,$database);
		
	require "PHPTimeSeries.php";
	$engine = new PHPTimeSeries(array('datadir'=>$timeseries_dir));
	
	$result = $mysqli->query("SELECT * FROM feeds WHERE `engine`= 0");
	print "There are ".$result->num_rows." feeds to convert, would you like to continue?(y/n) ";
	$handle = fopen ("php://stdin","r");
	$line = fgets($handle);
	if(trim($line) != 'y'){
		exit;
	}
	print "\n";
	
	print "Do you want to create a CSV backup in your PHPTimeSeries folder?(y/n) ";
	$handle = fopen ("php://stdin","r");
	$do_backup = fgets($handle);
	print "\n";
	
	
	while($row = $result->fetch_array())
	{
		print "userid:".$row['userid']." feed:".$row['id']." name:".$row['name']."\n";
		
		$id = $row['id'];
		$current_feed_value = $row['value'];
		
		$engineresult = $engine->create($id,0);
		
		if ($engineresult == true) {
			
			if (trim($do_backup) == 'y') {
				$backupfile = fopen("{$timeseries_dir}feed_{$id}backup.csv", "w") or die("Unable to open backup file!");
			
				fwrite($backupfile, "timestamp;data\n");
				
			}
			
			
			$data = $mysqli->query("SELECT * FROM feed_{$id}");
		
			while($row = $data->fetch_array()) {
				//print "timestamp:".$row['time']." data:".$row['data']."\n";
				$postresult = $engine->post($id,$row['time'],$row['data']);
				if (trim($do_backup) == 'y') {fwrite($backupfile, "{$row['time']};{$row['data']}\n");}
				//print $postresult."\n";
			}
			
			if (trim($do_backup) == 'y') {fclose($backupfile);}
			
			$mysqli->query("UPDATE feeds SET `engine`=2 WHERE `id`='$id'");
			$mysqli->query("UPDATE feeds SET `value`=$current_feed_value WHERE `id`='$id'");

			exec("chown {$uid}:{$gid} {$timeseries_dir}feed_{$id}.MYD");
			
			if (trim($do_backup) == 'y') {exec("chown {$uid}:{$gid} {$timeseries_dir}feed_{$id}backup.csv");}
			
			print "Feed $id is now PHPTimeseries\n";

			
  
		}
		else {
			print("PHPTimeSeries file create failed for feedid=$id");
		}
	
		
	}
	
	
?>
