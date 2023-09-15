import sys, time
import load_config as conf
serial_port, baud_rate, address = conf.load()

sys.path.append('/opt/openenergymonitor/emonhub/src')
from interfacers import *
mbus = EmonHubMBUSInterfacer.EmonHubMBUSInterfacer("MBUS",serial_port,baud_rate)

page = int(input("Please enter page (default: 1): ") or "1")

mbus.set_page(address, page)

