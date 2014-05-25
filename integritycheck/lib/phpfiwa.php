<?php
    /*

    All Emoncms code is released under the GNU Affero General Public License.
    See COPYRIGHT.txt and LICENSE.txt.

    ---------------------------------------------------------------------
    Emoncms - open source energy visualisation
    Part of the OpenEnergyMonitor project:
    http://openenergymonitor.org

    */
    
    function phpfiwa_check($engine_properties)
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
                $tmp = unpack("I",fread($metafile,4)); 
                $meta->start_time = $tmp[1];
                $tmp = unpack("I",fread($metafile,4)); 
                $meta->nlayers = $tmp[1];
                $meta->npoints = array();
                for ($i=0; $i<$meta->nlayers; $i++) {
                  $tmp = unpack("I",fread($metafile,4)); 
                  $meta->npoints[$i] = $tmp[1];
                }
                $meta->interval = array();
                for ($i=0; $i<$meta->nlayers; $i++) {
                  $tmp = unpack("I",fread($metafile,4)); 
                  $meta->interval[$i] = $tmp[1];
                }
                fclose($metafile);
                
                if ($meta->id != $id) {
                    $errormsg .= "[non matching feedid ".$meta->feedid."]";
                    $error = true;            
                }
                
                // CHECK 4: NLAYERS WITHIN CORRECT RANGE
                if ($meta->nlayers<1 || $meta->nlayers>4) {
                    $errormsg .= "[nlayers out of range: ".$meta->nlayers."]";
                    $error = true; 
                }
                
                $lcount = 0;
                if (file_exists($dir."$id.npoints")) 
                {
                    $metafile = fopen($dir."$id.npoints", 'rb');
                    $meta->npoints = array();
                    for ($i=0; $i<$meta->nlayers; $i++)
                    {
                      $tmp = unpack("I",fread($metafile,4)); 
                      $meta->npoints[$i] = $tmp[1];
                      
                      if (file_exists($dir.$id."_$i.dat")) $lcount ++;
                      $fsize = filesize($dir.$id."_$i.dat") / 4;
                      if ($meta->npoints[$i]!=$fsize) {
                        $errormsg .= "[npoints:".$meta->npoints[$i]." != fsize:$fsize]";
                        $error = true; 
                      }
                    }
                    fclose($metafile);
                    
                    if ($meta->start_time==0 && $meta->npoints[0]>0) {
                        $errormsg .= "[start==0]";
                        $error = true;
                    }
                }
                else 
                {
                    $errormsg .= "[missing npoints meta file]";
                    $error = true;
                }
                
                if ($meta->nlayers!=$lcount)
                {
                    $errormsg .= "[lcount:$lcount != ".$meta->nlayers."]";
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
