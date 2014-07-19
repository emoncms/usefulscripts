<?php
    /*

    All Emoncms code is released under the GNU Affero General Public License.
    See COPYRIGHT.txt and LICENSE.txt.

    ---------------------------------------------------------------------
    Emoncms - open source energy visualisation
    Part of the OpenEnergyMonitor project:
    http://openenergymonitor.org

    */
    
    function phpfina_check($engine_properties)
    {
        $dir = $engine_properties['dir'];
        
        $files = scandir($dir);
        $feeds = array();
        for ($i=2; $i<count($files); $i++)
        {
          $filename_parts = explode(".",$files[$i]);
          $feedid = (int) $filename_parts[0];
          if ($feedid>0 && !in_array($feedid,$feeds)) $feeds[] = $feedid;
        }
        
        $error_count = 0;
        $n = 0;

        foreach ($feeds as $id)
        {
            $error = false;
            $errormsg = "";

            // 1) Analyse meta file
           
            $feedname = "$id.meta";
            
            // CHECK 1: META FILE EXISTS
            if (!file_exists($dir.$feedname)) {
                print "[Meta file does not exist: $id]\n";
                $error = true;
            } 
            else 
            {
                $meta = new stdClass();
                $metafile = fopen($dir.$feedname, 'rb');
                fseek($metafile,8);
                $tmp = unpack("I",fread($metafile,4)); 
                $meta->interval = $tmp[1];
                $tmp = unpack("I",fread($metafile,4)); 
                $meta->start_time = $tmp[1];
                fclose($metafile);
               
                clearstatcache($dir.$id.".dat");
                $npoints = filesize($dir.$id.".dat") / 4;
                
                if ($meta->interval < 5){
                    $errormsg .= "[interval: ".$meta->interval."]";
                    $error = true; 
                }
                
                if (intval($npoints) != $npoints){
                    $errormsg .= "[integer npoints:".intval($npoints)." != npoints:$npoints]";
                    $error = true; 
                }
                
                if ($meta->start_time==0 && $npoints>0) {
                    $errormsg .= "[start==0]";
                    $error = true;
                }
                
                if ($error) print "Feed $id ".$errormsg." [".date("d:m:Y G:i",filemtime($dir.$feedname))."]\n";
            }
                 
            if ($error) $error_count ++;        
            $n++;   
        }
        
        print "Error count: ".$error_count."\n";
        print "Number of feeds: $n\n";
    }
