# Load config.ini if it exists
def load():
    try:
        import configobj
        config = configobj.ConfigObj("config.ini")
        serial_port = config['serial_port']
        baud_rate = int(config['baud_rate'])
        address = int(config['address'])
        print ("Loaded settings from config.ini file")
    except:
        print ("No config.ini file found")
        serial_port = input("Please enter serial port (default: /dev/ttyUSB0): ") or "/dev/ttyUSB0"
        baud_rate = int(input("Please enter baud rate (default: 2400): ") or "2400")
        address = int(input("Please enter address to check (default: 0): ") or "0")


    print ("Trying to connect on port:"+str(serial_port)+" baud:"+str(baud_rate)+" address:"+str(address))
    return serial_port, baud_rate, address


