<?php

  $adminkey = '';

  require "timestore_class.php";
  $timestore = new Timestore($adminkey);

  $userid = 1;
  $feedid = 1;
  $interval = 10;

  error_reporting(E_ALL);
  ini_set('display_errors', 'on');

  $mysqli = new mysqli("localhost","user","pass","database");

  $feedlistq = $mysqli->query("SELECT id,name FROM feeds WHERE `userid`='$userid' AND `timestore`='0' AND `datatype`='1'");
  
  while ($row = $feedlistq->fetch_array())
  {

  $feedid = $row['id'];

  $now = time();
  $start = time()-(3600*24*365*5);

  echo $row['id']." ".$row['name']." Creating timestore node ";
  print $timestore->create_node($feedid,$interval);
  echo "ok\n";

  $benchstart = microtime(true);

  do
  {
    sleep(1);
    $result = $mysqli->query("SELECT * FROM feed_$feedid WHERE time>$start ORDER BY time Asc LIMIT 25000");
    sleep(1);
    $rows = $result->num_rows;

    if ($rows>0)
    {
      $csv = array();
      while($row = $result->fetch_array())
      {
        $csv[] = $row['time'].",".$row['data'];
        $start = $row['time'];
      }
      $csv = implode($csv,"\n");
      $timestore->post_csv($feedid,$csv,null);

      $days = round(($now - $start) / (3600*24));

      echo "Start: ".$start." ".$days." days ".($result->num_rows)."\n";
    }
  } while ($rows!=0);

  echo microtime(true)-$benchstart;

  $mysqli->query("UPDATE feeds SET `timestore`='1' WHERE `id`='$feedid'");

  }
