#!/bin/bash

# netcat_sputnik_parse.sh
# originally written for solarpowerlog
# TODO: find authors, add license
# completely rewritten by Frank Lassowski <flassowski@gmx.de> in january 2012

make_random() {
	hextkk=$(echo "obase=16; $((RANDOM%64+32))" | bc)   # zwischen 32 und 64 dez.
	tkk=$(echo "ibase=16;$hextkk" | bc)
    hexpac=$(echo "obase=16; $((RANDOM%5000+1))" | bc)  # zwischen 1 und 5000 dez.
   	pac=$(echo "ibase=16;$hexpac" | bc)
    hexil1=$(echo "obase=16; $((RANDOM%400+1))" | bc)   # zwischen 1 und 400 dez.
   	il1=$(echo "ibase=16;$hexil1" | bc)
    hexidc=$(echo "obase=16; $((RANDOM%1024+1))" | bc)   # zwischen 1 und 1024 dez.
   	idc=$(echo "ibase=16;$hexidc" | bc)
    hexul1=$(echo "obase=16; $((RANDOM%400+256))" | bc) # zwischen 256 und 400 dez.
   	ul1=$(echo "ibase=16;$hexul1" | bc)
    hexudc=$(echo "obase=16; $((RANDOM%512+256))" | bc) # zwischen 256 und 512 dez.
   	udc=$(echo "ibase=16;$hexudc" | bc)
   	hexsys=$(echo "obase=16; $((RANDOM%20030+20000))" | bc) # zwischen 20000 und 20030 dez.
   	sys=$(echo "ibase=16;$hexsys" | bc)
}

send_main_message() {
    read -n 10 >/dev/null
    make_random
    send="|64:KDY=0;KMT=0;KYR=0;KT0=0;TNF=0;TKK=$hextkk;PAC=$hexpac;PRL=0;IL1=$hexil1;IDC=$hexidc;UL1=$hexul1;UDC=$hexudc;SYS=$hexsys|"
    echo -e $(../chksum_smw $send)
   	read -t 1 >/dev/null
}

echo "Connected" 1>&2
while (/bin/true)
do
	echo -n "." 1>&2
	count=0
	t_tkk=0
	t_pac=0
	t_il1=0
	t_idc=0
	t_ul1=0
	t_udc=0
	t_sys=0
	ticks=11
	while [ $count -lt $ticks ]
	do
		send_main_message
		t_tkk=$(($t_tkk+$tkk))
		t_pac=$(($t_pac+$pac))
		t_il1=$(($t_il1+$il1))
		t_idc=$(($t_idc+$idc))
		t_ul1=$(($t_ul1+$ul1))
		t_udc=$(($t_udc+$udc))
		t_sys=$(($t_sys+$sys))
		count=$((count+1))
	done
	
	echo $(date)" ø tkk:"$(($t_tkk/$ticks))" pac:"$(($t_pac/(2*$ticks)))" il1:"$(($t_il1/$ticks))" idc:"$(($t_idc/$ticks))" ul1:"$(($t_ul1/$ticks))" udc:"$(($t_udc/$ticks))" sys:"$sys >> /home/f/test/avglog
    touch /tmp/stampme
done
