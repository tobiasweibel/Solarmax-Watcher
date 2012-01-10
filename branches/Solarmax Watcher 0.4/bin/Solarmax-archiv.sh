#!/bin/bash

cd ..
realpath=$0
instpath="${realpath%/*}"
if [ "$instpath" = "." ]; then
  instpath=`pwd`
fi

version=`cat $instpath/HISTORY|grep -m 1 Version|cut -d' ' -f2`

clear
echo ""
echo " ----------------------------------------------------"
echo " |                                                  |"
echo " |       Solarmax Install-Repository wird           |"
echo " |      in ein *.tar.gr - File archiviert           |"
echo " |                                                  |"
echo " ----------------------------------------------------"
echo ""

if [ -d web-custom ]; then
  rm -fR web-custom
fi

if [ -d logger-bin ]; then
  rm -fR logger-bin
fi

echo -e "\n Archivierung wird durchgeführt in Datei "
echo -n " /Solarmax-Watcher-v$version.tar.gz ... "
cd /
tar -cPf /Solarmax-Watcher-v$version.tar.gz $instpath/
echo -e "fertig!\n"
