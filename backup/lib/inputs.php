<?php

function backup_inputs($mysqli,$remote_server,$remote_apikey,$userid)
{
  $inputs = file_get_contents($remote_server."/input/list.json?apikey=$remote_apikey");
  $inputs = json_decode($inputs);
  
  foreach ($inputs as $input)
  {
    echo json_encode($input)."\n";
    $mysqli->query("DELETE FROM input WHERE `id` = '".$input->id."'");
    $mysqli->query("INSERT INTO input (`id`,`userid`,`name`,`processList`,`time`,`value`,`nodeid`,`description`) VALUES ('".$input->id."','".$userid."','".$input->name."','".$input->processList."','".$input->time."','".$input->value."','".$input->nodeid."','".$input->description."')");

  }
}
