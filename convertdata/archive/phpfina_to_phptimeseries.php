<?php

    $phpfina_dir = "/var/lib/phpfina/";
    $phpfina_feedid = 1;
    
    $phptimeseries_dir = "/var/lib/phptimeseries/"; 
    $phptimeseries_feedid = 2;
    
    //----------------------------------------------------
    
    // read meta data
    $meta = new stdClass();
    $metafile = fopen($phpfina_dir.$phpfina_feedid.".meta", 'rb');
    fseek($metafile,8);
    $tmp = unpack("I",fread($metafile,4)); 
    $meta->interval = $tmp[1];
    $tmp = unpack("I",fread($metafile,4)); 
    $meta->start_time = $tmp[1];
    fclose($metafile);

    
    $phpfina_fh = fopen($phpfina_dir.$phpfina_feedid.".dat", 'rb');
    $filesize = filesize($phpfina_dir.$phpfina_feedid.".dat");
    
    $npoints = floor($filesize / 4.0);
    
    $phptimeseries_fh = fopen($phptimeseries_dir."feed_".$phptimeseries_feedid.".MYD", 'a');
    
    for ($i=0; $i<$npoints; $i++)
    {
        $val = unpack("f",fread($phpfina_fh,4));

        $time = $meta->start_time + $i * $meta->interval;
        $value = $val[1];
        
        //print $time." ".$value."\n";
        
        if (!is_nan($value)) fwrite($phptimeseries_fh, pack("CIf",249,$time,$value));
    }
