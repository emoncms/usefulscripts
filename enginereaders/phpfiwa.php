<?php

    // Directory of phpfina feeds, see: settings.php
    $dir = "/var/lib/phpfiwa/";
    
    // Feed id to read: 
    $feedid = 1;
    
    //----------------------------------------------------
    
    $meta = new stdClass();
    $meta->id = $feedid;
    
    $metafile = fopen($dir."$feedid.meta", 'rb');
    $tmp = unpack("I",fread($metafile,4));
    $tmp = unpack("I",fread($metafile,4)); 
    $meta->start_time = $tmp[1];
    $tmp = unpack("I",fread($metafile,4)); 
    $meta->nlayers = $tmp[1];
      
    $meta->npoints = array();
    for ($i=0; $i<$meta->nlayers; $i++)
    {
      $tmp = unpack("I",fread($metafile,4)); 
      $meta->npoints[$i] = $tmp[1];
    }
    
    $meta->interval = array();
    for ($i=0; $i<$meta->nlayers; $i++)
    {
      $tmp = unpack("I",fread($metafile,4)); 
      $meta->interval[$i] = $tmp[1];
    }
    
    fclose($metafile);

    
    $fh = fopen($dir.$feedid."_0.dat", 'rb');
    $filesize = filesize($dir.$feedid."_0.dat");
    
    $npoints = floor($filesize / 4.0);
    
    for ($i=0; $i<$npoints; $i++)
    {
        $val = unpack("f",fread($fh,4));

        $time = $meta->start_time + $i * $meta->interval[0];
        $value = $val[1];
        
        print $time." ".$value."\n";
    }
