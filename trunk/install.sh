#!/bin/bash

#some definitions
conffile=/usr/local/etc/logger.conf
tabelle=log       ## The Mysql DB tab-prefix
db=solarmax       ## The Mysql DB name
newuser=solaruser ## The Mysql DB user
realpath=$0
instpath="${realpath%/*}"
if [ "$instpath" = "." ]; then
  instpath=`pwd`
fi
sitehead=$instpath/web-custom/sitehead.php
installerversion=`cat $instpath/HISTORY|grep -m 1 Version|cut -d' ' -f2`

######################################
### definition for custom commands ###
######################################

#Ask for number of inverters
ask_anz(){
   echo -n -e "\n For how many inverters should the watcher be configured? "
   read anz_wr
   echo -n -e "\n Is the given input correct? [y/n] "
   korrekt1(){
     read RICHTIG1
     case "$RICHTIG1" in
       y)
         return 0
         ;;
       n)
         ask_anz
         ;;
       *)
         echo -n -e "\n Input error, is the number of inverters correct? [y/n] "
         korrekt1
         ;;
     esac
   }
   korrekt1
}

#Ask for Mysql Host
ask_mysql_host(){
echo -n -e "\n\n IP-address or hostname of your Mysql server [localhost]: "
read dbhost
   if [ -z $dbhost ]; then
     dbhost=localhost
   fi
   if [ $dbhost != localhost ]; then
     echo -e "\n Please secure, that the user 'root' may access the Mysql-server $dbhost from "
     echo -e " external and that the regarding firewall rules and port forwardings are adjusted. \n"
   fi
   echo -n -e "\n Is the DB-host '$dbhost' correct? [y/n] "
   korrekt6(){
     read RICHTIG6
     case "$RICHTIG6" in
       y)
         return 0
         ;;
       n)
         ask_mysql_host
         ;;
       *)
         echo -n -e "\n Input error, is the given hostname correct? [y/n] "
         korrekt6
         ;;
     esac
   }
   korrekt6
}

#Ask for Mysql-root-PW
ask_mysqlroot_pw(){
  mysql -u root -h $dbhost -e "show databases" >/dev/null 2>&1
  if [ $? -ne 0 ]; then
    korrekt2(){
      echo -n -e "\n\n Allready existing Mysql password for user 'root': "
      read -s rootpw
      echo -n -e "\n Repeat password input                           : "
      read -s rootpw2
      if [ "$rootpw" == "$rootpw2" ]; then
        echo -e "\n"
        return 0
      else
        echo -e "\n\n The passwords don't match, try again ... "
        korrekt2
      fi
    }
    korrekt2
  else
    korrekt3(){
      echo -e "\n Until now, no password is set for Mysql-user 'root'. We'll set it now ... "
      echo -n -e "\n Mysql password for user 'root': "
      read -s rootpw
      echo -n -e "\n Repeat password input         : "
      read -s rootpw2
      if [ "$rootpw" == "$rootpw2" ]; then
        echo -e "\n"
        return 0
      else
        echo -e "\n\n The passwords don't match, try again please... "
        korrekt3
      fi
    }
    korrekt3
    mysqladmin -u root -h $dbhost password @rootpw
  fi
}

#Ask for Mysql-user-PW
ask_mysqluser_pw(){
  echo -e "\n The DB-user for the Solarmax-DB needs a password, which is asked here ... "
    korrekt4(){
      echo -n -e "\n Mysql password for DB-User '$newuser': "
      read -s userpw
      echo -n -e "\n Repeat password input                 : "
      read -s userpw2
      if [ "$userpw" == "$userpw2" ]; then
        echo -e "\n"
        return 0
      else
        echo -e "\n\n The passwords don't match, try again please... "
        korrekt4
      fi
    }
    korrekt4
}

