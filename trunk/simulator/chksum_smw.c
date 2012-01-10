#include <stdio.h>
#include <string.h>

// compile with:
// gcc -o chksum_smw chksum_smw.c
// call it with:
// ./chksum_smw string

char tmpmess[512];
char checksumstr[5];
int lengthtotal;
char message[512];

int checksum16(char* text) {
	int i;
	int sum = 0;
	for (i = 0; text[i] != '\0'; i++) {
		sum += text[i];
		sum %= 65536;
	}
	return sum;
}

int main( int argc, char *argv[] ) {
	int lengthbody = strlen (argv[1]);
	lengthtotal = 1 + 8 + lengthbody + 4 + 1;
	sprintf(tmpmess, "FB;01;%x%s", lengthtotal, argv[1]);
	int checksum = checksum16(tmpmess);
	sprintf(checksumstr,"%04x", checksum );
	strcat(tmpmess,checksumstr);
	sprintf(message,"{%s}", tmpmess);
	printf("%s\n", message);
	return 0;
}
