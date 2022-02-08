import requests, os, sys, json, struct

host = "https://emoncms.org"
username = "flatllanberis"
password = "flatllanberis"

def phpfina_get_meta(datadir,feedid):
    if os.path.isfile(datadir+str(feedid)+".meta"):
        fh = open(datadir+str(feedid)+".meta","rb")
        bytes = fh.read(16)
        if len(bytes)==16:
            tmp = struct.unpack("IIII",bytes)
            fh.close()
            meta = {'start_time': tmp[2], 'interval': tmp[3], 'npoints':0}
            
            if os.path.isfile(datadir+str(feedid)+".dat"):
                bytesize = os.stat(datadir+str(feedid)+".dat").st_size
                meta['npoints'] = int(bytesize/4.0)
            return meta
    return False

def phpfina_create_meta(datadir,feedid,meta):
    fh = open(datadir+str(feedid)+".meta","wb")
    fh.write(struct.pack("I",0))
    fh.write(struct.pack("I",0))
    fh.write(struct.pack("I",meta['start_time']))
    fh.write(struct.pack("I",meta['interval']))
    fh.close()

def download_file(filename,url,mode):
    size = 0
    with requests.get(url, stream=True) as r:
        r.raise_for_status()
        with open(filename, mode) as f:
            try:
                for chunk in r.iter_content(chunk_size=8192): 
                    f.write(chunk)
                    size += len(chunk)
            except Exception as e:
                print (e)
    return size

def phpfina_download(datadir,feedid,host,apikey):
    # 1. Get remote meta
    result = requests.get(host+"/feed/getmeta.json",params={'id':feedid,'apikey':apikey})
    meta = json.loads(result.text)
    
    if meta['npoints']==0: 
        return False

    # 2. Get local meta
    download_start = 0
    local_meta = phpfina_get_meta(datadir,feedid)
    if local_meta:
        if meta['start_time']!=local_meta['start_time']:
            print("Error: local phpfina meta start_time does not match remote")
            return False
        if meta['interval']!=local_meta['interval']:
            print("Error: local phpfina meta interval does not match remote")
            return False
        download_start = local_meta['npoints']*4

    # 3. Create meta file
    phpfina_create_meta(datadir,feedid,meta)
    # 4. Download dat file
    download_size = download_file(datadir+str(feedid)+".dat",host+"/feed/export.json?id="+str(feedid)+"&start="+str(download_start)+"&apikey="+apikey,'ab')
    print ("PHPFina id="+str(feedid)+" downloaded: "+str(download_size)+" bytes")

def phptimeseries_download(datadir,feedid,host,apikey):
    download_start = 0
    if os.path.isfile(datadir+"feed_"+str(feedid)+".MYD"):
        download_start = os.stat(datadir+"feed_"+str(feedid)+".MYD").st_size
    download_size = download_file(datadir+"feed_"+str(feedid)+".MYD",host+"/feed/export.json?id="+str(feedid)+"&start="+str(download_start)+"&apikey="+apikey,'ab')
    print ("PHPTimeSeries id="+str(feedid)+" downloaded: "+str(download_size)+" bytes")

# -------------------------------

result = requests.post(host+"/user/auth.json",data={'username':username,'password':password})
user_auth = json.loads(result.text)
apikey = user_auth['apikey_read']

if not os.path.exists(username):
    os.mkdir(username)

if not os.path.exists(username+"/phpfina"):
    os.mkdir(username+"/phpfina")
    
if not os.path.exists(username+"/phptimeseries"):
    os.mkdir(username+"/phptimeseries")
    
result = requests.get(host+"/feed/list.json",params={'apikey':apikey})
feeds = json.loads(result.text)

fh = open(username+"/feeds.json","w")
fh.write(json.dumps(feeds))
fh.close()
 
for f in feeds:
    if int(f['engine'])==2:
        phptimeseries_download(username+"/phptimeseries/",f['id'],host,apikey) 
    if int(f['engine'])==5:
        phpfina_download(username+"/phpfina/",f['id'],host,apikey)
