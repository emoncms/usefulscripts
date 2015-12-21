#!/bin/bash

cd /home/pi/data

sudo service feedwriter stop

mysqldump -u root -praspberry emoncms > /home/pi/data/emoncms.sql
tar -cvzf backup.tar.gz /home/pi/data/emoncms.sql /home/pi/data/phpfina /home/pi/data/phptimeseries /home/pi/data/emonhub.conf /home/pi/data/emoncms.conf

sudo service feedwriter start
