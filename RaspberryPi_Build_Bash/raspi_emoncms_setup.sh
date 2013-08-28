#!/bin/bash -x
echo " "
echo "OpenEnergyMonitor.org Raspberry Pi Emoncms Install Script - created Aug 2013"
echo " "
echo "Assume starting with stock Raspian Wheezy - tested with 26-07-2013"
echo " "
echo "IMPORTANT - this script requires a root file system >2GB, if your SD card root partition is 2GB or less stop now and download and run wget http://raw.github.com/dweeber/rpiwiggle/master/rpi-wiggle to expand root to fill SD card (mins a little). Current SD card size:"
echo " "
df -h
echo " "
read -p 'If your SD card root partition is > 2GB press [Enter] to continue or [Ctrl + C] to exit'

hostname="emoncmspi"
DISABLE_LOGS=1

echo "Updating.."
apt-get update -y
echo "Update..done!"

echo "Intalling Mysql....."
echo "Create a MYSQL root password...make note of this, you will need it later!"
apt-get install -y mysql-server mysql-client
echo "Mysql intall compleate!"

echo "Intalling apache2....."
apt-get install -y apache2
echo "apache2 intall compleate!"

echo "Intalling php....."
apt-get install -y php5 libapache2-mod-php5
apt-get install -y php5-mysql
echo "php intall compleate!"

echo "Enable mod rewrite.."
a2enmod rewrite
cp /etc/apache2/sites-enabled/000-default /etc/apache2/sites-enabled/000-default_backup -n
sed 's/AllowOverride None/AllowOverride All/' /etc/apache2/sites-enabled/000-default_backup >/etc/apache2/sites-enabled/000-default

echo "Enable mod rewrite done!"

if [ $DISABLE_LOGS -eq 1 ] 
then 
	echo "Turn off apache logs.."
	cp /etc/apache2/apache2.conf /etc/apache2/apache2.conf_backup -n
	sed -e 's\ErrorLog ${APACHE_LOG_DIR}/error.log\ErrorLog /dev/null\' -e 's\LogLevel warn\# LogLevel warn\' /etc/apache2/apache2.conf_backup >/etc/apache2/apache2.conf
	cp /etc/apache2/conf.d/other-vhosts-access-log /etc/apache2/conf.d/other-vhosts-access-log_backup -n
	sed 's\CustomLog ${APACHE_LOG_DIR}/other_vhosts_access.log vhost_combined\#CustomLog ${APACHE_LOG_DIR}/other_vhosts_access.log vhost_combined\' /etc/apache2/conf.d/other-vhosts-access-log_backup >/etc/apache2/conf.d/other-vhosts-access-log
	cp /etc/apache2/sites-enabled/000-default /etc/apache2/sites-enabled/000-default_backup2 -n
	sed -e 's\ErrorLog ${APACHE_LOG_DIR}/error.log\# ErrorLog ${APACHE_LOG_DIR}/error.log\' -e 's\LogLevel warn\# LogLevel warn\' -e 's\CustomLog ${APACHE_LOG_DIR}/access.log combined\# CustomLog ${APACHE_LOG_DIR}/access.log combined\' /etc/apache2/sites-enabled/000-default_backup2 >/etc/apache2/sites-enabled/000-default
	echo "Turn off apache logs..done!"
fi

echo "Setting Pi Local Network Hostname to "$hostname
cp /etc/hostname /etc/hostname_backup -n
sed "s/raspberrypi/$hostname/" /etc/hostname_backup >/etc/hostname
cp /etc/hosts /etc/hosts_backup -n
sed "s/raspberrypi/$hostname/" /etc/hosts_backup >/etc/hosts

echo "Installing Git.."
apt-get install -y git-core
echo "Installing Git..done!"

echo "Installing emoncms core.."
cd /var/
chown pi www
cd www
git clone https://github.com/emoncms/emoncms.git
echo "Installing emoncms core..done!"

echo "Installing emoncms Raspberry Pi Module.."
cd /var/www/emoncms/Modules 
git clone https://github.com/emoncms/raspberrypi.git
echo "Installing emoncms Raspberry Pi Module..done!"

echo "Create an emoncms MYSQL database.."
echo "enter msql root user password.."
mysql -uroot -p -e "CREATE DATABASE IF NOT EXISTS emoncms;"
echo "emoncms MYSQL database..done"

echo "Set emoncms database settings.."
cp /var/www/emoncms/default.settings.php /var/www/emoncms/settings_backup.php -n
sed -e 's\$username = "";\$username = "root";\' -e 's\$password = "";\$password = "raspberry";\' -e 's\$database = "";\$database = "emoncms";\'  /var/www/emoncms/settings_backup.php > /var/www/emoncms/settings.php
echo "Set emoncms database settings..done"

echo "RFM12BPi Setup"
cp /boot/cmdline.txt /boot/cmdline_backup.txt -n
sed 's\console=ttyAMA0,115200 kgdboc=ttyAMA0,115200 console=tty1 root=/dev/mmcblk0p2\console=tty1 root=/dev/mmcblk0p2\' /boot/cmdline_backup.txt >/boot/cmdline.txt
cp /etc/inittab /etc/inittab_backup -n
sed 's\T0:23:respawn:/sbin/getty -L ttyAMA0 115200 vt100\#T0:23:respawn:/sbin/getty -L ttyAMA0 115200 vt100\' /etc/inittab_backup > /etc/inittab

echo "Install rfm12pi gateway service"
apt-get install php-pear php5-dev -y
apt-get install minicom -y
pecl install channel://pecl.php.net/dio-0.0.6
cp /etc/php5/cli/php.ini /etc/php5/cli/php_backup.ini -n


echo "add dio.so as a dynamic extension to serial php library.."
cp /etc/php5/cli/php.ini /etc/php5/cli/php_backup.ini -n
sed 's/;   extension=modulename.extension/extension=dio.so/' /etc/php5/cli/php_backup.ini > /etc/php5/cli/php.ini

echo "Install rfm12piphp gateway service"
cp /var/www/emoncms/Modules/raspberrypi/rfm12piphp /etc/init.d/
chmod 755 /etc/init.d/rfm12piphp
update-rc.d rfm12piphp defaults

echo "Add redirect index.php in /var/www"


echo "Create index.php file in webserver root folder with re-direct to emoncms"
cat > /var/www/index.php << EOF
<?php header('Location: ../emoncms'); ?>
<html><body><h1>Welcome</h1>
<p><a href="emoncms" >Goto Emoncms</a></p>
</body></html>
EOF

echo "rename index.html if exists"
cp /var/www/index.html /var/www/old_index.html -n 
rm /var/www/index.html

 