##Proof, if DB exists, otherwise create it
create_db(){
  if [ `mysql -u root -h $dbhost -p$rootpw -e "show databases"|grep -c $db` = 1 ]; then
    echo -e "\n The 'solarmax' database is allready existing. Nothing to do here ... "
    else
    echo -e "\n A new 'solarmax' database will be created now ... "

    ## create Database
    command1="create database if not exists $db;
    GRANT ALL PRIVILEGES ON $db.* to $newuser@'localhost' IDENTIFIED BY '$userpw';
    GRANT ALL PRIVILEGES ON $db.* to $newuser IDENTIFIED BY '$userpw';
    flush privileges;"

    mysql -uroot -h $dbhost -p"$rootpw" -e "$command1"

    ## create tables
    i=1
    while [ $i -le $anz_wr ]
    do
    command2="use $db;CREATE TABLE IF NOT EXISTS $tabelle$i (
    created timestamp NOT NULL default CURRENT_TIMESTAMP,
    kdy int(11) unsigned default NULL,
    kmt int(11) unsigned default NULL,
    kyr int(11) unsigned default NULL,
    kt0 int(11) unsigned default NULL,
    tnf int(11) unsigned default NULL,
    tkk int(11) unsigned default NULL,
    pac int(11) unsigned default NULL,
    prl int(11) unsigned default NULL,
    il1 int(11) unsigned default NULL,
    idc int(11) unsigned default NULL,
    ul1 int(11) unsigned default NULL,
    udc int(11) unsigned default NULL,
    sys int(11) unsigned default NULL,
    PRIMARY KEY  (created)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;"

    mysql -u$newuser -h $dbhost -p"$userpw" -e "$command2"
    i=`expr $i + 1`
    done

    ## show tables and status
    command3="show databases;
    use $db;
    show tables;
    status;"

    mysql -u$newuser -h $dbhost -p"$userpw" -e "$command3"

  fi
}

## compile logger and move into filesystem
compile_logger(){
  mkdir -p $instpath/logger-bin
  echo -n -e "\n Shall debugging be enabled? (y/n) [N] "
  read DEBUGENABLE
  case "$DEBUGENABLE" in
    y)
      sed -e "s/define DEBUG 0/define DEBUG 1/" $instpath/logger-src/logger.c > $instpath/logger-src/logger-neu.c
      ;;
    *)
      sed -e "s/define DEBUG 1/define DEBUG 0/" $instpath/logger-src/logger.c > $instpath/logger-src/logger-neu.c
      ;;
  esac
  gcc -W -Wall -Wextra -Wshadow -Wlong-long -Wformat -Wpointer-arith -rdynamic -pedantic-errors -std=c99 -o $instpath/logger-bin/logger $instpath/logger-src/logger-neu.c -lmysqlclient
  rm -f $instpath/logger-src/logger-neu.c
  cp -f $instpath/logger-bin/logger /usr/local/bin/
}

#Creation of config-file
config_file_logger(){
  echo -e "\n\n Define a logging interval in seconds here (60 might be a good choice) ... \n"
  echo -n " Logging interval: "
  read loginterval

  echo -e "\n\n LAN-Settings for 1st inverter"
  echo -e " -------------------\n"
  echo -n " Hostname or IP: "
  read invhost
  echo -n " Port          : "
  read invport

  mkdir -p /usr/local/etc
  touch $conffile
  chmod 0600 $conffile
  chown root.root $conffile
  echo "## Settings for the Solarmax Watcher" > $conffile
  echo "## defaults are shown in parentheses" >> $conffile
  echo "" >> $conffile
  echo "# Interval to read the values of the inverter (60)" >> $conffile
  echo "Loginterval=$loginterval" >> $conffile
  echo "" >> $conffile
  echo "# Interval to wait for inverters answer (200)" >> $conffile
  echo "Waitinterval=200" >> $conffile
  echo "" >> $conffile
  echo "" >> $conffile
  echo "## Mysql settings" >> $conffile
  echo "" >> $conffile
  echo "# Hostname which is running the Mysql Server (localhost)" >> $conffile
  echo "DBhost=$dbhost" >> $conffile
  echo "" >> $conffile
  echo "# Tablename prefix (log)" >> $conffile
  echo "DBtabprefix=$tabelle" >> $conffile
  echo "" >> $conffile
  echo "# Name of the mysql-DB for the Solarmax logger (solarmax)" >> $conffile
  echo "DBname=$db" >> $conffile
  echo "" >> $conffile
  echo "# DB-User to write the values Solarmax-DB ($newuser)" >> $conffile
  echo "DBuser=$newuser" >> $conffile
  echo "" >> $conffile
  echo "# Password for the DB-User" >> $conffile
  echo "DBpass=$userpw" >> $conffile
  echo "" >> $conffile
  echo "" >> $conffile
  echo "## Inverter settings" >> $conffile
  echo "" >> $conffile
  echo "# IP-address or hostname of the inverter connected to the LAN" >> $conffile
  echo "Hostname=$invhost" >> $conffile
  echo "" >> $conffile
  echo "# IP-Port of the inverter (12345)" >> $conffile
  echo "Hostport=$invport" >> $conffile
  echo "" >> $conffile
  echo "# Number of Solarmax inverters in your array (1)" >> $conffile
  echo "NumberOfInverters=$anz_wr" >> $conffile

  echo -e "\n\n If any of the settings above was incorrect or should change in the future, "
  echo -e " please edit the file '$conffile' to change these settings.\n"
}

