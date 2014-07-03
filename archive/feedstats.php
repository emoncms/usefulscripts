<?php

$mysqli = new mysqli("localhost","user","pass","db");

$feedsresult = $mysqli->query("SELECT id,datatype FROM feeds");
while ($feedsrow = $feedsresult->fetch_array())
{
  $feedid = $feedsrow['id'];

  $result = $mysqli->query("SHOW TABLE STATUS LIKE 'feed_$feedid'");
  $row = $result->fetch_array();

  $size = ($row['Data_length'] + $row['Index_length']);
  $datapoints = ($row['Data_length'] / 9);

  if ($datapoints>0) 
  {
    $result = $mysqli->query("SELECT * FROM feed_$feedid WHERE time>2012 ORDER BY time Asc LIMIT 1");
    $row = $result->fetch_array();
    $start = $row['time'];

    $result = $mysqli->query("SELECT * FROM feed_$feedid ORDER BY time Desc LIMIT 1");
    $row = $result->fetch_array();
    $end = $row['time'];

    $totaltime = $end-$start;
    $interval = round($totaltime / $datapoints);

    if ($feedsrow['datatype']==2) $interval = 3600 * 24;
    if ($feedsrow['datatype']==3) $interval = 3600 * 24;

    //echo "Interval: $interval\n";
    $result = $mysqli->query("UPDATE feeds SET `size` = '$size', `dpinterval` = '$interval' WHERE `id` = '$feedid'");

    echo "feed_".$feedid." ".$interval."s ".$size."b\n";
  } 
}
