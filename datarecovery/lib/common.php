<?php

    /*

    All Emoncms code is released under the GNU Affero General Public License.
    See COPYRIGHT.txt and LICENSE.txt.

    ---------------------------------------------------------------------
    Emoncms - open source energy visualisation
    Part of the OpenEnergyMonitor project:
    http://openenergymonitor.org

    */

    function copy_data($properties)
    {
        $sourcefile = $properties['sourcefile'];
        $targetfile = $properties['targetfile'];
        $bytelength = $properties['bytelength'];
        
        $fhr = fopen($sourcefile, 'rb');
        $fhw = fopen($targetfile, 'wb');

        $size = filesize($sourcefile);
        $onepercent = (int) $size / 100;

        $fpos = 0;
        $pos = 0;
        $percent = 0;

        while (!feof($fhr))
        {
            $d = fseek($fhr,$fpos);
            $d = fread($fhr,$bytelength);
            $len = strlen($d);

            if ($len==$bytelength) {
            
                if ($bytelength==4) {
                    $val = unpack("f",$d);
                    fwrite($fhw,pack("f",$val[1]));
                } elseif ($bytelength==9) {
                    $dp = unpack("x/Itime/fvalue",$d);
                    fwrite($fhw, pack("CIf",249,$dp['time'],$dp['value']));
                } else { die; }
                
                $pos += $bytelength;
                $fpos += $bytelength;
            } elseif ($len==0) {
                print "0 length skipping 4\n";
                $fpos += $bytelength;
                $pos += $bytelength;
                
                // Technically if it cant read one dp position
                // we are skipping 4 bytes and reading the next 
                // so a NAN should be written in place of the bad dp
                // but filesize comparison seems to suggest the
                // resultant feed becomes longer than the source feed.
                
                // fwrite($fhw,pack("f",NAN));
                
            } else {
                print "Length $len\n";
                break;
            }
            
            if ($pos>$onepercent) {
                $pos = 0;
                $percent ++;
                print "$percent%\n";
            }
        }

        fclose($fhr);
        fclose($fhw);
    }
    
    function attach_to_user($userid,$feeds,$enginename)
    {
        global $mysqli;
        
        foreach ($feeds as $id)
        {
            $name = $enginename."_".$id;
            $datatype = 1;
            
            if ($enginename=='phptimeseries') $engine = 2;
            if ($enginename=='phptimestore') $engine = 4;
            if ($enginename=='phpfina') $engine = 5;
            if ($enginename=='phpfiwa') $engine = 6;

            $result = $mysqli->query("SELECT id FROM feeds WHERE id = '$id'");
            if ($result->num_rows==0) {
                $result = $mysqli->query("INSERT INTO feeds (id,userid,name,datatype,public,engine) VALUES ('$id','$userid','$name','$datatype',false,'$engine')");
                print "Adding feed $id\n";
            }
        }
    }
