<?php
    /*

    All Emoncms code is released under the GNU Affero General Public License.
    See COPYRIGHT.txt and LICENSE.txt.

    ---------------------------------------------------------------------
    Emoncms - open source energy visualisation
    Part of the OpenEnergyMonitor project:
    http://openenergymonitor.org

    */

    // A tool for downloading and making a local copy of the feed data on a emoncms.org account (or other remote emoncms server). 
    // The local copy can just be a data backup or it can easily be linked to a locally installed emoncms server.

    include "lib/metadata.php";    
    include "lib/mysql.php";
    include "lib/phptimeseries.php";
    include "lib/phptimestore.php";
    include "lib/phpfiwa.php";
    include "lib/phpfina.php";
        
    $remote_server = "http://emoncms.org";
    $remote_apikey = "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx";
    
    $link_to_local_emoncms = false;
    $local_emoncms_location = "/var/www/emoncms/";
    $local_emoncms_userid = 1;
    
    // $dir and $engines only need to be set if your not linking to a local emoncms installation
    // if you are linking to a local emoncms installation, the settings will be fetched from
    // your settings.php file instead.
    
    // directory location without end /
    $dir = "/home/user/data";
    
    $engines = array(    
        'phpfiwa'=>array(
            'datadir'=> "$dir/phpfiwa/"
         ),
        'phpfina'=>array(
            'datadir'=> "$dir/phpfina/"
         ),
        'phptimeseries'=>array(
            'datadir'=> "$dir/phptimeseries/"
         ),
        'phptimestore'=>array(
            'datadir'=> "$dir/timestore/"
         )
    );

    if ($link_to_local_emoncms)
    {
        define('EMONCMS_EXEC', 1);
        chdir($local_emoncms_location);
        require "process_settings.php";
        $mysqli = @new mysqli($server,$username,$password,$database);
        $redis = new Redis();
        $redis->connect("127.0.0.1");
        
        $engines = $feed_settings;
    } else {
        $mysqli = false;
        $redis = false;
    }

    // Fetch remote server feed list
    $feeds = file_get_contents($remote_server."/feed/list.json?apikey=$remote_apikey");
    $feeds = json_decode($feeds);
    
    $number_of_feeds = count($feeds);
    echo $number_of_feeds." Emoncms.org feeds found\n";
    
    if ($number_of_feeds==0) {
        echo "No feeds found at remote account\n";
        die;
    }
 
    foreach ($feeds as $feed)
    {
        $feed->userid = $local_emoncms_userid;
        if ($link_to_local_emoncms) register_emoncms_feed($mysqli,$redis,$feed);
        
        if ($feed->engine==0 && $mysqli) {
            import_mysql($feed,$remote_server,$remote_apikey,$mysqli);
        }
        
        if ($feed->engine==1 && $feed->datatype==1) {
            import_phptimestore($feed->id,$remote_server,$remote_apikey,$engines['phptimestore']['datadir']);
        }

        if ($feed->engine==2) {
            import_phptimeseries($feed->id,$remote_server,$remote_apikey,$engines['phptimeseries']['datadir']);
        }
        
        if ($feed->engine==5) {
            import_phpfina($feed->id,$remote_server,$remote_apikey,$engines['phpfina']['datadir']);
        }

        if ($feed->engine==6) {
            import_phpfiwa($feed->id,$remote_server,$remote_apikey,$engines['phpfiwa']['datadir']);
        }

        if ($feed->engine==4 && $feed->datatype==1) {
            import_phptimestore($feed->id,$remote_server,$remote_apikey,$engines['phptimestore']['datadir']);
        }
    }
    
    if ($link_to_local_emoncms) reload_emoncms_feeds($mysqli,$redis,$local_emoncms_userid);
       
