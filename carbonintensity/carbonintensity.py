import sys, os, requests, json
from datetime import datetime, timedelta
from configobj import ConfigObj

script_path = os.path.dirname(os.path.realpath(__file__))
settings = ConfigObj(script_path+"/carbonintensity.conf", file_error=True)


# Step 1: Create feed via API call or use input interface in emoncms to create manually
result = requests.get(settings['emoncms']['server']+"/feed/getid.json",params={'tag':settings['emoncms']['tag'],'name':settings['emoncms']['name'],'apikey':settings['emoncms']['apikey']})
if  not result.text:
    # Create feed
    params = {'tag':settings['emoncms']['tag'],'name':settings['emoncms']['name'],'datatype':1,'engine':5,'options':'{"interval":1800}','unit':'','apikey':settings['emoncms']['apikey']}
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
print(result.text)
meta = json.loads(result.text)
print("Feed meta data:\t\t"+result.text)

if meta['npoints']>0:
    end_time = meta['start_time'] + (meta['interval'] * meta['npoints'])
    start = datetime.fromtimestamp(end_time)
    print("Request from:\t\t"+start.isoformat())
else:
    start = datetime(2017,10,1) # First available date

headers = {
  'Accept': 'application/json'
}

while start<datetime.now():

    dt_start = start.isoformat()
    dt_end = (start + timedelta(14)).isoformat()

    r = requests.get('https://api.carbonintensity.org.uk/intensity/'+dt_start+'/'+dt_end, params={}, headers = headers)
    r = r.json()

    # Step 4: Process history into data array for emoncms
    data_out = []
    for hh in r['data']:
        datetimestr = hh['from']
        time = int(datetime.timestamp(datetime.strptime(datetimestr,"%Y-%m-%dT%H:%MZ")))
        intensity = hh['intensity']['actual']
        if intensity!=None: 
            data_out.append([time,intensity])
        print(str(time)+" "+datetimestr+" "+str(intensity))

    # Step 5: Send data to emoncms
    if len(data_out):
        print("Posting data to emoncms")
        result = requests.post(settings['emoncms']['server']+"/feed/insert.json",params={'id':feedid,'apikey':settings['emoncms']['apikey']},data={'data':json.dumps(data_out)})
        print (result.text)
        
    print ("----------------")
    
    start = start + timedelta(14)
