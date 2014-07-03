# Useful scripts

Useful scripts for managing your emoncms installation.

## Replication/Backup

For emoncms version 8 replication use replication/import_v8

    php import_v8.php
    
This tool needs to be tested again with the latest version of emoncms v8.2.7

## Data recovery

If backing up feed data via the normal directory copy method fails due to a disk problem, this data recovery tool may help you extract your feed data skipping the problematic sector on your disk. See forum post here for further information: [Data Recovery forum post](http://openenergymonitor.org/emon/node/5213)

To use the data recovery tool, open recover.php and set both source and target directories for the data to recover. Run the recover tool from terminal with:

    php recover.php
    
## Integrity check

A tool for checking the integrity of your emoncms phpfiwa, phpfina, phptimeseries and phptimestore feeds. Run the integrity check tool from terminal with:

    php integritycheck.php
