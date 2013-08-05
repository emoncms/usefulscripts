<?php

$mysqli = new mysqli("localhost","user","pass","db");

$userq = $mysqli->query("SELECT id FROM users");
while ($user = $userq->fetch_array())
{
  $userid = $user['id'];
  $feedq = $mysqli->query("SELECT size FROM feeds WHERE `userid` = '$userid'");
 
  $total = 0;

  while ($feed = $feedq->fetch_array())
  {
    $total += $feed['size'];
  }

  $result = $mysqli->query("UPDATE users SET `diskuse` = '$total' WHERE `id` = '$userid'");
  echo "user_".$userid." ".$total."\n";
}
