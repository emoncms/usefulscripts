<?php

    /*

    All Emoncms code is released under the GNU Affero General Public License.
    See COPYRIGHT.txt and LICENSE.txt.

    ---------------------------------------------------------------------
    Emoncms - open source energy visualisation
    Part of the OpenEnergyMonitor project:
    http://openenergymonitor.org

    */

    function phptimestore_check($engine_properties)
    {
        $dir = $engine_properties['dir'];

        $files = scandir($dir);
        $feeds = array();
        for ($i=2; $i<count($files); $i++)
        {
          $filename_parts = explode("_",$files[$i]);
          $feedid = (int) $filename_parts[0];
          if ($feedid>0 && !in_array($feedid,$feeds)) $feeds[] = $feedid;
        }
        
        $error_count = 0;
        $n = 0;

        foreach ($feeds as $id)
        {
            $error = false;
            $errormsg = "";
            
            $feedname = str_pad($id, 16, '0', STR_PAD_LEFT).".tsdb";
            $meta = new stdClass();
            $size = filesize($dir.$feedname);
            
            if (!($size==36 || $size == 272)) {
                $errormsg .= "[feed:$id metadata filesize error, size = $size]";
                $error = true;
            } 
            
            $metafile = fopen($dir.$feedname, 'rb');

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
            
            if ($meta->nmetrics!=1) {
                $errormsg .= "[nmetrics is not 1]";
                $error = true;
            }
            
            if ($meta->interval<5 || $meta->interval>(24*3600))
            {
                $errormsg .= "[interval is out of range = ".$meta->interval."]";
                $error = true;
            }
             
            $npoints = filesize($dir.str_pad($id, 16, '0', STR_PAD_LEFT)."_0_.dat") / 4;
            if (intval($npoints)!=$npoints) {
                $errormsg .= "[npoints:".intval($npoints)." != npoints:$npoints]";
                $error = true; 
            }

            $feedname = str_pad($id, 16, '0', STR_PAD_LEFT).".tsdb";
            if ($error) print "Feed $id ".$errormsg." [".date("d:m:Y G:i",filemtime($dir.$feedname))."]\n";
            if ($error) $error_count ++;
            $n++;   
        }
        
        print "Error count: ".$error_count."\n";
        print "Number of feeds: $n\n";
    }
