# SDS011 Nova PM Sensor to Emoncms bridge
    
Install the SDS011 service:

    sudo ln -s /opt/emoncms/modules/usefulscripts/SDS011/SDS011.service /lib/systemd/system
    sudo systemctl daemon-reload
    sudo systemctl enable SDS011.service
    sudo systemctl start SDS011
    
Check service status:

    sudo systemctl status SDS011
