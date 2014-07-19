<?php

    /*

    All Emoncms code is released under the GNU Affero General Public License.
    See COPYRIGHT.txt and LICENSE.txt.

    ---------------------------------------------------------------------
    Emoncms - open source energy visualisation
    Part of the OpenEnergyMonitor project:
    http://openenergymonitor.org

    */

    function phptimestore_recover($engine_properties)
    {
        $source = $engine_properties['source'];
        $target = $engine_properties['target'];
    
        // 1) Identify feeds in folder 
           
        $files = scandir($source);
        $feeds = array();
        for ($i=2; $i<count($files); $i++)
        {
          $filename_parts = explode("_",$files[$i]);
          $feedid = (int) $filename_parts[0];
          if ($feedid>0 && !in_array($feedid,$feeds)) $feeds[] = $feedid;
        }
        
        foreach ($feeds as $id)
        {
            $npoints = array();
            print $id."\n";
            
            for ($l=0; $l<10; $l++)
            {
                $name = str_pad($id, 16, '0', STR_PAD_LEFT);
                if (file_exists($source.$name."_".$l."_.dat"))
                {
                    print "Copying data file layer $id $l\n";  
                    
                    copy_data(array(
                      'sourcefile'=>$source.$name."_".$l."_.dat",
                      'targetfile'=>$target.$name."_".$l."_.dat",
                      'bytelength'=>4
                    ));
                    
                    clearstatcache($target.$name."_".$l."_.dat");
                    $npoints[] = filesize($target.$name."_".$l."_.dat") / 4.0;
                }
            }
            
            $feedname = str_pad($id, 16, '0', STR_PAD_LEFT).".tsdb";
            $meta = new stdClass(); 
            $metafile = fopen($source.$feedname, 'rb');

            fseek($metafile,8);
            $d = fread($metafile,8);
            $tmp = unpack("h*",$d); // no longer used
            $tmp = unpack("I",fread($metafile,4));
            $meta->nmetrics = $tmp[1];
            $tmp = unpack("I",fread($metafile,4)); // no longer used
            $tmp = unpack("I",fread($metafile,8));
            $meta->start = $tmp[1];
            $tmp = unpack("I",fread($metafile,4));
            $meta->interval = $tmp[1];
            fclose($metafile);
            
            $metafile = fopen($target.$feedname, 'wb');
            fwrite($metafile,pack("I",0));
            fwrite($metafile,pack("I",0));
            fwrite($metafile,pack("h*",strrev(str_pad(0, 16, '0', STR_PAD_LEFT))));
            fwrite($metafile,pack("I",$meta->nmetrics));
            fwrite($metafile,pack("I",0));                  // Legacy
            fwrite($metafile,pack("I",$meta->start));
            fwrite($metafile,pack("I",0));
            fwrite($metafile,pack("I",$meta->interval));
            fclose($metafile);
        }
        return $feeds;        
    }
