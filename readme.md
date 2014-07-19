# Useful scripts

Useful scripts for managing your emoncms installation.

## Backup/Replication

This is a tool for backing up the data in an emoncms.org account or other remote emoncms server to a local computer. It can be used with or without a local installation of emoncms. To use, open Backup/backup.php in a text editor. 

- Set $remote_server and $remote_apikey to correspond to the remote emoncms account you wish to download from.
- Set $link\_to\_local\_emoncms to true if you wish to access your data within a local installation of emoncms. Set $local\_emoncms\_location and $local\_emoncms\_userid to link to your local emoncms installation.
- Set $link\_to\_local\_emoncms to false if you just want to download the data without linking to a local emoncms install (non-mysql data only). Set $dir to the directory on your computer you wish to download the data. Manually create the folders: phpfina, phpfiwa, phptimeseries, phptimestore within this folder.

- Run the backup script from terminal with:

    php backup.php

Tested with emoncms.org (v8.0.9: 4 July 2014), and local emoncms v8.2.8

## Data recovery

If backing up feed data via the normal directory copy method fails due to a disk problem, this data recovery tool may help you extract your feed data skipping the problematic sector on your disk. See forum post here for further information: [Data Recovery forum post](http://openenergymonitor.org/emon/node/5213)

To use the data recovery tool, open recover.php and set both source and target directories for the data to recover. Run the recover tool from terminal with:

    php recover.php
    
## Integrity check

A tool for checking the integrity of your emoncms phpfiwa, phpfina, phptimeseries and phptimestore feeds. To use, open recover.php and set the engine data directories. Run the recover tool from terminal with:

    php integritycheck.php
    
## Covert data

There are two scripts for converting phpfiwa or phptimestore to phpfina. PHPFina has a much lower write load when used as the storage engine on SD Cards. Using these conversion scripts you can convert your data to PHPFina so that it can be used with the new SD Card branch of emoncms called 'bufferedwrite'

    phpfiwa_to_phpfina.php
    phptimestore_to_phpfina.php
    
Set the source and target directory as well as emoncms install location and then run as above.
