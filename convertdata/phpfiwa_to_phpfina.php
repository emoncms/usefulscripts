<?php

/*

PHPTimestore stores its bottom layer in the same format as phpfina so all we need to do is copy the bottom layer and fetch the start_time and interval of the phptimestore feed in order to create the phpfina feed.

*/

// 1) Select PHPTimestore feeds from mysql

// 2) foreach feed:

    // run copy function on layer 0 
    // fetch meta data and build phpfina meta file.
    // update mysql and redis entries
    require "common.php";
    
    define('EMONCMS_EXEC', 1);
    
    chdir("/var/www/emoncms");
    
    require "process_settings.php";

    $mysqli = @new mysqli($server,$username,$password,$database);

    $result = $mysqli->query("SELECT * FROM feeds WHERE `engine`=6");

    $sourcedir = "/var/lib/phpfiwa/";
    $targetdir = "/var/lib/phpfina/";

    while($row = $result->fetch_array())
    {
        print $row['id']." ".$row['name']."\n";
        
        $id = $row['id'];
        
        $sourcefile = $sourcedir.$id."_0.dat";
        $targetfile = $targetdir.$id.".dat";
        
        copy_data(array(
           'sourcefile'=>$sourcefile,
           'targetfile'=>$targetfile,
           'bytelength'=>4
        ));
        
        $meta = new stdClass();
        $metafile = fopen($sourcedir.$id.".meta", 'rb');
        $tmp = unpack("I",fread($metafile,4));
        $tmp = unpack("I",fread($metafile,4)); 
        $meta->start_time = $tmp[1];
        $tmp = unpack("I",fread($metafile,4)); 
        $meta->nlayers = $tmp[1]; 
        
        $meta->npoints = array();
        for ($i=0; $i<$meta->nlayers; $i++)
        {
          $tmp = unpack("I",fread($metafile,4));
        }
        
        $meta->interval = array();
        for ($i=0; $i<$meta->nlayers; $i++)
        {
          $tmp = unpack("I",fread($metafile,4)); 
          $meta->interval[$i] = $tmp[1];
        }
        
        fclose($metafile);


        $metafile = fopen($targetdir.$id.".meta", 'wb');
        fwrite($metafile,pack("I",0));
        fwrite($metafile,pack("I",0)); 
        fwrite($metafile,pack("I",$meta->interval[0]));
        fwrite($metafile,pack("I",$meta->start_time)); 
        fclose($metafile);   
                
        $mysqli->query("UPDATE feeds SET `engine`=5 WHERE `id`='$id'");
    }
