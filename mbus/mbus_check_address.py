import sys, time
import load_config as conf
serial_port, baud_rate, address = conf.load()

sys.path.append('/opt/openenergymonitor/emonhub/src')
from interfacers import *
mbus = EmonHubMBUSInterfacer.EmonHubMBUSInterfacer("MBUS",serial_port,baud_rate)

while True:
    print ("Sending command to check meter at address "+str(address))
    mbus.mbus_short_frame(address, 0x40)
    time.sleep(1.0)
    reply = False
    while mbus.ser.in_waiting:
        val = ord(mbus.ser.read(1))
        if val==229: 
            print("ACK")
            reply = True

    if not reply:
        print("no reply received")
        sys.exit(0)

    address = int(input("Please enter address to check (default: "+str(address)+"): ") or address)
