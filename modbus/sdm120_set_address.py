import time
import struct
from pymodbus.client import ModbusSerialClient
from pymodbus.payload import BinaryPayloadDecoder
from pymodbus.constants import Endian
from pymodbus.utilities import computeCRC

def get_input(prompt, default):
    user_val = input(f"{prompt} [{default}]: ").strip()
    return user_val if user_val else default

def build_id_change_payload(old_id, new_id):
    """Constructs the raw Modbus RTU packet to change the ID."""
    # Convert new_id to 32-bit float hex (SDM120 requirement)
    float_hex = struct.pack('>f', float(new_id)) 
    
    # [Addr][Func][RegHi][RegLo][QtyHi][QtyLo][Bytes]
    packet = bytearray([old_id, 0x10, 0x00, 0x14, 0x00, 0x02, 0x04])
    packet.extend(float_hex)
    
    # Calculate CRC
    crc = computeCRC(packet)
    
    # FIX: PyModbus computeCRC returns the integer already byte-swapped.
    # Packing it as Big Endian ('>H') preserves the correct E6 90 transmission order.
    packet.extend(struct.pack('>H', crc))
    
    return packet

def main():
    # --- Configuration Inputs ---
    port = get_input("Enter Serial Port", "/dev/ttyACM0")
    old_id = int(get_input("Enter Current ID", "1"))
    new_id = int(get_input("Enter New ID to set", "2"))

    client = ModbusSerialClient(
        port=port,
        baudrate=9600,
        parity='N',
        stopbits=1,
        bytesize=8,
        timeout=2
    )

    if not client.connect():
        print(f"Error: Could not open {port}. Check permissions (sudo).")
        return

    try:
        # STEP 1: VERIFY ORIGINAL CONNECTION
        print(f"\n[1/3] Verifying connection to ID {old_id}...")
        result = client.read_input_registers(address=0x00, count=2, slave=old_id)

        if result.isError():
            print(f"Verification Failed: ID {old_id} is not responding.")
            return

        decoder = BinaryPayloadDecoder.fromRegisters(result.registers, byteorder=Endian.BIG, wordorder=Endian.BIG)
        print(f"Success! Current Voltage: {decoder.decode_32bit_float():.2f}V")

        # STEP 2: SEND CHANGE COMMAND
        confirm = input(f"\n[2/3] Change ID {old_id} to {new_id}? (y/n): ")
        if confirm.lower() != 'y':
            print("Operation cancelled by user.")
            return

        payload = build_id_change_payload(old_id, new_id)
        print(f"Command Sent: {payload.hex(' ').upper()}")
        client.socket.write(payload)
        
        # Give the meter a moment to update its internal EEPROM
        time.sleep(1)
        
        # Flush the buffer of the expected 8-byte response
        client.socket.read(8)

        # STEP 3: VERIFY NEW CONNECTION
        print(f"\n[3/3] Verifying new connection at ID {new_id}...")
        time.sleep(1) # Extra buffer for the bus to settle
        new_result = client.read_input_registers(address=0x00, count=2, slave=new_id)

        if not new_result.isError():
            new_decoder = BinaryPayloadDecoder.fromRegisters(new_result.registers, byteorder=Endian.BIG, wordorder=Endian.BIG)
            print(f"CONFIRMED: Device is now communicating on ID {new_id}!")
            print(f"New Read Voltage: {new_decoder.decode_32bit_float():.2f}V")
        else:
            print(f"Warning: Address change sent, but device not responding on ID {new_id} yet.")
            print("Try power-cycling the SDM120 and running a read test.")

    except Exception as e:
        print(f"An unexpected error occurred: {e}")
    finally:
        client.close()
        print("\nSession Closed.")

if __name__ == "__main__":
    main()
