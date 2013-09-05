

echo "---------------------------------------------------"
echo "Installing timestore"
echo "---------------------------------------------------"
# Install timestore

cd /home/pi
git clone https://github.com/TrystanLea/timestore
cd timestore
sudo sh install
cd /home/pi

echo "---------------------------------------------------"
echo "Installing php5-curl"
echo "---------------------------------------------------"
# Install php curl
sudo apt-get update
sudo apt-get install php5-curl

# Update emoncms
cd /var/www/emoncms
git pull

# Create new settings.php file and copy settings over
cd /home/pi/usefulscripts/
sh set_emoncms_settings.sh

echo "---------------------------------------------------"
echo "Updating raspberrypi module"
echo "---------------------------------------------------"
# Update raspberrypi module

if [ -d "/var/www/emoncms/Modules/raspberrypi" ]; then
  echo "Updating raspberrypi module"
  cd /var/www/emoncms/Modules/raspberrypi
  git pull
  
  # Update raspberrypi init script
  sudo cp /var/www/emoncms/Modules/raspberrypi/rfm12piphp /etc/init.d/
  sudo chmod 755 /etc/init.d/rfm12piphp
  sudo update-rc.d rfm12piphp defaults

  sudo service rfm12piphp stop
  sudo service rfm12piphp start

else
  echo "Raspberrypi module is not installed"
fi


echo "---------------------------------------------------"
echo "Updating event module"
echo "---------------------------------------------------"
# Update event module

if [ -d "/var/www/emoncms/Modules/event" ]; then
  echo "Updating event module"
  cd /var/www/emoncms/Modules/event
  git pull
else
  echo "Event module is not installed"
fi


echo "---------------------------------------------------"
echo "Updating or installing converttotimestore module"
echo "---------------------------------------------------"
# Download converttotimestore

if [ -d "/var/www/emoncms/Modules/converttotimestore" ]; then
  echo "Updating converttotimestore module"
  cd /var/www/emoncms/Modules/converttotimestore
  git pull
else
  echo "Downloading converttotimestore module"
  cd /var/www/emoncms/Modules
  git clone https://github.com/emoncms/converttotimestore.git
fi
