import sys, time

serial_port = input("Please enter serial port (default: /dev/ttyUSB0): ") or "/dev/ttyUSB0"
baud_rate = int(input("Please enter baud rate (default: /dev/ttyUSB0): ") or "2400")

print ("Trying to connect on "+str(serial_port)+" "+str(baud_rate))

sys.path.append('/opt/openenergymonitor/emonhub/src')
from interfacers import *
mbus = EmonHubMBUSInterfacer.EmonHubMBUSInterfacer("MBUS",serial_port,baud_rate)

old_address = int(input("Please enter address to check (default: 1): ") or "1")

print ("Sending command to check meter at address "+str(old_address))
mbus.mbus_short_frame(old_address, 0x40)
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
