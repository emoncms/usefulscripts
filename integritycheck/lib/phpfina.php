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
                print "[Meta file does not exist: $userid $id]\n";
                $error = true;
            } 
            else 
            {
                $meta = new stdClass();
                $metafile = fopen($dir.$feedname, 'rb');
                $tmp = unpack("I",fread($metafile,4)); 
                $meta->id = $tmp[1];
                // Legacy npoints
                $tmp = unpack("I",fread($metafile,4));
                $legacy_npoints = $tmp[1];
                $tmp = unpack("I",fread($metafile,4)); 
                $meta->interval = $tmp[1];
                $tmp = unpack("I",fread($metafile,4)); 
                $meta->start_time = $tmp[1];
                fclose($metafile);
                
                $metafile = fopen($dir."$id.npoints", 'rb');
                $tmp = unpack("I",fread($metafile,4));
                $npoints = $tmp[1];
                fclose($metafile);
                $meta->npoints = $npoints;
                
                $fsize = filesize($dir.$id.".dat") / 4;
                
                if ($meta->id != $id) {
                    $errormsg .= "[non matching feedid ".$meta->id."]";
                    $error = true;            
                }
                
                if ($meta->interval < 5){
                    $errormsg .= "[interval: ".$meta->interval."]";
                    $error = true; 
                }
                
                if ($meta->npoints != $fsize){
                    $errormsg .= "[npoints:".$meta->npoints." != fsize:$fsize]";
                    $error = true; 
                }
                
                if ($meta->start_time==0 && $meta->npoints>0) {
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