#activation and start of the logger
activation(){
  cp -f $instpath/init.d/solarmax-logger /etc/init.d/
  /etc/init.d/solarmax-logger start
  insserv solarmax-logger >/dev/null 2>&1
  chkconfig solarmax-logger >/dev/null 2>&1
  update-rc.d solarmax-logger defaults >/dev/null 2>&1

  if [ `cat /etc/crontab| grep -c 'solarmax-logger'` = 0 ]; then
    echo "00 4 * * *  root  /etc/init.d/solarmax-logger start" >> /etc/crontab
    echo "00 23 * * *  root  /etc/init.d/solarmax-logger stop" >> /etc/crontab
  fi
}

#Creation of Web
create_web(){
mkdir $instpath/web-custom
cp -R $instpath/web/* $instpath/web-custom/
echo -e -n "\n\n Which amount of remuneration do you get per kwh [ EUR, e. g. 0.3914 ] ? "
read earnings
fontpath=`find /usr/share/ |grep DejaVuSansMono.ttf`
sed -e "s/'user'/'$newuser'/" \
-e "s/password/$userpw/" \
-e "s/0.3405/$earnings/" \
-e "s,\/usr\/share\/fonts\/truetype\/ttf-dejavu\/DejaVuSansMono.ttf,$fontpath," \
-e "s/localhost/$dbhost/" \
sed -e "s/'solarmax'/'$db'/" \
$instpath/web-custom/solarertrag.php > $instpath/web-custom/atempfile
mv $instpath/web-custom/atempfile $instpath/web-custom/solarertrag.php

sed -e 's/$result1 =/\/\/$result1 =/' $instpath/web-custom/drawday.php > $instpath/web-custom/neu.php
sed -e 's/\/\/$result =/$result1 =/' $instpath/web-custom/neu.php > $instpath/web-custom/drawday.php

sed -e 's/ $result =/ \/\/$result1 =/' $instpath/web-custom/drawmonth.php > $instpath/web-custom/neu.php
sed -e 's/\/\/$result =/$result =/' $instpath/web-custom/neu.php > $instpath/web-custom/drawmonth.php

sed -e 's/ $result =/ \/\/$result1 =/' $instpath/web-custom/drawyear.php > $instpath/web-custom/neu.php
sed -e 's/\/\/$result =/$result =/' $instpath/web-custom/neu.php > $instpath/web-custom/drawyear.php

sed -e "s|'user'|'$newuser'|g" -e "s|'password'|'$userpw'|g" $instpath/web-custom/analyzer.php  > $instpath/web-custom/atempfile && mv $instpath/web-custom/atempfile $instpath/web-custom/analyzer.php

rm -f $instpath/web-custom/neu.php
echo -e "\n\n Please enter the project name, which will reside on top of the web page\n"
echo -n " Project name: "
read proname
echo -e "\n\n Please enter now the url to your web home, if there is one ( e. g. http://www.myweb.com )"
echo -e " Otherwise just hit ENTER\n"
echo -n " Web-home: "
read webhome
echo "<?php" > $sitehead
echo "    /*" >> $sitehead
echo "       Simple solarmax visualizer php program written by zagibu@gmx.ch in July 2010" >> $sitehead
echo "       This program was originally licensed under WTFPL 2 http://sam.zoy.org/wtfpl/" >> $sitehead
echo "       Improvements by Frank Lassowski flassowski@gmx.de in August 2010" >> $sitehead
echo "       This program is now licensed under GPLv2 or later http://www.gnu.org/licenses/gpl2.html" >> $sitehead
echo "    */" >> $sitehead
echo "   \$title=\"$proname\";" >> $sitehead
if [ $anz_wr -eq 1 ]; then
 echo "   \$slogan1=\"unsere Photovoltaik-Anlage\";" >> $sitehead
 else
  for i in {1..100}; do
    if [ $i -gt $anz_wr ]; then
    break
    fi
    echo "   \$slogan$i=\"Inverter $i\";" >> $sitehead
 done
fi
if [ -z $webhome ]; then
  echo "   \$link0=\"http://localhost/\";" >> $sitehead
else
  echo "   \$link0=\"$webhome/\";" >> $sitehead
fi

  for i in {1..100}; do
    if [ $i -gt $anz_wr ]; then
    break
    fi
echo "   \$link$i=\"solarertrag.php?wr=$i\";" >> $sitehead
 done
echo "" >> $sitehead
echo "   echo \"<div id=\\\"header\\\">\\n\";" >> $sitehead
echo "   echo \"<h1><a href=\\\"\" . \$link0 . \"\\\">\" . \$title . \"</a></h1>\\n\";" >> $sitehead
echo "   echo \"<h5> \";" >> $sitehead
  for i in {1..100}; do
    if [ $i -gt $anz_wr ]; then
    break
    fi
     echo "   echo \"<a href=\\\"\" . \$link$i . \"\\\">\" . \$slogan$i . \"</a> \";" >> $sitehead
 done
echo "   echo \"</h5>\\n\";" >> $sitehead
echo "   echo \"</div>\\n\";" >> $sitehead
echo "?>" >> $sitehead


echo -e "\n\n Please enter the root of your web folder or choose one of the following ... "
echo -e "\n   1. /srv/www/htdocs"
echo "   2. /var/www"
echo "   3. other choice"
echo -e -n "\n   Your choice: "
read web_alt
no_web=0
if [ $web_alt -eq "3" ]; then
  echo -e -n "\n  Your web root (without ending slash '/' ) : "
  read web_path
  elif [ $web_alt -eq "1" ]; then
  web_path=/srv/www/htdocs
  elif [ $web_alt -eq "2" ]; then
  web_path=/var/www
else
  no_web=1
  echo -e "\n No web root was choosen. The web folder will stay undone in the subfolder"
  echo " web custom of the src folder of this software ..."
fi
if [ $no_web = "0" ]; then
  mkdir -p $web_path/solarmax
  cp -pfR $instpath/web-custom/* $web_path/solarmax
  chown -R wwwrun.www $web_path/solarmax >/dev/null 2>&1
  chown -R www-data.www-data $web_path/solarmax >/dev/null 2>&1
fi
}

function pause(){
  read -p "$*"
}

clear
echo -e "\n --------------------------------------------------------------\n"
echo "                     Solarmax Watcher $installerversion"
echo -e "                     ----------------------\n"
echo "                Installer for the Solarmax Logger "
echo "               and the Solarmax Watcher php-scripts"
echo -e "\n --------------------------------------------------------------\n"

if [ `whoami` != "root" ]; then
  echo -e "\n To execute this script, root privileges are required. So please "
  echo -e " login as root or use the 'sudo' command to start this installer; "
  echo -e " exiting. \n"
  exit 1
fi

echo -e "\n\n To run the logger and the php-watcher some requirements have to "
echo -e " be fulfilled. \n"
echo -e " Needed packages: \n"
echo "   - GNU C compiler (gcc)"
echo "   - libmysqlclient-devel (containing /usr/include/mysql/mysql.h) "
echo "     (path may differ)"
echo "   - a running Mysql server (may reside on another machine)"
echo "   - a running webserver, e. g. Apache with installed and activated "
echo "     php extension"
echo -e "   - php-modules 'gd' and 'mysql' \n"
echo " Press 'q' to quit here and improve your installation before "
echo " installing this software or press any other key to proceed. "
read -s -n 1 go_on
case $go_on in
  q)
    exit 0
    ;;
  *)
    echo -e "\n OK, let's go on then ... \n"
#    return 0
    ;;
esac

ask_anz
ask_mysql_host
ask_mysqlroot_pw
ask_mysqluser_pw
create_db
compile_logger
config_file_logger
activation
create_web

echo ""
pause ' Press Enter key to proceed ...'
