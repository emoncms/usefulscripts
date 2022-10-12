import sys, time

serial_port = input("Please enter serial port (default: /dev/ttyUSB0): ") or "/dev/ttyUSB0"
address = int(input("Please enter address (default: 254): ") or "254")
current_baud_rate = int(input("Please enter current baud rate (default: 4800): ") or "4800")
new_baud_rate = int(input("Please enter new baud rate (default: 2400): ") or "2400")

print ("Trying to connect on "+str(serial_port)+" "+str(current_baud_rate))

sys.path.append('/opt/openenergymonitor/emonhub/src')
from interfacers import *
mbus = EmonHubMBUSInterfacer.EmonHubMBUSInterfacer("MBUS",serial_port,current_baud_rate)



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

print ("Sending command to change meter baud rate "+str(address)+" to baud rate "+str(new_baud_rate))
mbus.mbus_set_baudrate(address, new_baud_rate)
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

mbus = EmonHubMBUSInterfacer.EmonHubMBUSInterfacer("MBUS",serial_port,new_baud_rate)

print ("Sending command to check meter at address "+str(address))

for i in range(0,3):
    mbus.mbus_short_frame(address, 0x40)
    time.sleep(1.0)
    reply = False
    while mbus.ser.in_waiting:
        val = ord(mbus.ser.read(1))
        if val==229: 
            print("ACK")
            reply = True
            sys.exit(0)

    if not reply:
        print("no reply received")
