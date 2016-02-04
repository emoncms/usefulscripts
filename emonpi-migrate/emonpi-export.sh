#!/bin/bash

date=$(date +"%Y-%m-%d")
echo $date

image_version=$(ls /boot | grep emonSD)
# Check first 14 characters of filename
if [[ "${image_version:0:14}" == "emonSD-17Jun2015" ]]
then
  image="old"
  echo "$image image"
else
  image="new"
  echo "$image image"
fi

if [[ "${image_version:0:6}" == "emonSD" ]]
then
    echo "Image version: $image_version"
else
    echo "Non OpenEnergyMonitor offical emonSD image, no gurantees this script will work :-/"
    read -p "Press any key to continue...or CTRL+C to exit " -n1 -s
fi

cd /home/pi/data

sudo service feedwriter stop

if [[ $image == "old" ]]
then
  mysqldump -u root -praspberry emoncms > /home/pi/data/emoncms.sql
else
  mysqldump -u root -pemonpimysql2016 emoncms > /home/pi/data/emoncms.sql
fi

tar -cvzf backup-$date.tar.gz emoncms.sql phpfina phptimeseries emonhub.conf emoncms.conf

sudo service feedwriter start

echo "backup saved /home/pi/data/backup-$date.tar.gz"
echo "done"
date
