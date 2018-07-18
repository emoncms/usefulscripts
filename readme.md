# Useful scripts

Useful scripts for managing your emoncms installation.

## Update Emoncms

`update_emoncms.sh` script can be used to update Emoncms, this script pulls the latest chages from Emoncms GitHub and Emoncms Modules repos. The script assumes Emoncms path `/var/www/html/emoncms` edit to match your setup. Contribuited by @pb66 (Paul) [see forum thread](https://community.openenergymonitor.org/t/emoncms-9-8-3-released-to-emonpi-stable-branch/3759/6).

## Backup/Replication

This is a tool for backing up the data in an emoncms.org account or other remote emoncms server to a local computer. It can be used with or without a local installation of emoncms. To use, open Backup/backup.php in a text editor.

- Set $remote_server and $remote_apikey to correspond to the remote emoncms account you wish to download from.
- Set $link\_to\_local\_emoncms to true if you wish to access your data within a local installation of emoncms. Set $local\_emoncms\_location and $local\_emoncms\_userid to link to your local emoncms installation.
- Set $link\_to\_local\_emoncms to false if you just want to download the data without linking to a local emoncms install (non-mysql data only). Set $dir to the directory on your computer you wish to download the data. Manually create the folders: phpfina, phpfiwa, phptimeseries, phptimestore within this folder.

- Run the backup script from terminal with:

    php backup.php

Tested with emoncms.org (v8.0.9: 4 July 2014), and local emoncms v8.2.8

## Data recovery

If backing up feed data via the normal directory copy method fails due to a disk problem, this data recovery tool may help you extract your feed data skipping the problematic sector on your disk. See forum post here for further information: [Data Recovery forum post](http://openenergymonitor.org/emon/node/5213)

To use the data recovery tool, open recover.php and set both source and target directories for the data to recover. Run the recover tool from terminal with:

    php recover.php

## Integrity check

A tool for checking the integrity of your emoncms phpfiwa, phpfina, phptimeseries and phptimestore feeds. To use, open recover.php and set the engine data directories. Run the recover tool from terminal with:

    php integritycheck.php

## Convert data

There are two scripts for converting phpfiwa or phptimestore to phpfina. PHPFina has a much lower write load when used as the storage engine on SD Cards. Using these conversion scripts you can convert your data to PHPFina so that it can be used with the new SD Card branch of emoncms called 'bufferedwrite'

    phpfiwa_to_phpfina.php
    phptimestore_to_phpfina.php

Set the source and target directory as well as emoncms install location and then run as above.  
Once the script has completed the conversion, flush redis for the changes to take effect;

`redis-cli flushall`

## Remove spikes

This script is useful to remove irregularities in feed data, and works through the phpfiwa data files searching for values that are larger or smaller than the limits, when it finds one it sets it to NAN which means its ignored in the graph.

To run the script;

    sudo php remove_spike.php -i FeedId -n MinValue -x MaxValue

for example to remove value above 5.5V and below 1V (battery feed in V for example) on feed 22 the syntax is;

    sudo php remove_spike.php -i 22 -n 1 -x 5.5

ONLY WORKS WITH PHPFIWA DATA!

## Emoncms MYSQL backup script

This shell script can be called manually, or automated by using Node-red or Cron to create a MYSQL dump of your emoncms MYSQL database.  
Firstly, edit the mysql_backup_emoncms.sh script as follows;  
-u 'username' = your emoncms MYSQL username (default is emoncms)  
-p'password' = your emoncms MYSQL password (Note - there is no space after the leading 'p'!!)  
'database' = the name of your emoncms MYSQL database (default is emoncms)  
Replace '/path/to/' with the absolute path to where your backup will be stored

Make the file executable:

`chmod +x mysql_backup_emoncms.sh`

To run, from the script installation directory:

`./mysql_backup_emoncms.sh`

## sdpart - A script to set-up SD cards for emoncms

There are two variants of this script; sdpart_imagefile and sdpart_selfbuild.  
**sdpart_selfbuild** is a script intended to be used to prepare a SD card for users wishing to build their system step-by-step using the emoncms self-build guides.
**sdpart_imagefile** is a script to be used **only** with the official emoncms imagefile, which will make the necessary changes to your SD card for emoncms to run effectively.

####sdpart_selfbuild

This shell script can be run under 2 different scenarios;  
1) The script is run immediatly after installing the operating system - **BEFORE** running `$ sudo rasp-config` and expanding the filesystem. This is the preferred option!  
2) If the filesystem has already been expanded, you must unmount the root partition & reduce it's size using Gparted or other similar tools, by at least 350Mb if using a 4Gb SD card, or 1.5Gb if using a larger card.


The script will check your SD card for size and will make the following changes;  
**On SD cards less than 5Gb capacity**  
* create a 300Mb data partition formatted to ext2 with a blocksize of 1024 bytes  
* create a partition 'buffer' of 10Mb  
* expand the root filesystem to fill the available space.


**On SD cards more than 5Gb capacity**  
* create a 1Gb data partition formatted to ext2 with a blocksize of 1024 bytes  
* create a partition 'buffer' of 50Mb  
* expand the root filesystem to fill the available space.

To run the script, navigate to the usefulscripts/sdpart folder and run `sudo ./sdpart_selfbuild` - following the prompts.

####sdpart_imagefile

Firstly, write the emoncms imagefile to your SD card, and boot emoncms in your Raspberry Pi.  
**Do not run raspi-config** but run the sdpart_imagefile script as follows;  

```
rpi-rw
cd usefulscripts
git pull
sudo sdpart/./sdpart_imagefile
```
Symlink to use `sudo emonSDexpand` with:

`sudo ln -s /home/pi/usefulscripts/sdpart/sdpart_imagefile /sbin/emonSDexpand`

The script will determine, and make the necessary changes, but will take 20 minutes or so to complete, and it's important that the process is not interupted.  
So leave well alone! and once complete, your Raspberry Pi will poweroff and shutdown.


## Password Reset

[Forum thread discussion](http://openenergymonitor.org/emon/node/12155)

Reset Emoncms password. Run with:

    php resetpassword.php

It then asks for the userid (default:1) and for a new password or option to auto generate:

```
=======================================
EMONCMS PASSWORD RESET
=======================================
Select userid, or press enter for default:  
Using default user 1
Enter new password, or press enter to auto generate:        
Auto generated password: 9f7599c8da
```

## Create users and devices, add the users to a group

Script for bulk creation of users. If device module is installed it can create one device for the user. If group module is installed it can add the user to a group as a passive member.

The script outputs a csv table including: username, userid, password, apikey_read, apikey_write, device_key. This table can be copied and pasted into a csv file for importing into a spreadsheet.
 
`php create_users_and_devices_add_to_group.php -d device_template -g group_name --dnode device_id --dname device_name -u username1 -u username2`

To get some basic help:

`php create_users_and_devices_add_to_group.php -h`

