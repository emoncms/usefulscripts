#!/usr/bin/env python3

# Fetches the MPAN and Serial number from the Octopus API
# https://api.octopus.energy/v1/accounts/<account-number>/

import sys, os, requests, json
from datetime import datetime
from configobj import ConfigObj

script_path = os.path.dirname(os.path.realpath(__file__))
settings = ConfigObj(script_path+"/agile.conf", file_error=True)

# Step 1: Fetch account details
url = "https://api.octopus.energy/v1/accounts/%s/" % (settings['octopus']['account_number'])
result = requests.get(url,auth=(settings['octopus']['agile_apikey'],''))
data = json.loads(result.text)

if not data: sys.exit(0)

# List all the MPANs and Serial numbers
for property in data['properties']:
    print (f"Property: {property['address_line_1']}, {property['postcode']}")
    for meter in property['electricity_meter_points']:
        print(f"MPAN: {meter['mpan']}, Serial: {meter['meters'][0]['serial_number']}")

# Print most recent tariff
most_recent_agreement = property['electricity_meter_points'][0]['agreements'][-1]
tariff = most_recent_agreement['tariff_code']
valid_from = most_recent_agreement['valid_from']
valid_to = most_recent_agreement['valid_to']
print(f"Tariff: {tariff}, Valid from: {valid_from}, Valid to: {valid_to}")