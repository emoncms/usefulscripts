<?php
    if (!isset($argv[1])) {
        echo "please provide feedid in command line\n";
        die;
    }

    $phpfina_dir = "/home/pi/data/phpfina/";
    $phpfina_feedid = (int) $argv[1];
    
    $phpfina_fh = fopen($phpfina_dir.$phpfina_feedid.".dat", 'rb');
    $filesize = filesize($phpfina_dir.$phpfina_feedid.".dat");
    
    $npoints = floor($filesize / 4.0);
    
    $missing = 0;
    $total = 0;
    
    for ($i=0; $i<$npoints; $i++)
    {
        $val = unpack("f",fread($phpfina_fh,4));
        $value = $val[1];
        
        if (is_nan($value)) $missing++;
        $total++;
    }
    
    print "feed:".$phpfina_feedid.", ".$missing." missing, ".number_format((($total-$missing)/$total)*100,1)."%\n";
