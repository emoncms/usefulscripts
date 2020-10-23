# Python script to import Octopus Agile consumption data into emoncms

This script can be ran daily from cron to pull in the latest data automatically. The script requests new data since the last valid reading.

## Setup

1\. Create config file from default:

    cd /opt/emoncms/modules/usefulscripts/octopus/
    cp default.agile.conf agile.conf
    
2\. Enter configuration including emoncms apikey and authentication and meter details from Octopus

    nano agile.conf

3\. Run script for the first time to pull in data:

    python3 agile.py
    
4\. Run script from crontab every morning to pull in latest data:

    30 9 * * * /usr/bin/python3 /opt/emoncms/modules/usefulscripts/octopus/agile.py > /dev/null 2>&1
    
*This example runs the script at 9:30am every morning, consider changing the exact timing to reduce simultaneous load on the octopus servers*
