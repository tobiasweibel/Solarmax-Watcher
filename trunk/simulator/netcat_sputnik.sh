#!/bin/bash

# originally written for solarpowerlog
# TODO: find authors, add license

if [[ $# -ne "1" ]]
then
	echo "Usage $0 <portnum>"
	exit 1
fi

if [[ ! -e ./netcat_sputnik_parse.sh ]] 
then
	echo "./netcat_sputnik_parse.sh not found. cd into simulator dir"
	exit 1
fi

echo "The netcat script will listen on port $1. Press Ctrl+C to abort (maybe twice)"


# restart automatically if the smw-logger instance closed the connetion
while (/bin/sleep 5)
do
#nc -l -p $1 -e ./netcat_sputnik_parse.sh
nc.traditional -l -p $1 -e ./netcat_sputnik_parse.sh # nc doesn't know the option -e!!!'

done
