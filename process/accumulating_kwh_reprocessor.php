<?php

$dir = stdin("Please enter the emoncms phpfina feed directory: ");
$id = stdin("Please enter the emoncms feed id: ");
$max_power_limit = stdin("Please enter the max power allowed: ");

join_nan_values($dir,$id);
fix_kwh_faster($dir,$id,$max_power_limit);

function fix_kwh($dir,$id,$max_power_limit)
{
    echo "==================================================================\n";
    echo "kWh reprocessor\n";
    echo "Feed id:$id\n";

    if (!$metafile = @fopen($dir.$id.".meta", 'rb')) {
        echo "ERROR: could not open $dir $id.meta\n";
        return false;
    }
    fseek($metafile,8);
    $tmp = unpack("I",fread($metafile,4)); 
    $interval = $tmp[1];
    $tmp = unpack("I",fread($metafile,4)); 
    $start_time = $tmp[1];
    fclose($metafile);
    
    if ($interval<5) {
        echo "ERROR: interval is less than 5, found:$interval\n";
        return false;
    }

    if (@filesize($dir.$id.".dat")%4 != 0) {
        echo "ERROR: data file length is not an integer number of 4 byte blocks\n";
        return false;
    }
    
    $npoints = floor(filesize($dir.$id.".dat") / 4.0);

    echo "Meta: interval:$interval, start_time:$start_time, npoints:$npoints\n";

    $stime = microtime(true);

    if (!$fh = @fopen($dir.$id.".dat", 'c+')) {
        echo "ERROR: could not open $dir $id.dat\n";
        return false;
    }

    $nanfix = 0;
    $kwhfix = 0;
    $value = NAN;
    $totalwh = 0;
    $max_power = 0;

    for ($n=0; $n<$npoints; $n++) {
        $tmp = unpack("f",fread($fh,4));
        
        if (!is_nan($tmp[1])) {
            $lastvalue = $value;
            $value = $tmp[1];
            $val_diff = $value - $lastvalue;
            
            $power = ($val_diff * 3600) / $interval;
            if ($power>$max_power) $max_power = $power;
            
            if ($val_diff>0 && $power<$max_power_limit) $totalwh += $val_diff;
            
            if ($totalwh!=$value) {
                fseek($fh,$n*4);
                fwrite($fh,pack("f",$totalwh));
                $kwhfix++;
            }
            
        } else {
            fseek($fh,$n*4);
            fwrite($fh,pack("f",$totalwh));
            $nanfix++;
        }
        
        // 10% marker
        if ($n%($npoints/10)==0) echo ".";
        
        // Slow down
        // if ($n%50==0) usleep(1);
    }

    echo "\n";

    print "maxpower: ".round($max_power)."W\n";
    print "kwhfix: ".round(($kwhfix/$npoints)*100)."%\n";
    print "nanfix: ".round(($nanfix/$npoints)*100)."%\n";
    print "time: ".(microtime(true)-$stime)."\n";
    echo "==================================================================\n";

    return true;    
}

