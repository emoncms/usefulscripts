import sys, time
import load_config as conf
serial_port, baud_rate, address = conf.load()

sys.path.append('/opt/openenergymonitor/emonhub/src')
from interfacers import *
mbus = EmonHubMBUSInterfacer.EmonHubMBUSInterfacer("MBUS",serial_port,False,False,baud_rate)

mbus.debug_data_frame = True

# mbus.set_page(address, 1)

mbus.mbus_short_frame(address, 0x5b)

result = mbus.read_data_frame([])

print(result)
