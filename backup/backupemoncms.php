<?php
/*

  Automatically download all feeds from a remote emoncms server
  Developed for backing up emoncms.org producing an exact copy replication
  Requires that users and feeds table have already been downloaded.

  All Emoncms code is released under the GNU Affero General Public License.
  See COPYRIGHT.txt and LICENSE.txt.

  ---------------------------------------------------------------------
  Emoncms - open source energy visualisation
  Part of the OpenEnergyMonitor project:
  http://openenergymonitor.org

  Run this as a cron job hourly:

  0 * * * * php /var/www/emoncms/backupemoncms.php

  ADJUST Number of feeds to download each run to match feed number on your system

  640 feeds per run x 24 runs a day = 15360 feeds synced a day

  For this script to run, you need to first copy the users and feeds table from the master server to the backup server using the more common backup procedure of using mysqldump and scp:

  1) MysqlDump users and feeds tables from master server
  mysqldump -u root -p --single-transaction emoncms feeds > feeds.sql
  mysqldump -u root -p --single-transaction emoncms users > users.sql

  2) Log into backup machine
  ssh username@ipaddress

  3) Enter mysql terminal
  mysql -u username -p -A emoncms

  4) Drop existing users and feeds tables:
  DROP TABLE feeds;
  DROP TABLE users;
  exit;

  5) Copy over feeds and users table export from master server
  scp username@ipaddress:users.sql /home/username
  scp username@ipaddress:feeds.sql /home/username

  6) Import users and feeds table into backup mysql database
  mysql -u username -p emoncms < users.sql
  mysql -u username -p emoncms < feeds.sql
  
*/

set_time_limit (10000);

// Ensure only one instance of the script can run at any one time.
$fp = fopen("importlock", "w");
if (! flock($fp, LOCK_EX | LOCK_NB)) { echo "Already running\n"; die; }

// Connect to the database
define('EMONCMS_EXEC', 1);

require "process_settings.php";
$mysqli = new mysqli($server,$username,$password,$database);

// Fetch the import queue
$result = $mysqli->query("SELECT * FROM importqueue ORDER BY `queid` Desc LIMIT 640");

// If there are no rows then re fill import queue
if ($result->num_rows==0) {
  echo "No rows in import queue, CREATING NEW IMPORT QUEUE";

  $result = $mysqli->query("SELECT id, userid FROM feeds ORDER BY id ASC");

  echo "Feeds to process: ".$result->num_rows."\n";
  while ($row = $result->fetch_array())
  {
    // 1) check if feed data table already exists
    $feedid = $row['id'];
    $userid = $row['userid'];

    $user_result = $mysqli->query("SELECT apikey_write FROM users WHERE id='$userid'");
    $user_row = $user_result->fetch_array();

    $remotekey = $user_row['apikey_write'];

    $mysqli->query("INSERT INTO importqueue (`userid`,`remoteurl`,`remotekey`,`remotefeedid`,`localfeedid`) VALUES ('$userid','','$remotekey','$feedid','$feedid')");

  }

  // now that we have refilled the importqueue, select 600 rows for this sync run
  $result = $mysqli->query("SELECT * FROM importqueue ORDER BY `queid` Desc LIMIT 640");
} 

$starttime = time();

while ($row = $result->fetch_array())
{
    $queid = $row['queid'];

  $feedid = $row['localfeedid'];
  $userid = $row['userid'];
  $feedname = "feed_".$feedid;
  echo "Downloading $feedname\n";

  // Get datatype
  $result_datatype = $mysqli->query("SELECT datatype FROM feeds WHERE `id`='$feedid'");
  $datatype_row = $result_datatype->fetch_array();
  $datatype = $datatype_row['datatype'];

  echo "Datatype is: ".$datatype."\n";

  // check if feed data table already exists
  $resultB = $mysqli->query("SHOW TABLES LIKE '$feedname'");

  if (!$resultB->num_rows)
  {
    if ($datatype!=3) {
      echo "New feed created\n";										
      $mysqli->query("CREATE TABLE $feedname (time INT UNSIGNED, data float,INDEX ( `time` ))");
    }

    if ($datatype==3) {	
      echo "Histogram feed created\n";									
      $mysqli->query(										
      "CREATE TABLE $feedname (time INT UNSIGNED, data float, data2 float,INDEX ( `time` ))");
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
  $url = 'http://emoncms.org/feed/export.json?apikey='.$row['remotekey'].'&id='
  .$feedid.'&start='.$start;

  echo "Opening file $url\n";
  $fh = @fopen( $url, 'r' );

  $histogram = false;

  if ($fh)
  {
    // Read through the file
    $i = 0; $vals = "";
    while (($data = fgetcsv($fh, 0, ",")) !== FALSE) 
    {
      $feedtime = $data[0]; 
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
          if (isset($data[2])){
          $value2 = $data[2];
          $vals .= "('$feedtime','$value','$value2')";}
        }

        // Execute query every 400 rows (same block size as export script)
        if ($i>400)
        {
          $i = 0;
          if ($vals && !$histogram) $mysqli->query("INSERT INTO $feedname (`time`,`data`) VALUES ".$vals);
          if ($vals && $histogram) $mysqli->query("INSERT INTO $feedname (`time`,`data`,`data2`) VALUES ".$vals);
          $vals = "";
        }
      }
    }

    // If there are lines to be inserted left over insert them here at the end
    if ($vals && !$histogram) $mysqli->query("INSERT INTO $feedname (`time`,`data`) VALUES ".$vals);
    if ($vals && $histogram) $mysqli->query("INSERT INTO $feedname (`time`,`data`,`data2`) VALUES ".$vals);
    $vals = "";
    fclose($fh);
  }

  echo "Transfer complete\n";
  echo "Deleting item $queid from queue\n";
  $mysqli->query("DELETE FROM importqueue WHERE queid = $queid");

  //if ((time()-$starttime)>120) die;
}
