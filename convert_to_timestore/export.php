<?php

  error_reporting(E_ALL);
  ini_set('display_errors', 'on');

  $adminkey = '';

  require "timestore_class.php";
  $timestore = new Timestore($adminkey);

  $mysqli = new mysqli("localhost","user","pass","db");

  $userlistq = $mysqli->query("SELECT id FROM users WHERE `convert` = '1'");

  while ($row = $userlistq->fetch_array())
  {
    $userid = $row['id'];
    echo "USER: $userid\n";

  $feedlistq = $mysqli->query("SELECT id,name,`convert` FROM feeds WHERE `userid`='$userid' AND `timestore`='0' AND `datatype`='1'");

  while ($row = $feedlistq->fetch_array())
  {
    $feedid = $row['id'];
    $interval = (int) $row['convert'];
    if ($interval>0)
    {
    $now = time();
    $start = time()-(3600*24*365*5);

    echo $row['id']." ".$row['name']." Creating timestore node ";
    print $timestore->create_node($feedid,$interval);
    echo "ok\n";

    $benchstart = microtime(true);

    do
    {

      $result = $mysqli->query("SELECT * FROM feed_$feedid WHERE time>$start ORDER BY time Asc LIMIT 100000");

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

  }

  }
