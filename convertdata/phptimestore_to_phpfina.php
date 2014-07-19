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

    $result = $mysqli->query("SELECT * FROM feeds WHERE `engine`= 4");

    $sourcedir = "/var/lib/timestore/";
    $targetdir = "/var/lib/phpfina/";

    while($row = $result->fetch_array())
    {
        print $row['id']." ".$row['name']."\n";
        
        $id = $row['id'];
        
        $sourcefile = $sourcedir.str_pad($id, 16, '0', STR_PAD_LEFT)."_0_.dat";
        $targetfile = $targetdir.$id.".dat";
        
        copy_data(array(
           'sourcefile'=>$sourcefile,
           'targetfile'=>$targetfile,
           'bytelength'=>4
        ));
        
        $feedname = str_pad($id, 16, '0', STR_PAD_LEFT).".tsdb";
        $meta = new stdClass(); 
        $metafile = fopen($sourcedir.$feedname, 'rb');

        fseek($metafile,8);
        $d = fread($metafile,8);
        $tmp = unpack("h*",$d);
        $tmp = unpack("I",fread($metafile,4));
        $meta->nmetrics = $tmp[1];
        $tmp = unpack("I",fread($metafile,4));
        $tmp = unpack("I",fread($metafile,8));
        $meta->start = $tmp[1];
        $tmp = unpack("I",fread($metafile,4));
        $meta->interval = $tmp[1];
        fclose($metafile);

        $metafile = fopen($targetdir.$id.".meta", 'wb');
        fwrite($metafile,pack("I",0));
        fwrite($metafile,pack("I",0)); 
        fwrite($metafile,pack("I",$meta->interval));
        fwrite($metafile,pack("I",$meta->start)); 
        fclose($metafile);
                
        $mysqli->query("UPDATE feeds SET `engine`=5 WHERE `id`='$id'");
    }
