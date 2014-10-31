import struct, os

# Directory of phptimeseries feeds, see: settings.php
datadir = "/var/lib/phptimeseries/"
    
# Feed id to read: 
feedid = 1
    
#----------------------------------------------------
    
fh = open(datadir+"feed_"+str(feedid)+".MYD", 'rb')
bytesize = os.stat(datadir+"feed_"+str(feedid)+".MYD").st_size
npoints = int(bytesize/9.0)
    
for i in range(npoints):

    array = struct.unpack('<cIf',fh.read(9))
    
    time = array[1]
    value = array[2]
    
    print str(time)+" "+str(value);
