# SDS011 Nova PM Sensor to Emoncms bridge

e.g https://www.amazon.co.uk/gp/product/B07911ZY9W


## Run using docker (Recomended)

Tested on `emonSD-17Oct29`, Ubuntu 18.04 and Synology DSM6

   ```
   docker build -t dust .
   docker run --privileged --env-file=env dust
   ```
   
Run in the background on boot and always restart

   `docker run -d --privileged --restart=always --env-file=env dust`
   
   
****

## Run locally 

Copy the enviroment file:

   `cp default.env env`

Edit your enviroment file to set your mqtt / emoncms http settings:

   `nano env`

Export enviroment variables:

   `export $(xargs <env)`

Install python reqs:

   `pip install pyserial paho-mqtt`

Test the script:

   `./SDS0111`
    
Install the SDS011 service:

    sudo ln -s /opt/emoncms/modules/usefulscripts/SDS011/SDS011.service /lib/systemd/system
    sudo systemctl daemon-reload
    sudo systemctl enable SDS011.service
    sudo systemctl start SDS011
    
Check service status:

    sudo systemctl status SDS011






