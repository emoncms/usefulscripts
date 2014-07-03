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

    $verbose = true;

    $starttime = 0;
    $backupsize = 0;

    $backupfeedname = "/home/username/backup/feeds/feed_$feedid.MYD";
    $primaryfeedname = "/var/lib/mysql/emoncms/feed_$feedid.MYD";

    $primarysize = filesize($primaryfeedname);
    $backupsize = 0;

    if ($primarysize<(1024*1024*100)) // 100 MiB
    {
      $trate = 0;
      echo $n." ";
      // 1) Does backup MYD exist?
      if (file_exists($backupfeedname)) {
        $backupsize = filesize($backupfeedname);
        if ($verbose) echo "E ";
      } else echo "- ";

      if ($primarysize>$backupsize)
      {
        $dnsize = $primarysize-$backupsize;
        $dnstart = microtime(true);
        echo "DN ";
        $primary = fopen($primaryfeedname, 'rb');
        $backup = fopen($backupfeedname, 'a');
        fseek($primary,$backupsize);

        $left_to_read = $dnsize;
        do
        {
          if ($left_to_read>147456) $readsize = 147456; else $readsize = $left_to_read;
          $left_to_read -= $readsize;

          $data = fread($primary,$readsize);
          fwrite($backup,$data);
          if ($dnsize>147456) sleep(2);

          echo $left_to_read."\n";
        }
        while ($left_to_read>0);
        
        fclose($backup);
        fclose($primary);

        $trate = ($dnsize / (microtime(true) - $dnstart))/1000.0;
      } else { echo "-- "; } 

      echo "feed ".$row['id'].":".$row['name'];
      if ($trate>0) echo " ".number_format($trate,0)." kB/s ($dnsize)";
      echo "\n";
    }
 }
