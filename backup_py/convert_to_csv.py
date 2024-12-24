import os, json, struct
from pathlib import Path

username = ""

def phpfina_get_meta(datadir,feedid):
    if os.path.isfile(datadir+str(feedid)+".meta"):
        fh = open(datadir+str(feedid)+".meta","rb")
        tmp = struct.unpack("IIII",fh.read(16))
        fh.close()
        meta = {'start_time': tmp[2], 'interval': tmp[3], 'npoints':0}
        
        if os.path.isfile(datadir+str(feedid)+".dat"):
            bytesize = os.stat(datadir+str(feedid)+".dat").st_size
            meta['npoints'] = int(bytesize/4.0)
        return meta
    else:
        return False

def phpfina_convert(datadir,csvdir,feedid,tag,name):
    meta = phpfina_get_meta(datadir,feedid)
    csv_path = csvdir+str(feedid)+"-"+str(tag)+"-"+str(name)+".csv"
    csv_path= csv_path.replace(":", "-") # this is needed on windows at least because ":" are not allowed in file names
    csv_fh = open(csv_path,"w")
    fh = open(datadir+str(feedid)+".dat","rb")
    for i in range(0,meta['npoints']):
        time = meta['start_time'] + i*meta['interval']
        val = struct.unpack("f",fh.read(4))
        csv_fh.write(str(time)+","+str(val[0])+"\n")
    fh.close()
    csv_fh.close()
    
def phptimeseries_convert(datadir,csvdir,feedid,tag,name):
    if os.path.isfile(datadir+"feed_"+str(feedid)+".MYD"):
        bytesize = os.stat(datadir+"feed_"+str(feedid)+".MYD").st_size
        npoints = int(bytesize/9.0)
        csv_path = csvdir+str(feedid)+"-"+str(tag)+"-"+str(name)+".csv"
        csv_path = csv_path.replace(":", "-") # this is needed on windows at least because ":" are not allowed in file names
        csv_fh = open(csv_path,"w")
        fh = open(datadir+"feed_"+str(feedid)+".MYD","rb")
        
        for i in range(0,npoints):
            tmp = struct.unpack('<xIf',fh.read(9))
            csv_fh.write(str(tmp[0])+","+str(tmp[1])+"\n")
        fh.close()
        csv_fh.close()

if not os.path.exists(username+"/csv"):
    os.mkdir(username+"/csv")

if os.path.exists(username+"/feeds.json"):
    with open(username+'/feeds.json') as json_file:
        feeds = json.load(json_file)
        for f in feeds:
            if int(f['engine'])==2:
                print("Converting phptimeseries feed "+str(f['id']))
                phptimeseries_convert(username+"/phptimeseries/",username+"/csv/",f['id'],f['tag'],f['name'])
            if int(f['engine'])==5:
                print("Converting phpfina feed "+str(f['id']))
                phpfina_convert(username+"/phpfina/",username+"/csv/",f['id'],f['tag'],f['name'])        
                pass
