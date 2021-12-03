<?php

  //--------------------------------------------------------------------------------------------------
  // IMPORT MYSQL
  //--------------------------------------------------------------------------------------------------
  function import_mysql($feed,$server,$apikey,$mysqli)
  {
    echo "MYSQL: ".$feed->id."\n";
    
    $feedname = "feed_".trim($feed->id)."";

    $result = $mysqli->query("SELECT id FROM feeds WHERE `id` = '".$feed->id."'");

    if (!$result->num_rows)
    {
      // Create feed
      echo "create feed ".$feed->id."\n";
      //$result = $mysqli->query("INSERT INTO feeds (id,userid,name,tag,datatype,public,engine) VALUES ('".$feed->id."','$userid','".$feed->name."','".$feed->tag."','".$feed->datatype."','false','0')");
      
      if (!isset($feed->datatype)) $feed->datatype = 1;

      if ($feed->datatype==1 || $feed->datatype==2)
      {
        $result = $mysqli->query(
        "CREATE TABLE $feedname (
    time INT UNSIGNED, data float,
        INDEX ( `time` ))");
      }

      elseif ($feed->datatype==3) {
        $result = $mysqli->query(
        "CREATE TABLE $feedname (
    time INT UNSIGNED, data float, data2 float,
        INDEX ( `time` ))");
      }
    }
    else
    {
      $feedid = $feed->id;
      $result = $mysqli->query("SHOW TABLES LIKE 'feed_$feedid'");

      if (!$result->num_rows)
      {
        echo "Creating data table feed:$feedid\n";

        if ($feed->datatype==1 || $feed->datatype==2)
        {
          $result = $mysqli->query("CREATE TABLE $feedname (time INT UNSIGNED, data float,INDEX ( `time` ))");
        }

        elseif ($feed->datatype==3) {
          $result = $mysqli->query("CREATE TABLE $feedname (time INT UNSIGNED, data float, data2 float,INDEX ( `time` ))");
        }

      }

    }

    // Check if we have already downloaded part of the feed and get the last
    // value entered so that we dont download and insert data that has already
    // been inserted this makes this utility useful for syncing in general
    // and in particlar backup that only downloads the latest changes.
    $start = 0;
    $feed_result = $mysqli->query("SELECT * FROM $feedname ORDER BY time Desc LIMIT 1");
    if ($feed_result)
    {
      $feed_row = $feed_result->fetch_array();
      if ($feed_row[0]) $start = $feed_row[0];
    }

    // Open the file served from the export page on the remote server
    $url = $server.'/feed/export.json?apikey='.$apikey.'&id='.$feed->id.'&start='.$start;

    // echo "Opening file $url\n";
    $fh = @fopen( $url, 'r' );

    $histogram = false;

    if ($fh)
    {
      // The first line is to be updated
      if ($start!=0)
      {
        $data = fgetcsv($fh, 0, ",");
        if (isset($data[0]) && isset($data[1]) && count($data)!=3)
        {
          $feedtime = $data[0]; $value = $data[1];
          $mysqli->query("UPDATE $feedname SET `data` = '$value' WHERE `time` = '$feedtime'");
        }
        
        if (isset($data[0]) && isset($data[1]) && count($data)==3)
        {
          $feedtime = $data[0]; $value = $data[1]; $value2 = $data[2];
          $mysqli->query("UPDATE $feedname SET `data` = '$value', `data2`='$value2' WHERE `time` = '$feedtime'");
        }
      }
    
      $lines = 0;
      // Read through the file
      $i = 0; $vals = "";
      while (($data = fgetcsv($fh, 0, ",")) !== FALSE) 
      {
        $feedtime = $data[0]; 
        
        if (isset($data[1])) {
          $value = $data[1];

          if (count($data)==3) $histogram = true;

          if ($feedtime!='' && $value!='')
          {
            $i++;
            //Contruct values part of the query
            if ($i!=1) $vals .= ',';

            if (!$histogram) {
              $vals .= "('$feedtime','$value')";
            } else {
              $value2 = $data[2];
              $vals .= "('$feedtime','$value','$value2')";
            }

            // Execute query every 400 rows (same block size as export script)
            if ($i>400)
            {
              $i = 0;
              if ($vals && !$histogram) $mysqli->query("INSERT INTO $feedname (`time`,`data`) VALUES ".$vals);
              if ($vals && $histogram) $mysqli->query("INSERT INTO $feedname (`time`,`data`,`data2`) VALUES ".$vals);
              $vals = "";
              $lines++;
            }
          }
        }
      }

      // If there are lines to be inserted left over insert them here at the end
      if ($vals && !$histogram) $mysqli->query("INSERT INTO $feedname (`time`,`data`) VALUES ".$vals);
      if ($vals && $histogram) $mysqli->query("INSERT INTO $feedname (`time`,`data`,`data2`) VALUES ".$vals);
      $vals = "";
      fclose($fh);
    }
    
    echo "--lines: ".$lines."\n";
  }
  
