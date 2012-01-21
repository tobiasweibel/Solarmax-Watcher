/*
	Simple solarmax logger c program written by zagibu@gmx.ch in July 2010
	This program was originally licensed under WTFPL 2 http://sam.zoy.org/wtfpl/
	It is now licensed under GPLv2 or later http://www.gnu.org/licenses/gpl2.html

	You need the mysql client library files installed to be able to compile it.

	Compile with: gcc -W -Wall -Wextra -Wshadow -Wlong-long -Wformat -Wpointer-arith -rdynamic -pedantic-errors -std=c99 -o smw-logger smw-logger.c -lmysqlclient

	Run with: ./smw-logger /path/to/config-file

	Structure of the config-file:

	Debug=0
	Loginterval=60
	Waitinterval=200
	DBhost=localhost
	DBname=solarmax
	DBtabprefix=log
	DBuser=solaruser
	DBpass=userpassword
	Hostname=192.168.178.35
	Hostport=12345
	NumberOfInverters=1

   You can set DEBUG to 1 to get detailed output in a separate logfile.

   It is recommended to schedule the smw-logger to be started between 5:00 - 6:00 in the
   morning and stopped between 22:00 and 23:00 in the evening (compare with sunshine
   duration). The smw-logger has no built-in facility for logging, so use CRON or similar.

   Example CRON entries:
   00 05 * * * /usr/local/bin/smw-logger /usr/local/etc/smw-logger.conf
   00 23 * * * killall smw-logger

   Sources:
  - http://www.linuxhowtos.org/C_C++/socket.htm
  - http://wwwuser.gwdg.de/~kboehm/ebook/21_kap15_w6.html#49329
  - http://man.cx/setbuf%283%29
  - http://allfaq.org/forums/t/169895.aspx
  - http://dev.mysql.com/tech-resources/articles/mysql-capi-tutorial.html
  - http://www.cis.temple.edu/~ingargio/old/cis307s96/readings/docs/ipc.html <- Broadcast infos
  - http://www.linuxhowtos.org/C_C++/socket.htm <- Broadcast code parts
*/

#define _GNU_SOURCE

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <strings.h>
#include <sys/types.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <netdb.h>
#include <mysql/mysql.h>
#include <regex.h>
#include <time.h>
#include <unistd.h>
#include <fcntl.h>
#include <errno.h>
#include <pthread.h>
#include "UDP_Server.h"

FILE* error_file = NULL;
char error_file_name[512];
char* error_mode = "w";
FILE* debug_file = NULL;
char debug_file_name[512];
char* debug_mode = "w";
FILE* config_file = NULL;
char* config_file_name;
char* config_mode = "r";
int sockfd, portno, n, log_interval, logavg_interval, result, counter, wait_interval, active_max, nr_of_maxes, DEBUG;
// Renamed this flag because it is used for connection and regexp problems
int failure_flag = 0;
struct sockaddr_in serv_addr;
struct hostent* server;
char dbhost[512];
char dbname[512];
char dbtabprefix[512];
char dbuser[512];
char dbpass[512];
char hostaddr[512];
char line[512];
char* message;
char* expression = "...=([0-9A-F]*);...=([0-9A-F]*);...=([0-9A-F]*);...=([0-9A-F]*);...=([0-9A-F]*);...=([0-9A-F]*);...=([0-9A-F]*);...=([0-9A-F]*);...=([0-9A-F]*);...=([0-9A-F]*);...=([0-9A-F]*);...=([0-9A-F]*);...=([0-9A-F]*)";
int kdy, kmt, kyr, kt0, tnf, tkk, pac, prl, il1, idc, ul1, udc, sys;
char buffer[512], buffer2[512];
char query[512];
char* temp;
regex_t rx;
regmatch_t* matches;
MYSQL* connection = NULL;
// average stuff vars
int i;

// Server broadcast stuff
struct sockaddr_in their_addr;
int server_sock;

void error_exit(const char* msg) {
	perror(msg);
	if (error_file != NULL)
		fclose(error_file);
	if (debug_file != NULL)
		fclose(debug_file);
	exit(0);
}

void debug_entry(char* msg) {
	time_t timestamp = time(NULL);
	char debug_msg[512];
	char *time_now = ctime(&timestamp);
	time_now[strlen(time_now)-1]=0;
	if (debug_file == NULL)
		error_exit("ERROR writing to debug.log file");
	sprintf(debug_msg, "%s %s", time_now, msg);
	fprintf(debug_file, "%s\n", debug_msg);
	fprintf(stdout, "%s\n", debug_msg);
}

