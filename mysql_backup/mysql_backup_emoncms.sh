#!/bin/sh
# -u 'username' = your emoncms MYSQL username (default is emoncms)
# -p'password' = your emoncms MYSQL password
# 'database' = the name of your emoncms MYSQL database (default is emoncms)
# Replace '/path/to/' with the absolute path to where your backup will be stored
# Make the file executable - $ chmod +x mysql_backup_emoncms.sh
# To run - ./mysql_backup_emoncms.sh from the install directory, or via CRON/NodeRed
sudo service emonhub stop
mysqldump --lock-tables -u emoncms -ppassword database >  /path/to/emoncms_backup_`date +"%d%m%Y"`.sql
sudo service emonhub start
