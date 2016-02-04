#!/bin/bash

echo "emonPi data-import script starting"
date

image_version=$(ls /boot | grep emonSD)
# Check first 16 characters of filename
image_date=${image_version:0:16}
if [[ "$image_date" == "emonSD-17Jun2015" ]]
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
    echo "Non OpenEnergyMonitor offical emonSD image, no gurantees this import will work :-/"
    read -p "Press any key to continue...or CTRL+C to exit " -n1 -s
fi


# Get latest backup filename
backup_filename=$((cd /home/pi/data && ls -t *.gz) | grep backup | head -1)
cd ~/
if [[ -z "$backup_filename" ]] #if backup does not exist (empty filename string)
then
    echo "backup does not exit..stoppping import"
    exit 1
else # if backup exists
  echo "backup found: $backup_filename starting import.."
  sudo tar xvfz /home/pi/data/$backup_filename -C /
  if [[ $image == "old" ]]
  then
    mysql -u root -praspberry emoncms < /home/pi/data/emoncms.sql
  else
    mysql -u emoncms -pemonpiemoncmsmysql2016 emoncms < /home/pi/data/emoncms.sql
  fi
fi


echo "backup settings emonhub.conf > ~/data/old.emohub.conf"
mv /home/pi/data/emonhub.conf /home/pi/data/old.emonhub.conf
mv /home/pi/data/emoncms.conf /home/pi/data/old.emoncms.conf



if [[ $image == "old" ]]
then    # Legacy image use emonhub.conf without MQTT authenitication
   echo "Start with fresh config: copy LEGACY default emonhub.conf"
   echo "/home/pi/emonhub/conf/old.default.emonhub.conf /home/pi/data/emonhub.conf"
   cp /home/pi/emonhub/conf/old.default.emonhub.conf /home/pi/data/emonhub.conf
else    # Newer Feb15+ image use latest emonhub.conf with MQTT node variable topic structure and MQTT authentication enabled
   echo "Start with fresh config: copy NEW default emonpi emonhub.conf"
   echo "cp /home/pi/emonhub/conf/emonpi.default.emonhub.conf /home/pi/data/emonhub.conf"
   cp /home/pi/emonhub/conf/emonpi.default.emonhub.conf /home/pi/data/emonhub.conf
fi



sudo touch /home/pi/data/emoncms.conf
sudo chown pi:www-data /home/pi/data/emoncms.conf
sudo chmod 664 /home/pi/data/emoncms.conf

echo "import done..restarting services"

redis-cli "flushall"
sudo service emonhub start
sudo service emoncms-nodes-service start
sudo service feedwriter start
echo "done"
date
rpi-rw
