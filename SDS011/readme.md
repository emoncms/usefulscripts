# SDS011 Nova PM Sensor to Emoncms bridge

Copy the enviroment file:

   cp default.env env

Edit your enviroment file to set your mqtt / emoncms http settings:

   nano env

Export enviroment file:

   export $(xargs <env)

Install python reqs:

   pip install pyserial paho-mqtt

Test the script:

   ./SDS0111
    
Install the SDS011 service:

    sudo ln -s /opt/emoncms/modules/usefulscripts/SDS011/SDS011.service /lib/systemd/system
    sudo systemctl daemon-reload
    sudo systemctl enable SDS011.service
    sudo systemctl start SDS011
    
Check service status:

    sudo systemctl status SDS011



## Run using docker 

   docker build -t dust .
   docker run --privileged --env-file=env dust

