import requests
from datetime import datetime
import time
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

end_time = 0

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
else:
    # If the feed exists get the last data point
    url = f"{host}/feed/getmeta.json?id={feedid}&apikey={apikey}"
    response = requests.get(url)
    if response.status_code == 200:
        meta = response.json()
        if meta['npoints'] > 0:
            end_time = meta['start_time'] + (meta['interval'] * meta['npoints'])

# Calculate number of half hours since last data point
now = time.time()
diff = now - end_time
half_hours = int(diff / 1800)
page_size = half_hours

if page_size > 1500:
    page_size = 1500

if page_size < 100:
    page_size = 100

print (f"Number of half hours since last data point: {half_hours}, page size: {page_size}")

data = []

# start from page 1 and go through all pages
page = 1
max_page_load = 30
while True:

    complete = False

    print (f"Fetching page {page}")
    url = f"https://api.octopus.energy/v1/products/{tariff_name}/electricity-tariffs/E-1R-{tariff_name}-{gsp_id}/standard-unit-rates/?page={page}&page_size={page_size}"
    response = requests.get(url)

    if response.status_code == 200:
        result = response.json()

        if result is not None and 'results' in result:

            print (f"Number of data points: {len(result['results'])}")
            if len(result['results']) == 0:
                complete = True
                break

            for row in result['results']:
                date = datetime.fromisoformat(row['valid_from'].replace('Z', '+00:00'))
                timestamp = int(date.timestamp())

                if timestamp < end_time:
                    complete = True
                    break

                data.append([int(date.timestamp()), row['value_exc_vat']])
        else:
            complete = True
            break
    else:
        complete = True
        break

    if complete:
        break

    page += 1
    if page > max_page_load:
        break
                


# print number of data points
print(f"Number of data points: {len(data)}")

# sys.exit(0)

if len(data):
    # sort by timestamp asc
    data.sort(key=lambda x: x[0])

    # Send data to emoncms feed/post API
    url = f"{host}/feed/post.json?id={feedid}&apikey={apikey}"
    response = requests.post(url, data={'data': json.dumps(data)})

    if response.status_code == 200:
        print("Data uploaded successfully")
    else:
        print("Error uploading data")
        print(response.text)

