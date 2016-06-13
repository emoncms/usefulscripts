<?php

  /*
  
  Emoncms Engines
  
  class Engine {
    const MYSQL = 0;
    const TIMESTORE = 1;     // Depreciated
    const PHPTIMESERIES = 2;
    const GRAPHITE = 3;      // Not included in core
    const PHPTIMESTORE = 4;  // Depreciated
    const PHPFINA = 5;
    const PHPFIWA = 6;
    const VIRTUALFEED = 7;   // Virtual feed, on demand post processing
    const MYSQLMEMORY = 8;   // Mysql with MEMORY tables on RAM. All data is lost on shutdown 
    const REDISBUFFER = 9;   // (internal use only) Redis Read/Write buffer, for low write mode
  }
  
  */
    print "-------------------------------------\n";
    print "Emoncms Feed Conversion Check Script\n";
    print "-------------------------------------\n";

    define('EMONCMS_EXEC', 1);
    
    chdir("/var/www/emoncms");
    require "process_settings.php";
    $mysqli = @new mysqli($server,$username,$password,$database);
    $result = $mysqli->query("SELECT * FROM feeds");
    
    while($row = $result->fetch_object())
    {
        print "feed:".$row->id." engine:";
        
        if ($row->engine==0) {
            print "MYSQL";
        }
        if ($row->engine==1) {
            print "TIMESTORE";
        }
        if ($row->engine==2) {
            print "PHPTIMESERIES ";
            $datadir = "/var/lib/phptimeseries/";
            if (isset($feed_settings["phptimeseries"])) $datadir = $feed_settings["phptimeseries"]["datadir"];
            print "datadir:".$datadir;
            print " datafile:"; if (file_exists($datadir."feed_".$row->id.".MYD")) print "yes"; else print "no";
        }
        if ($row->engine==3) {
            print "GRAPHITE (Conversion not supported)";
        }
        if ($row->engine==4) {
            print "PHPTIMESTORE ";
            $datadir = "/var/lib/phptimestore/";
            if (isset($feed_settings["phptimestore"])) $datadir = $feed_settings["phptimestore"]["datadir"];
            print "datadir:".$datadir;
        }
        if ($row->engine==5) {
            print "PHPFINA ";
            $datadir = "/var/lib/phpfina/";
            if (isset($feed_settings["phpfina"])) $datadir = $feed_settings["phpfina"]["datadir"];
            print "datadir:".$datadir;
            print " metafile:"; if (file_exists($datadir.$row->id.".meta")) print "yes"; else print "no";
            print " datafile:"; if (file_exists($datadir.$row->id.".dat")) print "yes"; else print "no";
        }
        if ($row->engine==6) {
            print "PHPFIWA ";
            $datadir = "/var/lib/phpfiwa/";
            if (isset($feed_settings["phpfiwa"])) $datadir = $feed_settings["phpfiwa"]["datadir"];
            print "datadir:".$datadir;
            print " metafile:"; if (file_exists($datadir.$row->id.".meta")) print "yes"; else print "no";
            print " datafile:"; if (file_exists($datadir.$row->id."_0.dat")) print "yes"; else print "no";
        }
        
        if ($row->engine==7) print "VIRTUALFEED";
        if ($row->engine==8) print "MYSQLMEMORY";
        if ($row->engine==9) print "REDISBUFFER";
        
        print "\n";
    }
