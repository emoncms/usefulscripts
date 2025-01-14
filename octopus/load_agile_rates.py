import requests
from datetime import datetime
import sys
import json
import os
from configobj import ConfigObj

script_path = os.path.dirname(os.path.realpath(__file__))
settings = ConfigObj(script_path+"/agile.conf", file_error=True)

# Emoncms account details
host = settings['emoncms']['server']
apikey = settings['emoncms']['apikey']

# AGILE-18-02-21, AGILE-22-07-22, AGILE-22-08-31, AGILE-23-12-06, AGILE-VAR-22-10-19, AGILE-FLEX-22-11-25
tariff_name = settings['octopus']['tariff_name']

# Regions
"""
"A": "Eastern_England",
"B": "East_Midlands",
"C": "London",
"D": "Merseyside_and_Northern_Wales",
"E": "West_Midlands",
"F": "North_Eastern_England",
"G": "North_Western_England",
"H": "Southern_England",
"J": "South_Eastern_England",
"K": "Southern_Wales",
"L": "South_Western_England",
"M": "Yorkshire",
"N": "Southern_Scotland",
"P": "Northern_Scotland"
"""
gsp_id = settings['octopus']['gsp_id']

feed_name = f"{tariff_name}-{gsp_id}"

# Get feedid of agile rates feed from emoncms
url = f"{host}/feed/list.json?apikey={apikey}"
response = requests.get(url)

if response.status_code == 200:
    feeds = response.json()
    feedid = None
    for feed in feeds:
        if feed['name'] == feed_name:
            feedid = feed['id']
            break

# if feed not found, create a new feed
# https://emoncms.org/feed/create.json?tag=Octopus&name=AGILE-23-12-06-D&engine=5&options={"interval":1800}
if feedid is None:
    print ("Creating agile feed")
    options = { "interval": 1800 }
    # convert options to json
    options = json.dumps(options)
    url = f"{host}/feed/create.json?tag=Octopus&name={feed_name}&engine=5&options={options}&apikey={apikey}"
    print (url)

    response = requests.get(url)

    if response.status_code == 200:
        feedid = response.json()['feedid']

url = f"https://api.octopus.energy/v1/products/{tariff_name}/electricity-tariffs/E-1R-{tariff_name}-{gsp_id}/standard-unit-rates/"
response = requests.get(url)

if response.status_code == 200:
    result = response.json()

    if result is not None and 'results' in result:

        data = []
        for row in result['results']:
            date = datetime.fromisoformat(row['valid_from'].replace('Z', '+00:00'))
            data.append([int(date.timestamp()), row['value_exc_vat']])

        # sort by timestamp asc
        data.sort(key=lambda x: x[0])

        # Send data to emoncms feed/post API
        url = f"{host}/feed/post.json?id={feedid}&apikey={apikey}&data={data}"
        response = requests.get(url)

        if response.status_code == 200:
            print("Data uploaded successfully")