# EmonPi export and import tool for backup and migration

The emonpi-export-wrapper.sh is to be used with the in-browser backup facility. 

Clicking on backup in emoncms first creates a flag on the emonpi tmp partition.
A cron job polls every minute to check if this flag exists and if it does starts the backup process.

To create the cron entry first open crontab with:

    $ crontab -e

and then add the following line

    * * * * * /home/pi/usefulscripts/emonpi-migrate/emonpi-export-wrapper.sh >> /home/pi/data/emonpibackup.log 2>&1
