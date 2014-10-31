<?php

    // Directory of phpfina feeds, see: settings.php
    $dir = "/home/trystan/Data/19July/phpfina/";
    
    // Feed id to read: 
    $feedid = 1;
    
    //----------------------------------------------------
    
    // read meta data
    $meta = new stdClass();
    $metafile = fopen($dir.$feedid.".meta", 'rb');
    fseek($metafile,8);
    $tmp = unpack("I",fread($metafile,4)); 
    $meta->interval = $tmp[1];
    $tmp = unpack("I",fread($metafile,4)); 
    $meta->start_time = $tmp[1];
    fclose($metafile);

    
    $fh = fopen($dir."$feedid.dat", 'rb');
    $filesize = filesize($dir."$feedid.dat");
    
    $npoints = floor($filesize / 4.0);
    
    for ($i=0; $i<$npoints; $i++)
    {
        $val = unpack("f",fread($fh,4));

        $time = $meta->start_time + $i * $meta->interval;
        $value = $val[1];
        
        print $time." ".$value."\n";
    }
