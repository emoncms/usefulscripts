cd /var/www/emoncms

echo "---------------------------------------------------"
echo "Loading current settings"
echo "---------------------------------------------------"

# Fetch current settings from settings.php
tmp=$(sed -n '/username/p' settings.php)
mysql_username=$(echo $tmp | awk -F\" '{print $(NF-1)}')

tmp=$(sed -n '/password/p' settings.php)
mysql_password=$(echo $tmp | awk -F\" '{print $(NF-1)}')

tmp=$(sed -n '/server/p' settings.php)
mysql_server=$(echo $tmp | awk -F\" '{print $(NF-1)}')

tmp=$(sed -n '/database/p' settings.php)
mysql_database=$(echo $tmp | awk -F\" '{print $(NF-1)}')

tmp=$(sed -n '/dbtest/p' settings.php)

# check if dbtest is true
dbtest=false

if echo "$tmp" | egrep -q "true" ; then
  dbtest=true
fi

if echo "$tmp" | egrep -q "TRUE" ; then
  dbtest=true
fi

# if settings dont exist then ask for user entry
if [ -z "$mysql_username" ]; then
  echo "No mysql username set, please enter your mysql username: (usually: root)"
  read mysql_username
fi

if [ -z "$mysql_password" ]; then
  echo "No mysql password set, please enter your mysql password: "
  read mysql_password
fi

if [ -z "$mysql_server" ]; then
  echo "No mysql server set, please enter your mysql server (usually: localhost): "
  read mysql_server
fi

if [ -z "$mysql_database" ]; then
  echo "No mysql database set, please enter your mysql database: (usually: emoncms)"
  read mysql_database
fi

echo "MYSQL username: "$mysql_username
echo "MYSQL password: "$mysql_password
echo "MYSQL server: "$mysql_server
echo "MYSQL database: "$mysql_database
echo
if [ $dbtest ]; then 
  echo "dbcheck: true"
  echo "YOU MAY WANT TO SET dbcheck in settings.php to FALSE when your sure your database is setup correctly"
else
  echo "dbcheck: false"
fi
echo ""

timestore_adminkey=$(cat "/var/lib/timestore/adminkey.txt")

echo "Fetching timestore adminkey from /var/lib/timestore/adminkey.txt: "
echo $timestore_adminkey

echo "---------------------------------------------------"
echo "Creating new settings.php file"
echo "---------------------------------------------------"

echo "Deleting old settings.php file"
rm settings.php
echo "copying default.settings.php to settings.php"
cp default.settings.php settings.php

echo "inserting settings as above"
sed -i 's/^  $username.*/  $username = "'$mysql_username'";/' settings.php
sed -i 's/^  $password.*/  $password = "'$mysql_password'";/' settings.php
sed -i 's/^  $server.*/  $server = "'$mysql_server'";/' settings.php
sed -i 's/^  $database.*/  $database = "'$mysql_database'";/' settings.php

timestore_adminkey=$(echo "$timestore_adminkey"|sed 's!\([]\*\$\/&[]\)!\\\1!g')
sed -i 's/^  $timestore_adminkey.*/  $timestore_adminkey = "'$timestore_adminkey'";/' settings.php

if [ -z $dbtest ]; then
  sed -i 's/^  $dbtest.*/  $dbtest = FALSE;/' settings.php
fi


