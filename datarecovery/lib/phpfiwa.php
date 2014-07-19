<?php
    /*

    All Emoncms code is released under the GNU Affero General Public License.
    See COPYRIGHT.txt and LICENSE.txt.

    ---------------------------------------------------------------------
    Emoncms - open source energy visualisation
    Part of the OpenEnergyMonitor project:
    http://openenergymonitor.org

    */
    
    function phpfiwa_recover($engine_properties)
    {
        $source = $engine_properties['source'];
        $target = $engine_properties['target'];
    
        // 1) Identify feeds in folder 
           
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
            $npoints = array();
            print $id."\n";
            
            for ($l=0; $l<10; $l++)
            {
                if (file_exists($source.$id."_".$l.".dat"))
                {
                    print "Copying data file layer $id $l\n";
                    
                    copy_data(array(
                      'sourcefile'=>$source.$id."_".$l.".dat",
                      'targetfile'=>$target.$id."_".$l.".dat",
                      'bytelength'=>4
                    ));
                    
                    clearstatcache($target.$id."_".$l.".dat");
                    $npoints[] = filesize($target.$id."_".$l.".dat") / 4.0;
                }
            }
            
            foreach ($npoints as $np) {
                if ((int)$np!=$np) {
                    print "filesize error\n";
                }
            }
            
            $meta = new stdClass();
            $metafile = fopen($source.$id.".meta", 'rb');
            fseek($metafile,4);
            $tmp = unpack("I",fread($metafile,4)); 
            $meta->start_time = $tmp[1];
            $tmp = unpack("I",fread($metafile,4)); 
            $meta->nlayers = $tmp[1]; 
            
            for ($i=0; $i<$meta->nlayers; $i++)
            {
              $tmp = unpack("I",fread($metafile,4));
            }
            
            $meta->interval = array();
            for ($i=0; $i<$meta->nlayers; $i++)
            {
              $tmp = unpack("I",fread($metafile,4)); 
              $meta->interval[$i] = $tmp[1];
            }
            
            fclose($metafile);
            
            if ($meta->start_time==0) print "Feed start time error!\n";
            if ($meta->interval[0]<5) print "Feed interval error!".$meta->interval[0]."\n";
            
            $metafile = fopen($target.$id.".meta", 'wb');    
            fwrite($metafile,pack("I",0));
            fwrite($metafile,pack("I",$meta->start_time)); 
            fwrite($metafile,pack("I",$meta->nlayers));
            foreach ($meta->interval as $n) fwrite($metafile,pack("I",0));       // Legacy
            foreach ($meta->interval as $d) fwrite($metafile,pack("I",$d));
            fclose($metafile);
            
        }
        return $feeds;
    }
