import time
from pymodbus.client import ModbusSerialClient
from pymodbus.payload import BinaryPayloadDecoder
from pymodbus.constants import Endian
from pymodbus.utilities import computeCRC

# Configuration
PORT = '/dev/ttyACM0'
BAUD = 9600

def main():
    client = ModbusSerialClient(
        port=PORT,
        baudrate=BAUD,
        parity='N',
        stopbits=1,
        bytesize=8,
        # Short timeout makes scanning much faster
        timeout=0.2 
    )

    if not client.connect():
        print(f"Error: Could not open {PORT}. Check permissions or USB connection.")
        return

    print(f"Scanning Modbus IDs 1 to 100 on {PORT} at {BAUD} baud...")
    found_device = False

    for slave_id in range(1, 101):
        # Attempt to read Voltage (Input Reg 0x0000)
        result = client.read_input_registers(address=0x00, count=2, slave=slave_id)
        
        if not result.isError():
            print(f"✅ SUCCESS: SDM120 found responding to ID: {slave_id}")
            decoder = BinaryPayloadDecoder.fromRegisters(result.registers, Endian.BIG, Endian.BIG)
            print(f"Success! Current Voltage: {decoder.decode_32bit_float():.2f}V")
            found_device = True
        
        # Small buffer to prevent bus collisions
        time.sleep(0.05) 

    if not found_device:
        print("❌ Scan complete. No devices responded on IDs 1-100.")

    client.close()

if __name__ == "__main__":
    main()
