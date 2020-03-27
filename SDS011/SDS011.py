#!/usr/bin/python
# -*- coding: UTF-8 -*-
# ----------------------------------------------------------------------------------
# SDS011 Nova PM Sensor to Emoncms bridge
# ----------------------------------------------------------------------------------

import serial, time, struct, time, urllib2, os
import paho.mqtt.client as mqtt

emoncms_nodename = os.environ['EMONCMS_NODENAME']
print 'emoncms nodename: ' + emoncms_nodename

emoncms_host = os.environ['EMONCMS_HOST']
print 'emoncms host: ' + emoncms_host
emoncms_apikey = os.environ['EMONCMS_APIKEY']
print 'emoncms apikey: ' + emoncms_apikey
http_enable = os.environ['HTTP_ENABLE']
print 'http enable: ' + http_enable

mqtt_user = os.environ['MQTT_USER']
print 'mqtt user: ' + mqtt_user
mqtt_passwd = os.environ['MQTT_PASSWD']
print 'mqtt pass: ' + mqtt_passwd
mqtt_host = os.environ['MQTT_HOST']
print 'mqtt host: ' + mqtt_host
mqtt_port = os.environ['MQTT_PORT']
print 'mqtt port: ' + mqtt_port
mqtt_enable = os.environ['MQTT_ENABLE']
print 'mqtt enable: ' + mqtt_enable

serial_port = os.environ['SERIAL_PORT']
print 'serial port: ' + serial_port

if mqtt_enable=='True':
    print 'Starting MQTT...'
    mqttc = mqtt.Client()
    mqttc.username_pw_set(mqtt_user, mqtt_passwd)
    mqttc.connect(mqtt_host, mqtt_port, 60)

print 'connecting to ' + serial_port
ser = serial.Serial(serial_port, baudrate=9600, stopbits=1, parity="N", timeout=2)

ser.flushInput()

byte, lastbyte = "\x00", "\x00"

# Reading arrive from SDS011 every second, we average 10 readings every 10 seconds and send the result to emoncms
pm_25_sum = 0
pm_10_sum = 0
count = 0

lasttime = 0

print 'starting loop..'
while True:
    lastbyte = byte
    byte = ser.read(size=1)
    
    # Valid packet header
    if lastbyte == "\xAA" and byte == "\xC0":
        sentence = ser.read(size=8) # Read 8 more bytes
        readings = struct.unpack('<hhxxcc',sentence) # Decode the packet - big endian, 2 shorts for pm2.5 and pm10, 2 reserved bytes, checksum, message tail
        
        pm_25 = readings[0]/10.0
        pm_10 = readings[1]/10.0
        # print "PM 2.5:",pm_25,"μg/m^3  PM 10:",pm_10,"μg/m^3"
        
        pm_25_sum += pm_25
        pm_10_sum += pm_10
        count = count + 1
    
    # Send to emoncms every 10 seconds
    if (time.time()-lasttime)>=10.0:
        lasttime = time.time()
        if count>0:
            pm_25 = round(pm_25_sum/count,3)
            pm_10 = round(pm_10_sum/count,3)
            pm_25_sum = 0
            pm_10_sum = 0
            count = 0
            print "PM 2.5:",pm_25,"μg/m^3  PM 10:",pm_10,"μg/m^3"
            if http_enable=='True':
                print 'http post..'
                contents = urllib2.urlopen(emoncms_host+'/input/post?node='+emoncms_nodename+'&fulljson={"pm_25":'+str(pm_25)+',"pm_10":'+str(pm_10)+'}&apikey='+emoncms_apikey).read()
            if mqtt_enable=='True':
                print 'mqtt post..'
                mqttc.publish("emon/"+emoncms_nodename+"/pm_25",pm_25)
                mqttc.publish("emon/"+emoncms_nodename+"/pm_10",pm_10)

    if mqtt_enable=='True':
        mqttc.loop(0.0)

    time.sleep(0.01)



