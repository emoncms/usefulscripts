import sys, time

serial_port = input("Please enter serial port (default: /dev/ttyUSB0): ") or "/dev/ttyUSB0"
baud_rate = int(input("Please enter baud rate (default: 2400): ") or "2400")

print ("Trying to connect on "+str(serial_port)+" "+str(baud_rate))

sys.path.append('/opt/openenergymonitor/emonhub/src')
from interfacers import *
mbus = EmonHubMBUSInterfacer.EmonHubMBUSInterfacer("MBUS",serial_port,baud_rate)

address = int(input("Please enter address to check (default: 0): ") or "0")

mbus.mbus_application_reset(address)
