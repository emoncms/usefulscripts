import sys, time

serial_port = input("Please enter serial port (default: /dev/ttyUSB0): ") or "/dev/ttyUSB0"
baud_rate = int(input("Please enter baud rate (default: /dev/ttyUSB0): ") or "2400")

print ("Trying to connect on "+str(serial_port)+" "+str(baud_rate))

sys.path.append('/opt/openenergymonitor/emonhub/src')
from interfacers import *
mbus = EmonHubMBUSInterfacer.EmonHubMBUSInterfacer("MBUS",serial_port,baud_rate)

old_address = int(input("Please enter current address (default: 254): ") or "254")
new_address = int(input("Please enter new address (default: 1): ") or "1")

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

print ("Sending command to change meter on address "+str(old_address)+" to address "+str(new_address))
mbus.mbus_set_address(old_address, new_address)
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

print ("Sending command to check meter at address "+str(new_address))
mbus.mbus_short_frame(new_address, 0x40)
time.sleep(1.0)
reply = False
while mbus.ser.in_waiting:
    val = ord(mbus.ser.read(1))
    if val==229: 
        print("ACK")
        reply = True

if not reply:
    print("no reply received")
