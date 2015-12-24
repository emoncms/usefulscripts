#!/bin/bash

sudo service emonhub stop
sudo service emoncms-nodes-service stop
sudo service feedwriter stop

sudo tar xvfz backup.tar.gz -C /
mysql -u root -praspberry emoncms < /home/pi/data/emoncms.sql

mv /home/pi/data/emonhub.conf /home/pi/data/old.emonhub.conf
cp /home/pi/emonhub/emonhub.conf /home/pi/data/emonhub.conf

redis-cli "flushall"

sudo service emonhub start
sudo service emoncms-nodes-service start
sudo service feedwriter start
