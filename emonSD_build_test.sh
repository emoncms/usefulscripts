# --------------------------------------------------------------------------------
# RaspberryPi Strech Build Script
# Emoncms, Emoncms Modules, EmonHub & dependencies
#
# Tested with: Raspbian Strech
# Date: 19 March 2019
#
# Status: Work in Progress
# --------------------------------------------------------------------------------

#! /bin/sh

USER=pi

sudo apt-get update -y
sudo apt-get upgrade -y

# Needed on stock raspbian lite 19th March 2019
sudo apt --fix-broken install

# Emoncms install process from:
# https://github.com/emoncms/emoncms/blob/master/docs/RaspberryPi/readme.md
sudo apt-get install -y apache2 mariadb-server mysql-client php7.0 libapache2-mod-php7.0 php7.0-mysql php7.0-gd php7.0-opcache php7.0-curl php-pear php7.0-dev php7.0-mcrypt php7.0-common redis-server php-redis git build-essential php7.0-mbstring libmosquitto-dev mosquitto python-pip python-dev

# Install the pecl dependencies
sudo pecl channel-update pecl.php.net
printf "\n" | sudo pecl install redis Mosquitto-alpha

# Add redis to php mods available 
printf "extension=redis.so" | sudo tee /etc/php/7.0/mods-available/redis.ini 1>&2
sudo phpenmod redis

# Add mosquitto to php mods available
printf "extension=mosquitto.so" | sudo tee /etc/php/7.0/mods-available/mosquitto.ini 1>&2
sudo phpenmod mosquitto

# Disable mosquitto persistance
#   sudo nano mosquitto.conf
#   Set persistence false

# Enable apache mod rewrite
# MANUAL FIX for mod rewrite in /etc/apache2/apache2.conf
# For <Directory /> and <Directory /var/www/> change AllowOverride None to AllowOverride All. 
# This should be on, or very close to lines 161 and 172 of /etc/apache2/apache2.conf
sudo a2enmod rewrite
sudo service apache2 restart

# Emoncms install
# Give pi user ownership over /var/www/ folder
sudo chown $USER /var/www
cd /var/www && git clone -b stable https://github.com/emoncms/emoncms.git

# Create logfile
sudo touch /var/log/emoncms.log
sudo chmod 666 /var/log/emoncms.log

# --------------------------------------------------------------------------------
# Setup the Mariadb server (MYSQL)
# --------------------------------------------------------------------------------
# Secure mysql
sudo mysql -e "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1'); DELETE FROM mysql.user WHERE User=''; DROP DATABASE IF EXISTS test; DELETE FROM mysql.db WHERE Db='test' OR Db='test\_%'; FLUSH PRIVILEGES;"
# Create the emoncms database using utf8 character decoding:
sudo mysql -e "CREATE DATABASE emoncms DEFAULT CHARACTER SET utf8;"
# Add emoncms database, set user permissions
sudo mysql -e "CREATE USER 'emoncms'@'localhost' IDENTIFIED BY 'emonpiemoncmsmysql2016'; GRANT ALL ON emoncms.* TO 'emoncms'@'localhost'; flush privileges;"

# Create data repositories for emoncms feed engines:
sudo mkdir /var/lib/{phpfiwa,phpfina,phptimeseries}
sudo chown www-data:root /var/lib/{phpfiwa,phpfina,phptimeseries}

# Configure emoncms database settings
# Make a copy of default.settings.php and call it settings.php:
cd /var/www/emoncms && cp default.emonpi.settings.php settings.php
# MODIFY TO USE /var/lib/phpfina etc locations

# Create a symlink to reference emoncms within the web root folder:
cd /var/www/html && sudo ln -s /var/www/emoncms

# Redirect
echo "<?php header('Location: ../emoncms'); ?>" > /home/pi/index.php
sudo mv /home/pi/index.php /var/www/html/index.php
sudo rm /var/www/html/index.html
# --------------------------------------------------------------------------------
# Install Emoncms Services
# --------------------------------------------------------------------------------
# Emoncms MQTT
sudo ln -s /var/www/emoncms/scripts/services/emoncms_mqtt/emoncms_mqtt.service /lib/systemd/system
sudo systemctl enable emoncms_mqtt.service
sudo systemctl start emoncms_mqtt.service
# Feedwriter
sudo ln -s /var/www/emoncms/scripts/services/feedwriter/feedwriter.service /lib/systemd/system
sudo systemctl enable feedwriter.service
sudo systemctl start feedwriter.service
# Service runner
sudo pip install redis
sudo ln -s /var/www/emoncms/scripts/services/service-runner/service-runner.service /lib/systemd/system
sudo systemctl enable service-runner.service
sudo systemctl start service-runner.service

