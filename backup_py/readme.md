# Python based Emoncms Backup Utility

- **data_downloader.py:** Downloads all feeds from a remote emoncms server such as emoncms.org. Data is saved locally in the original phpfina and phptimeseries binary data formats.
- **convert_to_csv.py:** Converts downloaded feeds from phpfina and phptimeseries binary formats into CSV data.

## How to use

**Download account data**

1\. Enter emoncms account username and password at the top of the data_downloader.py script.

2\. Run script:

    python3 data_downloader.py
    
3\. A directory will be created with the same name as the account username, this directory contains the phpfina and phptimeseries directories which in turn contain the feed data files. feeds.json includes a record of the feed grouping (tag) and names.

**Convert downloaded binary feed data to CSV**

1\. Enter the emoncms account username of the data downloaded using data_downloader.py above.

2\. Run script:

    python3 convert_to_csv.py
    
3\. A directory will be created inside the username directory called csv containing the converted CSV data.

    
    

