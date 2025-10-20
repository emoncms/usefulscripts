#!/usr/bin/env python3
"""
Octopus Energy CSV Import Script for EmonCMS

This script imports half-hourly electricity consumption data from a CSV file 
exported from Octopus Energy into an EmonCMS feed.

CSV Format Expected:
Consumption (kwh), Estimated Cost Inc. Tax (p), Start, End
0.028000, 0.71, 2024-10-01T00:00:00+01:00, 2024-10-01T00:30:00+01:00
...

Usage:
    python3 import_from_csv.py [csv_file_path] [--all]
    
    csv_file_path: Path to CSV file (default: gLRFkoBQ.csv)
    --all, -a: Import all data including historical (default: only newer data)
    
Examples:
    python3 import_from_csv.py                              # Import only new data from default file
    python3 import_from_csv.py --all                        # Import ALL data from default file
    python3 import_from_csv.py my_data.csv                  # Import only new data from specific file
    python3 import_from_csv.py my_data.csv --all            # Import ALL data from specific file
    python3 import_from_csv.py /path/to/octopus_export.csv --all
"""

import sys, os, requests, json, csv
from datetime import datetime
from configobj import ConfigObj

script_path = os.path.dirname(os.path.realpath(__file__))
settings = ConfigObj(script_path+"/agile.conf", file_error=True)

# Parse command line arguments
csv_file_path = script_path + "/gLRFkoBQ.csv"  # default
import_all = False  # default: only import newer data

# Check command line arguments
for i, arg in enumerate(sys.argv[1:], 1):
    if arg == "--all" or arg == "-a":
        import_all = True
        print("Import mode: ALL data (including historical)")
    elif arg == "--help" or arg == "-h":
        print("Usage: python3 import_from_csv.py [csv_file] [--all]")
        print("  csv_file: Path to CSV file (default: gLRFkoBQ.csv)")
        print("  --all, -a: Import all data including historical (default: only newer data)")
        print("  --help, -h: Show this help message")
        sys.exit(0)
    else:
        csv_file_path = arg
        # If relative path, make it relative to script directory
        if not os.path.isabs(csv_file_path):
            csv_file_path = os.path.join(script_path, csv_file_path)

if not import_all:
    print("Import mode: Only data newer than last feed entry (use --all to import historical data)")

# Check if CSV file exists
if not os.path.exists(csv_file_path):
    print(f"Error: CSV file not found at {csv_file_path}")
    sys.exit(1)

# Step 1: Create feed via API call or use input interface in emoncms to create manually
result = requests.get(settings['emoncms']['server']+"/feed/getid.json",params={'tag':settings['emoncms']['tag'],'name':settings['emoncms']['name'],'apikey':settings['emoncms']['apikey']})
if not result.text:
    # Create feed
    params = {'tag':settings['emoncms']['tag'],'name':settings['emoncms']['name'],'datatype':1,'engine':5,'options':'{"interval":1800}','unit':'kWh','apikey':settings['emoncms']['apikey']}
    result = requests.get(settings['emoncms']['server']+"/feed/create.json",params)
    result = json.loads(result.text)
    if result['success']:
        feedid = int(result['feedid'])
        print("Emoncms feed created:\t"+str(feedid))
    else:
        print("Error creating feed")
        sys.exit(0)
else:
    feedid = int(result.text)
    print("Using emoncms feed:\t"+str(feedid))

# Step 2: Fetch feed meta data to find last data point time and value
result = requests.get(settings['emoncms']['server']+"/feed/getmeta.json",params={'id':feedid,'apikey':settings['emoncms']['apikey']})
meta = json.loads(result.text)
print("Feed meta data:\t\t"+result.text)

end_time = 0
if meta['npoints']>0:
    end_time = meta['start_time'] + (meta['interval'] * meta['npoints'])
    print(f"Last data point time:\t{datetime.fromtimestamp(end_time)}")

# Step 3: Read CSV file and parse data
data = []
try:
    with open(csv_file_path, 'r') as csvfile:
        # Read the CSV file
        reader = csv.DictReader(csvfile)
        
        print(f"Reading data from CSV file: {csv_file_path}")
        
        for row in reader:
            # Parse the start timestamp (note the leading space in CSV header)
            start_time_str = row[' Start'].strip()
            
            # Convert ISO format timestamp to datetime object
            date = datetime.fromisoformat(start_time_str.replace('Z', '+00:00'))
            timestamp = int(date.timestamp())
            
            # Get consumption value
            consumption = float(row['Consumption (kwh)'].strip())
            
            # Add data based on import mode
            if import_all or timestamp >= end_time:
                data.append([timestamp, consumption])
            
        print(f"Total CSV rows processed: {reader.line_num - 1}")
        
except Exception as e:
    print(f"Error reading CSV file: {e}")
    sys.exit(1)

# Filter out duplicate timestamps and sort by timestamp
if data:
    # Remove duplicates and sort
    unique_data = {}
    for timestamp, consumption in data:
        unique_data[timestamp] = consumption
    
    data = [[timestamp, consumption] for timestamp, consumption in unique_data.items()]
    data.sort(key=lambda x: x[0])  # Sort by timestamp in ascending order
    
    print(f"Unique data points to upload: {len(data)}")
    
    if len(data) > 0:
        # Print date range
        start_date = datetime.fromtimestamp(data[0][0])
        end_date = datetime.fromtimestamp(data[-1][0])
        print(f"Data range: {start_date} to {end_date}")
        
        # Step 4: Send data to EmonCMS
        url = f"{settings['emoncms']['server']}/feed/post.json?id={feedid}&apikey={settings['emoncms']['apikey']}"
        response = requests.post(url, data={'data': json.dumps(data)})
        
        if response.status_code == 200:
            print("Data uploaded successfully")
            result = json.loads(response.text)
            if result and 'success' in result:
                if result['success']:
                    print("EmonCMS confirmed successful upload")
                else:
                    print(f"EmonCMS reported error: {result}")
        else:
            print(f"Error uploading data: {response.status_code}")
            print(response.text)
    else:
        print("No new data to upload")
else:
    print("No data found in CSV file")
