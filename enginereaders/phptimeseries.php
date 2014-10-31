<?php

    // Directory of phptimeseries feeds, see: settings.php
    $dir = "/var/lib/phptimeseries/";
    
    // Feed id to read: 
    $feedid = 1;
    
    //----------------------------------------------------
    
    $fh = fopen($dir."feed_$feedid.MYD", 'rb');
    $filesize = filesize($dir."feed_$feedid.MYD");
    
    $npoints = floor($filesize / 9.0);
    
    for ($i=0; $i<$npoints; $i++)
    {
        // Read next datapoint
        $d = fread($fh,9);

        // Itime = unsigned integer (I) assign to 'time'
        // fvalue = float (f) assign to 'value'
        $array = unpack("x/Itime/fvalue",$d);

        $time = $array['time'];
        $value = $array['value'];
        
        print $time." ".$value."\n";
    }
