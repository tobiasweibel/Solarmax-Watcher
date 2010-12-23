#!/bin/bash
#
# Solarmax-logger This scripts starts Solarmax logger.
#
### BEGIN INIT INFO
# Provides:       solarmax-logger
# Required-Start: $remote_fs $mysql $network
# Required-Stop: $remote_fs
# Default-Start:  3 5
# Default-Stop: 0 1 2 6
# Description:   Solarmax-logger reads data from Solarmax photovoltaic inverters
### END INIT INFO
#
# written by Stefan Riedelsheimer stefanos-web@arcor.de in November 2010
#
### START DER VARIABLEN-BESCHREIBUNG

bin=smw-logger.pl
conf="smw-logger-pl.conf"
#
### ENDE DER VARIABLEN-BESCHREIBUNG

### Killroutine nach START und STOP Time ####
### 
 START_TIME=6 
 STOP_TIME=22 
 HOUR=$((`date +"%k"`)) 
	# if (( $HOUR <= $START_TIME || $HOUR >= $STOP_TIME )) ; then 
	#	 echo -e "\033[49;1;32mSolarmax-logger daemon schliesst ... kein start\033[0m" 
	#	 PID=$(pidof perl /share/Qweb/Solarmax/log/smw-logger.pl)
	#	if [ "$PID" != "" ] ;
	#		then 
	#		kill $PID
	#		exit
	#		else	
	#		exit 
	#	 fi
	# fi
	
case "$1" in
	start)
	if (( $HOUR <= $START_TIME || $HOUR >= $STOP_TIME )) ; 
		then 
		 echo -e "\033[49;1;31mSolarmax-logger daemon schliesst ... kein start\033[0m" 
		 PID=$(pidof perl smw-logger.pl)
		if [ "$PID" != "" ] ;
			then 
			$0 stop 
		fi
	else
	 PID=$(pidof perl smw-logger.pl)
		if [ "$PID" != "" ] ;
    then 
      echo -e "\033[49;1;32mSolarmax-logger daemon laeuft gerade ... kein start\033[0m"
    
    else
		echo -n " Starting Solarmax-logger daemon ... "
		perl $bin $conf &
		    if [ $? = 0 ]; then
		      echo -e "\033[49;1;32mdone\033[0m"
	         	else
		      echo -e "\033[49;1;31mfailed\033[0m"
	     	fi
		fi
	fi
		;;
	stop)
		echo -n " Stopping Solarmax-logger daemon ... "
		PID=$(pidof perl smw-logger.pl)
		if [ "$PID" = "" ] ;
			then 
			echo -e  "\n\033[49;1;31m... laeuft nicht  ... kein stop\033[0m"
			else
			
			kill $PID
				if [ $? = 0 ]; then
					echo -e "\033[49;1;32mdone\033[0m"
					else
					echo -e "\033[49;1;31mfailed\033[0m"
				fi
		fi
		
		;;
		
	restart)
		$0 stop
		sleep 5
		$0 start
		;;
	*)
		echo "Usage: $0 {start|stop|restart}"
		;;
esac

