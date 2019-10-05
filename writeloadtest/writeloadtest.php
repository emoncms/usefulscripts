<?php

$apikey = "";
$host = "http://localhost";

$last = 0.0;
$total_sectors_written2 = -1;
$total_sectors_written3 = -1;

while(true) {

    if ((time()-$last)>=60.0) 
    {
        $last = time();

        $last_sectors_written2 = $total_sectors_written2;
        $total_sectors_written2 = exec("awk '/mmcblk0p2/ {print $10}' /proc/diskstats");
        $sectors_written2 = $total_sectors_written2 - $last_sectors_written2;

        $last_sectors_written3 = $total_sectors_written3;
        $total_sectors_written3 = exec("awk '/mmcblk0p3/ {print $10}' /proc/diskstats");
        $sectors_written3 = $total_sectors_written3 - $last_sectors_written3;

        if ($last_sectors_written2!=-1 && $last_sectors_written3!=-1) {
            // Send to emoncms
            file_get_contents("$host/input/post?node=diskstats&data=totalsectorswritten2:$total_sectors_written2,sectorswritten2:$sectors_written2,totalsectorswritten3:$total_sectors_written3,sectorswritten3:$sectors_written3&apikey=$apikey");
        }
    
    }
    
    sleep(1.0);
}
