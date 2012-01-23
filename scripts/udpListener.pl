#!/usr/bin/perl -w
#	Simple perl udp listener which print the KDY and the PAC values provided by the smw-logger
#	Source base from: http://www.thegeekstuff.com/2010/07/perl-tcp-udp-socket-programming/
#	This is my first perl script. So please forgive me ;)
#	Author:		SleepProgger
#
use IO::Socket::INET;
use POSIX;
# flush after every write
$| = 1;

my $regex = "{[^}]*KDY=([0-9A-F]+)[^}]*PAC=([0-9A-F]+)[^}]*}";
my $localPort = "4950";
my ($socket,$received_data);
my ($peeraddress,$peerport);
if(@ARGV > 0 && $ARGV[0] =~ m/\-?(\?|help|h)/){
	printf "USAGE: udpServer.pl [port [regex]]\n";
	printf "Regex have to provide \$1 (KDY) and \$2 (PAC)\n";
	exit 0;
}
if(@ARGV > 0){
	$localPort = $ARGV[0];
	printf "local port set to $localPort\n";
}
if(@ARGV > 1){
	$regex = $ARGV[1];
	printf "regex set to $regex\n";
}


#  we call IO::Socket::INET->new() to create the UDP Socket and bound
$socket = new IO::Socket::INET (
LocalPort => $localPort,
Proto => 'udp',
) or die "ERROR in Socket Creation : $!\n";
while(1){
	# read operation on the socket
	$socket->recv($recieved_data,1024);
	$recieved_data =~ s/($regex)/KDY: $2 kWh\nPAC: $3/;
	my $pac = strtol( $3, 16 ) / 2;
	my $kdy = strtol( $2, 16 ) / 10;
	system "clear";
	print "Ertrag heute:\t".$kdy." kWh\n"."Leistung:\t".$pac." Watt\n";
}
$socket->close();
