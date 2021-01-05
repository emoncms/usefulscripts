# Python script to import UK Grid Carbon Intensity data into emoncms

Downloads from: https://carbon-intensity.github.io/api-definitions/#carbon-intensity-api-v2-0-0

This script can be ran daily from cron to pull in the latest data automatically. The script requests new data since the last valid reading.

## Setup

1\. Create config file from default:

    cp default.carbonintensity.conf carbonintensity.conf
    
2\. Enter emoncms write apikey

    nano carbonintensity.conf

3\. Run script for the first time to pull in data:

    python3 carbonintensity.py
    
4\. Run script from crontab every day to pull in latest data:

    15 2 * * * /usr/bin/python3 /opt/emoncms/modules/usefulscripts/carbonintensity/carbonintensity.py > /dev/null 2>&1
    
*Set to 2:15am here, specific time not crucial but good to randomise to reduce load on carbon intensity server*
