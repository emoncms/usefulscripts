#!/usr/bin/env python3

import sys, os, requests, json
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

# Agile request parameters
params = {'page':1,'order_by':'period','page_size':25000}

# Step 2: Fetch feed meta data to find last data point time and value
result = requests.get(settings['emoncms']['server']+"/feed/getmeta.json",params={'id':feedid,'apikey':settings['emoncms']['apikey']})
meta = json.loads(result.text)
print("Feed meta data:\t\t"+result.text)

if meta['npoints']>0:
    end_time = meta['start_time'] + (meta['interval'] * meta['npoints'])
    params['period_from'] = datetime.fromtimestamp(end_time).astimezone().isoformat()
    print("Request from:\t\t"+params['period_from'])

# Step 3: Request history from Octopus
url = "https://api.octopus.energy/v1/electricity-meter-points/%s/meters/%s/consumption/" % (settings['octopus']['mpan'],settings['octopus']['serial_number'])
result = requests.get(url,params=params,auth=(settings['octopus']['agile_apikey'],''))
data = json.loads(result.text)

if not data: sys.exit(0)
if not 'results' in data: sys.exit(0)

dp_received = len(data['results'])
print("Number of data points:\t%s" % dp_received);

# Step 4: Process history into data array for emoncms
data_out = []
for dp in data['results']:
    time = int(datetime.timestamp(datetime.strptime(dp['interval_start'],"%Y-%m-%dT%H:%M:%S%z")))
    value = dp['consumption']
    print(dp['interval_start']+" "+str(value))
    data_out.append([time,value])

# Step 5: Send data to emoncms
if len(data_out):
    print("Posting data to emoncms")
    result = requests.post(settings['emoncms']['server']+"/feed/insert.json",params={'id':feedid,'apikey':settings['emoncms']['apikey'],'skipbuffer':1},data={'data':json.dumps(data_out)})
