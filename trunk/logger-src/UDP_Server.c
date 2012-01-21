/*
 ** broadcaster.c -- a datagram "client" like talker.c, except
 **                  this one can broadcast
 */

#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <errno.h>
#include <string.h>
#include <sys/types.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <arpa/inet.h>
#include <netdb.h>

// TODO nicer names ;)
int createAddress(char *address, int port, struct sockaddr_in *their_addr) {
	//struct sockaddr_in their_addr; // connector's address information
	struct hostent *he;
	if ((he = gethostbyname(address)) == NULL) { // get the host info
		return 1;
	}
	their_addr->sin_family = AF_INET; // host byte order
	their_addr->sin_port = htons(port); // short, network byte order
	their_addr->sin_addr = *((struct in_addr *) he->h_addr);
	memset(their_addr->sin_zero, '\0', sizeof(their_addr->sin_zero));
	return 0;
}
int createSock(int *sockfd) {
	//int sockfd;
	int broadcast = 1;
	//char broadcast = '1'; // if that doesn't work, try this
	if ((*sockfd = socket(AF_INET, SOCK_DGRAM, 0)) == -1) {
		return 1;
	}
	// this call is what allows broadcast packets to be sent:
	if (setsockopt(*sockfd, SOL_SOCKET, SO_BROADCAST, &broadcast,sizeof broadcast) == -1) {
		return 2;
	}
	return 0;
}
int sendUDPMessage(struct sockaddr_in *their_addr, int sockfd, char *message){
	int numbytes;
	if ((numbytes = sendto(sockfd, message, strlen(message), 0, (struct sockaddr *) their_addr, sizeof (*their_addr))) == -1) {
		return 1;
	}
	return 0;
}

int main_example(int argc, char *argv[]) {
	printf("wtf?");
	struct sockaddr_in their_addr;
	int sockfd;
	printf("wtf?");
	if(createAddress("192.168.0.255", 4950, &their_addr) != 0)printf("error with address creation\n");
	if(createSock(&sockfd) != 0)printf("error with socket creation\n");
	while(sendUDPMessage(&their_addr, sockfd, "test tes test") == 0){
		sleep(5);
	}
	printf("%s\n", "done");
	return 0;
}
