/*
 * UDP_Server.h
 *
 *  Created on: 21.01.2012
 *      Author: klaas
 */

#ifndef UDP_SERVER_H_
#define UDP_SERVER_H_

int createAddress(char *address, int port, struct sockaddr_in *their_addr);
int createSock(int *sockfd);
int sendUDPMessage(struct sockaddr_in *their_addr, int sockfd, char *message);

#endif /* UDP_SERVER_H_ */
