#!/bin/bash

# If update flag does not exit then exit
if [ ! -f /tmp/emonpibackup ]; then
    exit
fi
sudo rm /tmp/emonpibackup

LOCK=/tmp/backuplock
if [ -f $LOCK ]; then
  echo Job is already running\!
  exit 6
fi
touch $LOCK

cd /home/pi/usefulscripts/emonpi-migrate
sh emonpi-export.sh

rm $LOCK

