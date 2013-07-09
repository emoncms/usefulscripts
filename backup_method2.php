<?php

  // Emoncms feed data backup - method 2
  // from primary disc to 2nd disc connected to same machine
  // incremental - only updates changes
  // relies upon sorted data with inserts at the end of the file
  // will not update changes made in parts of feeds already downloaded.

  // Licence: GPL

  $username = "";
  $password = "";
  $server   = "localhost";
  $database = "";

  $mysqli = @new mysqli($server,$username,$password,$database);

  $result = $mysqli->query("SELECT id,name FROM feeds WHERE `datatype` = 1 ORDER BY id Asc");
  $n = 0;

  while ($row = $result->fetch_array())
  {
    $n++;
    $feedid = $row['id'];
    $starttime = 0;
    $backupsize = 0;

    $backupfeedname = "/backup/feeds/feed_$feedid.MYD";
    $primaryfeedname = "/var/lib/mysql/emoncms/feed_$feedid.MYD";

    $primarysize = filesize($primaryfeedname);
    $backupsize = 0;

    $transfer_rate = 0;
    echo $n." ";
    // 1) Does backup MYD exist?
    if (file_exists($backupfeedname)) {
      $backupsize = filesize($backupfeedname);
      echo "E ";       // E for exists
    } else echo "- ";  // - does not exist

    if ($primarysize>$backupsize)
    {
      $dnsize = $primarysize-$backupsize;
      $dnstart = microtime(true);
      echo "DN ";
      $primary = fopen($primaryfeedname, 'rb');
      $backup = fopen($backupfeedname, 'a');

      fseek($primary,$backupsize);

      // manually set transfer rate
      // 9*32 = number of bytes read each time
      // usleep(1500); delay microseconds between each read
      for ($i=$backupsize; $i<$primarysize; $i+=(9*32))
      {
        fwrite($backup,fread($primary,(9*32)));
        usleep(1500);
      }
      
      fclose($backup);
      fclose($primary);

      $transfer_rate = ($dnsize / (microtime(true) - $dnstart))/1000.0;

    } else { echo "-- "; } 

    echo "feed $feedid:".$row['name'];
    if ($transfer_rate>0) echo " ".number_format($transfer_rate,0)." kB/s";
    echo "\n";
 
  }


