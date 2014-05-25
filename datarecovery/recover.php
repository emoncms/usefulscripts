<?php
    /*

    All Emoncms code is released under the GNU Affero General Public License.
    See COPYRIGHT.txt and LICENSE.txt.

    ---------------------------------------------------------------------
    Emoncms - open source energy visualisation
    Part of the OpenEnergyMonitor project:
    http://openenergymonitor.org

    */
    
    // An emoncms data recovery tool for engines: phpfiwa, phpfina, phptimeseries, phptimestore
    // Works by copying emoncms data from a source drive datapoint by datapoint and writing the 
    // data to a backup location on the targer drive. If a datapoint cant be read because of a
    // disk input/output error the script will skip this datapoint and go on to the next.
    
    // TESTED WITH EMONCMS VERSION 8.2.3
    
    include "lib/common.php";
    include "lib/phpfiwa.php";
    include "lib/phpfina.php";
    include "lib/phptimeseries.php";
    include "lib/phptimestore.php";
    
    // Uncomment to attach recovered feeds to a target emoncms account:
    // $userid = 1;
    // $mysqli = @new mysqli("localhost","root","pass","backup");
    
    // Source engines
    // NOTE: Set different source and target locations.
    
    $sourcelocation = "/media/USER/HARDDRIVEID/var/lib";
    $targetlocation = "/home/USER/emoncmsbackup";

    $engines = array(    
        'phpfiwa'=>array(
            'source'=> "$sourcelocation/phpfiwa/",
            'target'=> "$targetlocation/phpfiwa/"
         ),
        'phpfina'=>array(
            'source'=> "$sourcelocation/phpfina/",
            'target'=> "$targetlocation/phpfina/"
         ),
        'phptimeseries'=>array(
            'source'=> "$sourcelocation/phptimeseries/",
            'target'=> "$targetlocation/phptimeseries/"
         ),
        'phptimestore'=>array(
            'source'=> "$sourcelocation/timestore/",
            'target'=> "$targetlocation/timestore/"
         )
     );
     
     print "Emoncms data recovery tool\n";
     
     foreach ($engines as $engine=>$engine_properties)
     {
        print "Recovering $engine:\n";
        $function = $engine."_recover";
        $feeds = $function($engine_properties);
        // Uncomment to attach recovered feeds to a target emoncms account:
        // attach_to_user($userid,$feeds,$engine);
     }
