<?php

    /*

    All Emoncms code is released under the GNU Affero General Public License.
    See COPYRIGHT.txt and LICENSE.txt.

    ---------------------------------------------------------------------
    Emoncms - open source energy visualisation
    Part of the OpenEnergyMonitor project:
    http://openenergymonitor.org

    */
    
    function phptimeseries_check($engine_properties)
    {
            $dir = $engine_properties['dir'];
            
        $files = scandir($dir);
        $feeds = array();
        for ($i=2; $i<count($files); $i++)
        {
          $filename_parts = explode(".",$files[$i]);
          $filename_parts = explode("_",$filename_parts[0]);
          $feedid = (int) $filename_parts[1];
          if ($feedid>0 && !in_array($feedid,$feeds)) $feeds[] = $feedid;
        }
        
        $n = 0;
        $errorcount = 0;
        
        foreach ($feeds as $id)
        {
            $error = false;
            $errormsg = "";
            
            $feedname = "feed_$id.MYD";
            
            $size = filesize($dir.$feedname);
                
            if (($size / 9.0)!=(int)($size / 9.0))
            {
                $error = true;
                $errormsg .= "[SIZE ERROR]";
            }

            if ($error)
            {
              print "Feed $id $errormsg ".date("d:m:Y G:i",filemtime($dir.$feedname))."\n";
              $errorcount ++;
            }
            $n++;
        }
        
        
        print "Error count: ".$errorcount."\n";
        print "Number of feeds: $n\n";
    }
