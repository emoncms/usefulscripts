<?php
    /*

    All Emoncms code is released under the GNU Affero General Public License.
    See COPYRIGHT.txt and LICENSE.txt.

    ---------------------------------------------------------------------
    Emoncms - open source energy visualisation
    Part of the OpenEnergyMonitor project:
    http://openenergymonitor.org

    */
    
    function phpfina_recover($engine_properties)
    {
        $source = $engine_properties['source'];
        $target = $engine_properties['target'];
        
        // Identify feeds in folder 
        $files = scandir($source);
        $feeds = array();
        for ($i=2; $i<count($files); $i++)
        {
          $filename_parts = explode(".",$files[$i]);
          $feedid = (int) $filename_parts[0];
          if ($feedid>0 && !in_array($feedid,$feeds)) $feeds[] = $feedid;
        }
        
        foreach ($feeds as $id)
        {
            print "Copying feed $id: \n";
               
            copy_data(array(
              'sourcefile'=>$source.$id.".dat",
              'targetfile'=>$target.$id.".dat",
              'bytelength'=>4
            ));
            
            clearstatcache();
            $npoints = filesize($target.$id.".dat") / 4.0;
            if ((int)$npoints!=$npoints) {
                print "filesize error\n";
            } else {
                $feedname = "$id.npoints";    
                $metafile = fopen($target.$feedname, 'wb');
                fwrite($metafile,pack("I",$npoints));
                fclose($metafile);
            }
            
            $meta = new stdClass();
            $metafile = fopen($source.$id.".meta", 'rb');
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
            
            if ($meta->id != $id) print "Feed id's dont match!\n";
            if ($meta->start_time==0) print "Feed start time error!\n";
            if ($meta->interval<5) print "Feed interval error!\n";
            
            $metafile = fopen($target.$id.".meta", 'wb');
            fwrite($metafile,pack("I",$meta->id));
            // Legacy npoints, npoints moved to seperate file
            fwrite($metafile,pack("I",0)); 
            fwrite($metafile,pack("I",$meta->interval));
            fwrite($metafile,pack("I",$meta->start_time)); 
            fclose($metafile);    
        }
        
        return $feeds;
    }
