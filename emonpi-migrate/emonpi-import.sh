#!/bin/bash

sudo service emonhub stop
sudo service emoncms-nodes-service stop
sudo service feedwriter stop

sudo tar xvfz backup.tar.gz -C /
mysql -u root -praspberry emoncms < /home/pi/data/emoncms.sql

mv /home/pi/data/emonhub.conf /home/pi/data/old.emonhub.conf
cp /home/pi/emonhub/conf/emonhub.conf /home/pi/data/emonhub.conf

mv /home/pi/data/emoncms.conf /home/pi/data/old.emoncms.conf

sudo touch /home/pi/data/emoncms.conf
sudo chown pi:www-data /home/pi/data/emoncms.conf
sudo chmod 664 /home/pi/data/emoncms.conf

redis-cli "flushall"

sudo service emonhub start
sudo service emoncms-nodes-service start
sudo service feedwriter start
