import sys, time
import load_config as conf
serial_port, baud_rate, address = conf.load()

sys.path.append('/opt/openenergymonitor/emonhub/src')
from interfacers import *
mbus = EmonHubMBUSInterfacer.EmonHubMBUSInterfacer("MBUS",serial_port,False,False,baud_rate)

for address in range(0, 254):

    mbus.mbus_short_frame(address, 0x40)
    time.sleep(1.0)
    reply = False
    while mbus.ser.in_waiting:
        val = ord(mbus.ser.read(1))
        if val==229: 
            print("ACK received from address "+str(address))
            reply = True

    if not reply:
        print("no reply received from address "+str(address))
        sys.exit(0)

