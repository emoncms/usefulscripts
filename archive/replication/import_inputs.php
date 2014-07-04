<?php 
        
  $userid = 1;
  $mysqli = @new mysqli("localhost","root","password","emoncms");

  // Example backing up from a raspberrypi based on the local network
  $server = "http://192.168.1.44/emoncms";
  $apikey = "remote apikey";
  $inputs = file_get_contents($server."/input/list.json?apikey=$apikey");
  $inputs = json_decode($inputs);
  
  foreach ($inputs as $input)
  {
    echo json_encode($input)."\n";
    $mysqli->query("DELETE FROM input WHERE `id` = '".$input->id."'");
    $mysqli->query("INSERT INTO input (`id`,`userid`,`name`,`processList`,`time`,`value`,`nodeid`,`description`) VALUES ('".$input->id."','".$userid."','".$input->name."','".$input->processList."','".$input->time."','".$input->value."','".$input->nodeid."','".$input->description."')");

  }
  
