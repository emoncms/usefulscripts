<?php

function import_phptimeseries($feedid,$server,$apikey,$datadir)
{
    $feedname = $datadir."feed_$feedid.MYD";

    if (file_exists($feedname)) {
        $downloadfrom = filesize($feedname);

        if (intval($downloadfrom/9.0)!=($downloadfrom/9.0)) { 
            echo "PHPTimeSeries: local datafile filesize is not an integer number of 9 bytes\n"; 
//            return false;
        }
        
    } else {
        $downloadfrom = 0;
    }

    echo "PHPTIMESERIES: $feedid\n";
    
    $url = $server."/feed/export.json?apikey=$apikey&id=$feedid&start=$downloadfrom";
    
    if (!$backup = @fopen($feedname, 'a')) {
        echo "Cannot open local data file\n";
        return false;
    }

    $primary = @fopen( $url, 'r' );
    if (!$primary) {
        echo "Failed to open remote url\n";
        return false;
    }
    
    $dnsize = 0;
    if ($primary)
    {
        for (;;)
        {
            $data = fread($primary,8192);
            fwrite($backup,$data);
            $dnsize += strlen($data);
            if (feof($primary)) break;
        }
        
    }
    fclose($primary);
    fclose($backup);

    echo "--downloaded: ".$dnsize." bytes\n";
}

