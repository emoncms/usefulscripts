import sys, time
import load_config as conf
serial_port, baud_rate, address = conf.load()

sys.path.append('/opt/openenergymonitor/emonhub/src')
from interfacers import *
mbus = EmonHubMBUSInterfacer.EmonHubMBUSInterfacer("MBUS",serial_port,baud_rate)

mbus.mbus_application_reset(address)
