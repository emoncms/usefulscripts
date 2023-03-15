import sys, time

serial_port = input("Please enter serial port (default: /dev/ttyUSB0): ") or "/dev/ttyUSB0"
baud_rate = int(input("Please enter baud rate (default: 2400): ") or "2400")

print ("Trying to connect on "+str(serial_port)+" "+str(baud_rate))

sys.path.append('/opt/openenergymonitor/emonhub/src')
from interfacers import *
mbus = EmonHubMBUSInterfacer.EmonHubMBUSInterfacer("MBUS",serial_port,baud_rate)

address = int(input("Please enter address to check (default: 0): ") or "0")

mbus.debug_data_frame = True

# mbus.set_page(address, 1)

mbus.mbus_short_frame(address, 0x5b)

result = mbus.read_data_frame([])

print(result)