function fix_kwh_faster($dir,$id,$max_power_limit)
{
    echo "==================================================================\n";
    echo "kWh reprocessor\n";
    echo "Feed id:$id\n";

    if (!$metafile = @fopen($dir.$id.".meta", 'rb')) {
        echo "ERROR: could not open $dir $id.meta\n";
        return false;
    }
    fseek($metafile,8);
    $tmp = unpack("I",fread($metafile,4)); 
    $interval = $tmp[1];
    $tmp = unpack("I",fread($metafile,4)); 
    $start_time = $tmp[1];
    fclose($metafile);
    
    if ($interval<5) {
        echo "ERROR: interval is less than 5, found:$interval\n";
        return false;
    }

    if (@filesize($dir.$id.".dat")%4 != 0) {
        echo "ERROR: data file length is not an integer number of 4 byte blocks\n";
        return false;
    }
    
    $npoints = floor(filesize($dir.$id.".dat") / 4.0);
    if ($npoints==0) {
        echo "ERROR: npoints is zero\n";
        return false;
    }
    
    echo "Meta: interval:$interval, start_time:$start_time, npoints:$npoints\n";

    $stime = microtime(true);

    if (!$fh = @fopen($dir.$id.".dat", 'c+')) {
        echo "ERROR: could not open $dir $id.dat\n";
        return false;
    }

    $fpos = 0;
    $dplefttoread = $npoints;
    $blocksize = 100000;
    
    $kwhfix = 0;
    $value = 0;
    $totalwh = 0;
    $max_power = 0;

    $stime = microtime(true);
    
    while ($dplefttoread>0)
    {
        fseek($fh,$fpos*4);

        $values = unpack("f*",fread($fh,4*$blocksize));
        $count = count($values);
        if ($count==0) break;

        for ($i=1; $i<=$count; $i++)
        {
            $dpos = $fpos + ($i-1);
            
            $lastvalue = $value;
            $value = $values[$i];
            $val_diff = $value - $lastvalue;
            
            $power = ($val_diff * 3600) / $interval;
            if ($power>$max_power) $max_power = $power;
            
            if ($val_diff>0 && $power<$max_power_limit) $totalwh += $val_diff;
            
            if ($totalwh!=$value) {
                fseek($fh,$dpos*4);
                fwrite($fh,pack("f",$totalwh));
                $kwhfix++;
            }
            
            if ($dpos%($npoints/10)==0) echo ".";
        }

        $dplefttoread -= $count;
        $fpos += $count;

    }
    fclose($fh);
    
    echo "\n";

    print "maxpower: ".round($max_power)."W\n";
    print "kwhfix: ".round(($kwhfix/$npoints)*100)."%\n";
    print "time: ".(microtime(true)-$stime)."\n";
    echo "==================================================================\n";
}

function join_nan_values($dir,$id)
{
    echo "==================================================================\n";
    echo "NAN remover\n";
    echo "Feed id:$id\n";
    
    if (!$fh = @fopen($dir.$id.".dat", 'c+')) {
        echo "ERROR: could not open $dir $id.dat\n";
        return false;
    }
    $npoints = floor(filesize($dir.$id.".dat") / 4.0);
    if ($npoints==0) {
        echo "ERROR: npoints is zero\n";
        return false;
    }

    $fpos = 0;
    $dplefttoread = $npoints;

    $blocksize = 100000;

    $in_nan_period = 0;
    $startval = 0;
    $startpos = 0;
    $nanfix = 0;
    
    $stime = microtime(true);

    while ($dplefttoread>0)
    {
        fseek($fh,$fpos*4);

        $values = unpack("f*",fread($fh,4*$blocksize));
        $count = count($values);
        if ($count==0) break;

        for ($i=1; $i<=$count; $i++)
        {
            $dpos = $fpos + ($i-1);
            if (is_nan($values[$i])) {
                $in_nan_period = 1;
            } else {

                $endval = $values[$i];

                if ($in_nan_period==1) {
                    $npoints2 = $dpos - $startpos;
                    $diff = ($endval - $startval) / $npoints2;
                    for ($p=1; $p<$npoints2; $p++)
                    {
                        fseek($fh,($startpos+$p)*4);
                        fwrite($fh,pack("f",$startval+($p*$diff)));
                        $nanfix++;
                    }
                }
                $startval = $endval;
                $startpos = $dpos;
                $in_nan_period = 0;
            }
            
            if ($dpos%($npoints/10)==0) echo ".";
        }

        $dplefttoread -= $count;
        $fpos += $count;

    }
    fclose($fh);
    
    echo "\n";
    
    print "nanfix: ".round(($nanfix/$npoints)*100)."%\n";
    print "time: ".(microtime(true)-$stime)."\n";
    echo "==================================================================\n";
}

function stdin($prompt = null){
    if($prompt){
        echo $prompt;
    }
    $fp = fopen("php://stdin","r");
    $line = rtrim(fgets($fp, 1024));
    return $line;
}
