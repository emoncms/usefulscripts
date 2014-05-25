<?php

    /*

    All Emoncms code is released under the GNU Affero General Public License.
    See COPYRIGHT.txt and LICENSE.txt.

    ---------------------------------------------------------------------
    Emoncms - open source energy visualisation
    Part of the OpenEnergyMonitor project:
    http://openenergymonitor.org

    */
    
    function phptimeseries_recover($engine_properties)
    {
        $source = $engine_properties['source'];
        $target = $engine_properties['target'];
    
        $files = scandir($source);
        $feeds = array();
        for ($i=2; $i<count($files); $i++)
        {
          $filename_parts = explode(".",$files[$i]);
          $filename_parts = explode("_",$filename_parts[0]);
          $feedid = (int) $filename_parts[1];
          if ($feedid>0 && !in_array($feedid,$feeds)) $feeds[] = $feedid;
        }
        
        foreach ($feeds as $id)
        {
            print "Copying feed $id: \n";            
            copy_data(array(
              'sourcefile'=>$source."feed_".$id.".MYD",
              'targetfile'=>$target."feed_".$id.".MYD",
              'bytelength'=>9
            ));   
        }
        return $feeds;
    }