void error_retry(char* msg) {
	time_t timestamp = time(NULL);
	char error_msg[512];
	char *time_now = ctime(&timestamp);
	time_now[strlen(time_now)-1]=0;
	if (error_file == NULL)
		error_exit("ERROR writing to error.log file");
	sprintf(error_msg, "%s %s", time_now, msg);
	fprintf(error_file, "%s\n", error_msg);
	fprintf(stderr, "%s\n", error_msg);
}

void set_nonblock(int sock) {
	int flags;
	flags = fcntl(sock,F_GETFL,0);
	if (flags == -1)
		error_exit("ERROR no valid flags on socket");
	fcntl(sock, F_SETFL, flags | O_NONBLOCK);
}


int main(int argc, char *argv[]) {
	// Hold the time to wait between single requests.
	//int logavg_interval = 5;

	// Check commandline arguments
	if (argc < 2)
		error_exit("ERROR program needs config-file as parameter");

	//Read Config File
	config_file_name = argv[1];
	FILE *fp = fopen(config_file_name, config_mode);

	// Read variables
	if (fp) {
		while (fgets(line, sizeof(line), fp)) {
			sscanf(line, "Debug=%d[^\n]", &DEBUG);
			sscanf(line, "Errorfile=%[^\n]", error_file_name);
			sscanf(line, "Debugfile=%[^\n]", debug_file_name);
			sscanf(line, "Loginterval=%d[^\n]", &log_interval);
			sscanf(line, "Logavginterval=%d[^\n]", &logavg_interval);
			sscanf(line, "Waitinterval=%d[^\n]", &wait_interval);
			sscanf(line, "DBhost=%[^\n]", dbhost);
			sscanf(line, "DBname=%[^\n]", dbname);
			sscanf(line, "DBtabprefix=%[^\n]", dbtabprefix);
			sscanf(line, "DBuser=%[^\n]", dbuser);
			sscanf(line, "DBpass=%[^\n]", dbpass);
			sscanf(line, "Hostname=%[^\n]", hostaddr);
			sscanf(line, "Hostport=%d[^\n]", &portno);
			sscanf(line, "NumberOfInverters=%d[^\n]", &nr_of_maxes);
		}
	}
	fclose(fp);

	// Try to open error log file
	if ((error_file = fopen(error_file_name, error_mode)) == NULL)
		error_exit("ERROR opening error.log file");

	// Make file unbuffered
	setbuf(error_file, NULL);

	// create the arrays for the average calculation
	long tkdy[nr_of_maxes], tkmt[nr_of_maxes], tkyr[nr_of_maxes], tkt0[nr_of_maxes], ttnf[nr_of_maxes], ttkk[nr_of_maxes], tpac[nr_of_maxes], tprl[nr_of_maxes], til1[nr_of_maxes], tidc[nr_of_maxes], tul1[nr_of_maxes], tudc[nr_of_maxes], tsys[nr_of_maxes];
	// calculate the requests per log_interval
	// TODO -2 is a dirty fix to avoid desync
	int logavg_pertick = (int)((log_interval-2)/logavg_interval);

	// Try to open debug log file, if necessary
	if(DEBUG) {
		if((debug_file = fopen(debug_file_name, debug_mode)) == NULL)
			error_exit("ERROR opening debug.log file");

		// Make file unbuffered
		setbuf(debug_file, NULL);
   }

	// Try to compile regular expression
	result = regcomp(&rx, expression, REG_EXTENDED);
	if (result != 0) {
		regerror(result, &rx, expression, sizeof(expression));
		regfree(&rx);
		sprintf(buffer, "ERROR invalid regular expression: %s", expression);
		error_exit(buffer);
	}

	// Try to reserve memory for matches
	matches = (regmatch_t *) malloc((rx.re_nsub + 1) * sizeof(regmatch_t));
	if (!matches)
		error_exit("Out of memory");

	// Connect to database
	connection = mysql_init(NULL);
	if (!mysql_real_connect(connection, dbhost, dbuser, dbpass, dbname, 0, NULL, 0))
		error_exit(mysql_error(connection));

	if (DEBUG) {
		sprintf(buffer, "Connected to database %s on host %s", dbname, dbhost);
		debug_entry(buffer);
	}

	// Create the udp server socket stuff
	if(createAddress("192.168.0.255", 4950, &their_addr) != 0)printf("error with address creation\n");
	if(createSock(&server_sock) != 0)printf("error with socket creation\n");

	while (1) {

		// set variable to default value or it will keep trying to reconnect
		failure_flag = 0;

		// Check if connection to db-server must be re-established
		if (mysql_ping(connection)) {

			//TODO Maybe a reconnect (if needed) here ?
			// Connect to database
			if (!mysql_real_connect(connection, dbhost, dbuser, dbpass, dbname, 0, NULL, 0))
				error_exit(mysql_error(connection));

			if (DEBUG) {
				sprintf(buffer, "Connected to database %s on host %s", dbname, dbhost);
				debug_entry(buffer);
			}
		}

		// Try to open socket for communication with solarmax
		sockfd = socket(AF_INET, SOCK_STREAM, 0);
		if (sockfd < 0) {
			error_retry("Can't open any socket");
		sleep(60);
		continue;
		}

		// Try to resolve solarmax address/hostname
		server = gethostbyname(hostaddr);
		if (server == NULL) {
			sprintf(buffer, "Can't resolve \"%s\"", hostaddr);
			error_retry(buffer);
			sleep(60);
			continue;
		}

		// Try to establish a connection with solarmax
		//portno = atoi(argv[2]);
		bzero((char *) &serv_addr, sizeof(serv_addr));
		serv_addr.sin_family = AF_INET;
		bcopy((char *) server->h_addr, (char *) &serv_addr.sin_addr.s_addr, server->h_length);
		serv_addr.sin_port = htons(portno);
		if (connect(sockfd, (struct sockaddr*) &serv_addr, sizeof(serv_addr)) < 0) {
			sprintf(buffer, "%s: Can't connect to solarmax (%s) on port %d", strerror(errno), hostaddr, portno);
			error_retry(buffer);
			sleep(60);
			continue;
		}

		// Make socket non-blocking
		set_nonblock(sockfd);

		if (DEBUG) {
			sprintf(buffer, "Connected to solarmax (%s) on port %d", hostaddr, portno);
			debug_entry(buffer);
		}

		// Start sending the data requests and logging the answers
		while (1) {
			time_t start_time = time(NULL);
			for (i = 0; i < nr_of_maxes; ++i) {
				tkdy[i] = tkmt[i] = tkyr[i] = tkt0[i] = ttnf[i] = ttkk[i] = tpac[i] = tprl[i] = til1[i] = tidc[i] = tul1[i] = tudc[i] = tsys[i] = 0;
			}

			// Get the current time
			for (i = 0; i < logavg_pertick; ++i) {
				time_t single_start_time = time(NULL);
				// We have to get out of this while-loop to reestablish the connection to the inverter
				if (failure_flag == 1){
					 debug_entry("Looks like we lost our connection to solarmax, reconnecting...");
					 break;
				}


				for(active_max = 1; active_max <= nr_of_maxes; active_max++){

					// Generate message according to device address of solarmax:

					// Could be something like this:
					// sprintf(message, "{FB;0%d;46|64:KDY;KMT;KYR;KT0;TNF;TKK;PAC;PRL;IL1;IDC;UL1;UDC;SYS|%s}", active_max, 16_bit_checksum
					// For further information on the protocol refer to: http://blog.dest-unreach.be/2009/04/15/solarmax-maxtalk-protocol-reverse-engineered

					// Until someone comes up with a nice solution to calculate the checksum, lets stick to a few precalculated message strings (tested only for 2 maxes!):
					if (active_max == 1) {
						message = "{FB;01;46|64:KDY;KMT;KYR;KT0;TNF;TKK;PAC;PRL;IL1;IDC;UL1;UDC;SYS|1199}";
					}
					else if (active_max == 2) {
						message = "{FB;02;46|64:KDY;KMT;KYR;KT0;TNF;TKK;PAC;PRL;IL1;IDC;UL1;UDC;SYS|119A}";
					}
					else if (active_max == 3) {
						message = "{FB;03;46|64:KDY;KMT;KYR;KT0;TNF;TKK;PAC;PRL;IL1;IDC;UL1;UDC;SYS|119B}";
					}
					else if (active_max == 4) {
						message = "{FB;04;46|64:KDY;KMT;KYR;KT0;TNF;TKK;PAC;PRL;IL1;IDC;UL1;UDC;SYS|119C}";
					}
					else if (active_max == 5) {
						message = "{FB;05;46|64:KDY;KMT;KYR;KT0;TNF;TKK;PAC;PRL;IL1;IDC;UL1;UDC;SYS|119D}";
					}
					else if (active_max == 6) {
						message = "{FB;06;46|64:KDY;KMT;KYR;KT0;TNF;TKK;PAC;PRL;IL1;IDC;UL1;UDC;SYS|119E}";
					}
					else if (active_max == 7) {
						message = "{FB;07;46|64:KDY;KMT;KYR;KT0;TNF;TKK;PAC;PRL;IL1;IDC;UL1;UDC;SYS|119F}";
					}
					else if (active_max == 8) {
						message = "{FB;08;46|64:KDY;KMT;KYR;KT0;TNF;TKK;PAC;PRL;IL1;IDC;UL1;UDC;SYS|11A0}";
					}
					else if (active_max == 9) {
						message = "{FB;09;46|64:KDY;KMT;KYR;KT0;TNF;TKK;PAC;PRL;IL1;IDC;UL1;UDC;SYS|11A1}";
					}
					else {
						error_exit("ERROR invalid hardware address; currently works only for 9 maxes");
					}

					if (DEBUG) {
						sprintf(buffer, "Sending message: %s", message);
						debug_entry(buffer);
					}

					// Send message
					n = write(sockfd,message,strlen(message));
					if (n < 0) {
						close(sockfd);
						error_retry("ERROR sending TCP packet");
						failure_flag = 1;
						break;
					}

					// Read answer
					bzero(buffer, 256);
					n = read(sockfd, buffer, 255);
					for (counter = 0; counter < wait_interval && n < 0; counter++) {
						if (DEBUG)
							debug_entry("Socket contains no data, trying to read again later");
						usleep(10000);
						n = read(sockfd, buffer, 255);
					}

					if (n < 0) {
						close(sockfd);
						error_retry("ERROR receiving TCP packet");
						failure_flag = 1;
						break;
					}

					if (DEBUG) {
						sprintf(buffer2, "Received answer: %s", buffer);
						debug_entry(buffer2);
					}

					// send the incoming results per udp
					if(sendUDPMessage(&their_addr, server_sock, buffer) != 0)printf("error with sending message\n");

					// Extract the data fields from answer
					result = regexec(&rx, buffer, rx.re_nsub + 1, matches, 0);
					if (result) {
						regerror(result, &rx, buffer, sizeof(buffer));
						//error_exit("ERROR no regexp match");
						error_retry("ERROR no regexp match");
						// TODO Create flag for this kind of failure or rename this one
						failure_flag = 2;
						break;
					}

					// Convert the extracted data fields to integer values
					temp = strndup(buffer + matches[1].rm_so, matches[1].rm_eo - matches[1].rm_so);
//					tkdy[active_max-1] += strtol(temp, NULL, 16);
					kdy = strtol(temp, NULL, 16);
					free(temp);
					temp = strndup(buffer + matches[2].rm_so, matches[2].rm_eo - matches[2].rm_so);
//					tkmt[active_max-1] += strtol(temp, NULL, 16);
					kmt = strtol(temp, NULL, 16);
					free(temp);
					temp = strndup(buffer + matches[3].rm_so, matches[3].rm_eo - matches[3].rm_so);
//					tkyr[active_max-1] += strtol(temp, NULL, 16);
					kyr = strtol(temp, NULL, 16);
					free(temp);
					temp = strndup(buffer + matches[4].rm_so, matches[4].rm_eo - matches[4].rm_so);
//					tkt0[active_max-1] += strtol(temp, NULL, 16);
					kt0 = strtol(temp, NULL, 16);
					free(temp);
					temp = strndup(buffer + matches[5].rm_so, matches[5].rm_eo - matches[5].rm_so);
					ttnf[active_max-1] += strtol(temp, NULL, 16);
					free(temp);
					temp = strndup(buffer + matches[6].rm_so, matches[6].rm_eo - matches[6].rm_so);
					ttkk[active_max-1] += strtol(temp, NULL, 16);
					free(temp);
					temp = strndup(buffer + matches[7].rm_so, matches[7].rm_eo - matches[7].rm_so);
					tpac[active_max-1] += strtol(temp, NULL, 16) / 2;
					free(temp);
					temp = strndup(buffer + matches[8].rm_so, matches[8].rm_eo - matches[8].rm_so);
					tprl[active_max-1] += strtol(temp, NULL, 16);
					free(temp);
					temp = strndup(buffer + matches[9].rm_so, matches[9].rm_eo - matches[9].rm_so);
					til1[active_max-1] += strtol(temp, NULL, 16);
					free(temp);
					temp = strndup(buffer + matches[10].rm_so, matches[10].rm_eo - matches[10].rm_so);
					tidc[active_max-1] += strtol(temp, NULL, 16);
					free(temp);
					temp = strndup(buffer + matches[11].rm_so, matches[11].rm_eo - matches[11].rm_so);
					tul1[active_max-1] += strtol(temp, NULL, 16);
					free(temp);
					temp = strndup(buffer + matches[12].rm_so, matches[12].rm_eo - matches[12].rm_so);
					tudc[active_max-1] += strtol(temp, NULL, 16);
					free(temp);
					temp = strndup(buffer + matches[13].rm_so, matches[13].rm_eo - matches[13].rm_so);
//					tsys[active_max-1] += strtol(temp, NULL, 16);
					sys = strtol(temp, NULL, 16);
					free(temp);
				}
				if (failure_flag > 0){
					 break;
				}
				//TODO check if the task need more time than logavg_interval
				sleep(logavg_interval - (time(NULL)-single_start_time));
			}
			// Calculate the average values and insert into db
			if (failure_flag == 0){
				for (i = 0; i < nr_of_maxes; ++i) {
//					tkdy[i] = (int)((tkdy[i]*1.0)/(logavg_pertick*1.0));
//					tkmt[i] = (int)((tkmt[i]*1.0)/(logavg_pertick*1.0));
//					tkyr[i] = (int)((tkyr[i]*1.0)/(logavg_pertick*1.0));
//					tkt0[i] = (int)((tkt0[i]*1.0)/(logavg_pertick*1.0));
					ttnf[i] = (int)((ttnf[i]*1.0)/(logavg_pertick*1.0));
					ttkk[i] = (int)((ttkk[i]*1.0)/(logavg_pertick*1.0));
					tpac[i] = (int)((tpac[i]*1.0)/(logavg_pertick*1.0));
					tprl[i] = (int)((tprl[i]*1.0)/(logavg_pertick*1.0));
					til1[i] = (int)((til1[i]*1.0)/(logavg_pertick*1.0));
					tidc[i] = (int)((tidc[i]*1.0)/(logavg_pertick*1.0));
					tul1[i] = (int)((tul1[i]*1.0)/(logavg_pertick*1.0));
					tudc[i] = (int)((tudc[i]*1.0)/(logavg_pertick*1.0));
//					tsys[i] = (int)((tsys[i]*1.0)/(logavg_pertick*1.0));

					// Construct the query according to active solarmax
					sprintf(query, "INSERT INTO %s%d (kdy, kmt, kyr, kt0, tnf, tkk, pac, prl, il1, idc, ul1, udc, sys) VALUES (%d, %d, %d, %d, %d, %d, %d, %d, %d, %d, %d, %d, %d);", dbtabprefix, (i+1), kdy, kmt, kyr, kt0, (int)ttnf[i], (int)ttkk[i], (int)tpac[i], (int)tprl[i], (int)til1[i], (int)tidc[i], (int)tul1[i], (int)tudc[i], sys);
					if (DEBUG) {
						sprintf(buffer, "Executing query: %s", query);
						debug_entry(buffer);
					}
					// Execute the query to write the data into db
					mysql_query(connection, query);
					if (mysql_errno(connection))
						error_exit(mysql_error(connection));
				}

			}

			// Wait for the specified number of seconds - calc duration - 1
			if (DEBUG)
				debug_entry("Waiting for about 1 minute ...");
			// Get the current time
			time_t stop_time = time(NULL);
			// TODO check if time needed is > log_interval
			int sleepTime = log_interval + start_time - stop_time - 1;
			if(sleepTime > 0)
				sleep(sleepTime);
			else{
				sprintf(buffer, "!!! sleepTime error. Assuming desync: %i seconds. !!!\n", sleepTime);
				debug_entry(buffer);
			}


			// Add a busy-loop for the last second to make sure we are perfectly accurate
			while (time(NULL) < start_time + log_interval) usleep(99999);

			// If the connection is lost -> retry
			if (failure_flag == 1){
				// just to be sure
				close(sockfd);
				break;
			}
		}
	}
	return 0;
}