# --------------------------------------------------------------------------------
# Install Emoncms Modules
# --------------------------------------------------------------------------------
cd /var/www/emoncms/Modules
git clone https://github.com/emoncms/config.git
git clone https://github.com/emoncms/graph.git
git clone https://github.com/emoncms/dashboard.git
git clone https://github.com/emoncms/device.git
git clone https://github.com/emoncms/app.git
git clone https://github.com/emoncms/wifi.git

cd /home/pi/
git clone https://github.com/emoncms/backup.git
git clone https://github.com/emoncms/postprocess.git
git clone https://github.com/emoncms/usefulscripts.git
git clone https://github.com/emoncms/demandshaper.git
git clone https://github.com/emoncms/sync.git
# Symlink emoncms module folders here...

# --------------------------------------------------------------------------------
# Install EmonHub
# --------------------------------------------------------------------------------
sudo systemctl stop serial-getty@ttyAMA0.service
sudo systemctl disable serial-getty@ttyAMA0.service

cd /home/pi
git clone https://github.com/openenergymonitor/emonhub.git
mkdir data
sudo apt-get install -y python-serial python-configobj python-requests
sudo pip install paho-mqtt
cd /home/pi/emonhub
sudo ./install.systemd
sudo systemctl start emonhub.service

# --------------------------------------------------------------------------------
# EmonPi repo
# --------------------------------------------------------------------------------
cd /home/pi/
git clone https://github.com/openenergymonitor/emonpi.git

# Enable service-runner update
# emonpi update checks for image type and only runs with a valid image name file in the boot partition
sudo touch /boot/emonSD-30Oct18

# Try running emoncms Update
# !!!! avrdude and emonPiLCD still to install !!!! 

# --------------------------------------------------------------------------------
# Install log2ram, so that logging is on RAM to reduce SD card wear.
# Logs are written to disk every hour or at shutdown
# --------------------------------------------------------------------------------
curl -Lo log2ram.tar.gz https://github.com/azlux/log2ram/archive/master.tar.gz
tar xf log2ram.tar.gz
cd log2ram-master
chmod +x install.sh && sudo ./install.sh
cd ..
rm -r log2ram-master

# --------------------------------------------------------------------------------
# Manual steps to complete
# --------------------------------------------------------------------------------

# MANUAL FIX for mod rewrite in /etc/apache2/apache2.conf
# For <Directory /> and <Directory /var/www/> change AllowOverride None to AllowOverride All. 
# This should be on, or very close to lines 161 and 172 of /etc/apache2/apache2.conf

# Disable redis persistance
#   sudo nano /etc/redis/redis.conf
#   # save 900 1
#   # save 300 10
#   # save 60 10000
#   sudo service redis-server restart

# MODIFY emoncms/settings.php TO USE /var/lib/phpfina etc locations

# RaspberryPi Serial configuration
# disable Pi3 Bluetooth and restore UART0/ttyAMA0 over GPIOs 14 & 15;
#   sudo nano /boot/config.txt
# Add to the end of the file
#   dtoverlay=pi3-disable-bt
# We also need to stop the Bluetooth modem trying to use UART
#   sudo systemctl disable hciuart
# Remove console
#   sudo nano /boot/cmdline.txt
#   remove: console=serial0,115200

# Change emonhub logging level to WARNING

# Disable duplicate daemon.log logging to syslog
# sudo nano /etc/rsyslog.conf
# change syslog line to: *.*;auth,authpriv.none,daemon.none      -/var/log/syslog

# Disable apache2 access logs
# sudo nano /etc/apache2/sites-available/000-default.conf (comment access.log)
# sudo nano /etc/apache2/conf-available/other-vhosts-access-log.conf (comment CustomLog)

sudo reboot

# --------------------------------------------------------------------------------
# Optional steps:
# --------------------------------------------------------------------------------
# 1. Expand file-system (appears already expanded)
# 2. Change hostname 
# 3. Change password
