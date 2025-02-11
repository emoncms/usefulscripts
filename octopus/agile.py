#!/usr/bin/env python3

import sys, os, requests, json, time
from datetime import datetime
from configobj import ConfigObj

script_path = os.path.dirname(os.path.realpath(__file__))
settings = ConfigObj(script_path+"/agile.conf", file_error=True)

# Step 1: Create feed via API call or use input interface in emoncms to create manually
result = requests.get(settings['emoncms']['server']+"/feed/getid.json",params={'tag':settings['emoncms']['tag'],'name':settings['emoncms']['name'],'apikey':settings['emoncms']['apikey']})
if  not result.text:
    # Create feed
    params = {'tag':settings['emoncms']['tag'],'name':settings['emoncms']['name'],'datatype':1,'engine':5,'options':'{"interval":1800}','unit':'kWh','apikey':settings['emoncms']['apikey']}
    result = requests.get(settings['emoncms']['server']+"/feed/create.json",params)
    result = json.loads(result.text)
    if result['success']:
        feedid = int(result['feedid'])
        print("Emoncms feed created:\t"+str(feedid))
    else:
        print("Error creating feed")
        sys.exit(0)
else:
    feedid = int(result.text)
    print("Using emoncms feed:\t"+str(feedid))



# Step 2: Fetch feed meta data to find last data point time and value
result = requests.get(settings['emoncms']['server']+"/feed/getmeta.json",params={'id':feedid,'apikey':settings['emoncms']['apikey']})
meta = json.loads(result.text)
print("Feed meta data:\t\t"+result.text)


end_time = 0
if meta['npoints']>0:
    end_time = meta['start_time'] + (meta['interval'] * meta['npoints'])
    # params['period_from'] = datetime.fromtimestamp(end_time).astimezone().isoformat()
    # print("Request from:\t\t"+params['period_from'])

# Calculate number of half hours since last data point
now = time.time()
diff = now - end_time
half_hours = int(diff / 1800)
page_size = half_hours

if page_size > 3000:
    page_size = 3000

if page_size < 100:
    page_size = 100

data = []

# Start fetching data from page 1
page = 1
max_page_load = 200
mpan = settings['octopus']['mpan']
serial_number = settings['octopus']['serial_number']

while True:
    print(f"Fetching page {page}")

    url = f"https://api.octopus.energy/v1/electricity-meter-points/{mpan}/meters/{settings['octopus']['serial_number']}/consumption/?page={page}&page_size={page_size}"
    response = requests.get(url, auth=(settings['octopus']['agile_apikey'], ''))

    if response.status_code == 200:
        result = response.json()

        if result and 'results' in result:
            print(f"- Number of data points: {len(result['results'])}")

            if len(result['results']) == 0:
                break  # No more data to fetch

            # Print from to dates
            print (f"- From: {result['results'][0]['interval_start']} to {result['results'][-1]['interval_start']}")

            for row in result['results']:
                date = datetime.fromisoformat(row['interval_start'].replace('Z', '+00:00'))
                timestamp = int(date.timestamp())

                if timestamp < end_time:
                    max_page_load = 0
                    break  # Stop if we reach older data than required

                data.append([timestamp, row['consumption']])  # Store timestamp and consumption value
        else:
            break
    else:
        print(f"Error fetching data: {response.status_code}")
        print(response.text)
        break

    page += 1
    if page > max_page_load:
        break

# Print number of data points
print(f"Total number of data points: {len(data)}")

# If data exists, sort and send to EmonCMS
if data:
    data.sort(key=lambda x: x[0])  # Sort by timestamp in ascending order

    # Send data to EmonCMS
    url = f"{settings['emoncms']['server']}/feed/post.json?id={feedid}&apikey={settings['emoncms']['apikey']}"
    response = requests.post(url, data={'data': json.dumps(data)})

    if response.status_code == 200:
        print("Data uploaded successfully")
    else:
        print("Error uploading data")
        print(response.text)
