<?php
    /*

    All Emoncms code is released under the GNU Affero General Public License.
    See COPYRIGHT.txt and LICENSE.txt.

    ---------------------------------------------------------------------
    Emoncms - open source energy visualisation
    Part of the OpenEnergyMonitor project:
    http://openenergymonitor.org

    */

    // Tool for checking feed engine meta data integrity

    include "lib/phpfiwa.php";
    include "lib/phpfina.php";
    include "lib/phptimeseries.php";
    include "lib/phptimestore.php";
    
    $dir = "/var/lib";

    $engines = array(    
        'phpfiwa'=>array(
            'dir'=> "$dir/phpfiwa/"
         ),
        'phpfina'=>array(
            'dir'=> "$dir/phpfina/"
         ),
        'phptimeseries'=>array(
            'dir'=> "$dir/phptimeseries/"
         ),
        'phptimestore'=>array(
            'dir'=> "$dir/timestore/"
         )
     );
     
     foreach ($engines as $engine=>$engine_properties)
     {
        print "\nCHECKING INTEGRITY OF $engine META DATA:\n";
        $function = $engine."_check";
        $feeds = $function($engine_properties);
     }
