# SDS011 Nova PM Sensor to Emoncms bridge

Edit SDS011.py, set emoncms hostname and apikey to match your system.

    emoncms_host = "http://localhost"
    emoncms_apikey = "APIKEY"
    emoncms_nodename = "SDS011"
    
Install the SDS011 service:

    sudo ln -s /opt/emoncms/modules/usefulscripts/SDS011/SDS011.service /lib/systemd/system
    sudo systemctl daemon-reload
    sudo systemctl enable SDS011.service
    sudo systemctl start SDS011
    
Check service status:

    sudo systemctl status SDS011
