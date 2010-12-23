#!/usr/bin/perl

#
# Author: Georg Büchele © 2010 georg.buechele@web.de
# Co Author: Stefan Riedelsheimer © 2010 stefanos-web@arcor.de
# PERL Script needed PERL 5.xx and higher 
# PERL Module needed perl-mysql (muss teilweise nachinstalliert werden.)
# licensed under GPLv2 or later http://www.gnu.org/licenses/gpl2.html


		use DBI();
		use IO::Socket;
		
		my $error_file ;
		my $error_file_name ;
		my $error_mode = '>';

		my $debug_file ;
		my $debug_file_name;
		my $debug_mode = '>';

		my $log_interval;


		my $message;
		my $expression = "...=([0-9A-F]*);"x12 . "...=([0-9A-F]*)";
		my $value;
		my $buffer;
		my $query;

		my $connection;

		my $dbname;
		my $debug;
		my $dbhost;
		my $loginterval;
		my $dbuser;
		my $dbpass;
		my $hostname;
		my $hostport;
		my $numberofinverters;
		my $waitinterval;
		my $dbtabprefix;
		my $proto;

		  open(CONFIG,"$ARGV[0]") or die $_;
		  while (<CONFIG>) {
			   chomp;
			   next if /^\s*\#/;
			   next unless /=/;
			  ($key, $variable) = split(/=/,$_,2);
			  $variable =~ s/(\$(\w+))/$config{$2}/g;
			  $config{$key} = $variable;
			 if ($key eq "debug") { $debug = $variable;	} 
			 if ($key eq "loginterval") { $loginterval = $variable; } 
			 if ($key eq "waitinterval") {$waitinterval = $variable; } 
			 if ($key eq "dbhost") { $dbhost = $variable; } 
			 if ($key eq "dbname") { $dbname = $variable; } 
			 if ($key eq "dbtabprefix") {$dbtabprefix = $variable; } 
			 if ($key eq "dbuser") { $dbuser = $variable; } 
			 if ($key eq "dbpass") { $dbpass = $variable; } 
			 if ($key eq "hostname") { $hostname = $variable; } 
			 if ($key eq "hostport") { $hostport = $variable; } 
			 if ($key eq "numberofinverters") { $numberofinverters = $variable; } 
			 if ($key eq "proto") { $proto = $variable; } 
			 if ($key eq "error_file") { $error_file = $variable; } 
			 if ($key eq "error_file_name") { $error_file_name = $variable; } 
			 if ($key eq "debug_file") { $debug_file = $variable; } 
			 if ($key eq "debug_file_name") { $debug_file_name = $variable; } 
			 if ($key eq "message") { $message = $variable; } 
			}

		sub error_exit
		{

		  # TODO print to error instead to out
		  my $msg = shift @_;
		  open (STDOUT, "| tee -ai $debug_file_name");
		  print  "$msg\n";
		  close (STDOUT);

		  if(defined(fileno $error_file))
		  {
			close $error_file;
		  }
		  

		  if(defined(fileno $debug_file))
		  {
			close $debug_file;
		  }
		  
		}



		sub debug_entry
		{
		  my $msg = shift @_;
		  my $timestamp = time;
		  my $debug_msg;
		  my $time_now = localtime $timestamp;
		  if(!defined(fileno $debug_file))
		  {
			error_exit "ERROR writing to debug.log file";
		  }
		  $debug_msg = "$time_now $msg";
		  if ($debug eq "1") 
		  {
		  open (STDOUT, "| tee -ai $debug_file_name");
		  print  "$debug_msg\n";
		  close (STDOUT);
		  }
		}


		sub error_retry
		{
		  my $msg = shift @_;
		  my $timestamp = time;
		  my $error_msg;
		  my $time_now = localtime $timestamp;
		  if(!defined(fileno $error_file))
		  {
			error_exit "ERROR writing to error.log file";
		  }
		  $error_msg = "$time_now $msg";
		  if ($debug eq "1")
		  {
		  open (STDOUT, "| tee -ai $error_file_name");
		  print "$error_msg\n";
		  close (STDOUT);
		  }
		}

		# Try to open error log file

		my $is_error_file_open = open($error_file, $error_mode, $error_file_name);
		if(!defined($is_error_file_open))
		{
		  error_exit "ERROR opening error.log file";
		}

		# Make file unbuffered
		# TODO setbuf(error_file, NULL);


		# Try to open debug log file, if necessary
		if($debug)
		{

		  my $is_debug_file_open = open($debug_file, $debug_mode, $debug_file_name);
		  if(!defined($is_debug_file_open))
		  {
			error_exit "ERROR opening debug.log file";
		  }
		  # Make file unbuffered
		  # TODO setbuf(debug_file, NULL);
		}


		# Connect to database
		$connection = DBI->connect("DBI:mysql:database=$dbname;host=$dbhost",
								 $dbuser, $dbpass,
								 {'RaiseError' => 1});

		$connection->{mysql_auto_reconnect} = 1;


		if($debug)
		{
		  $buffer = "Connected to database $dbname on host $dbhost";
		  debug_entry($buffer);
		}
		
		
		while(1) {

		  # The socket works on auto reconnect mode

		  # Try to open socket for communication with solarmax
		  # Try to resolve solarmax address/hostname
		  # Try to establish a connection with solarmax
		  # Make socket non-blocking
		  $sockfd = new IO::Socket::INET(
			Proto => $proto,
			PeerAddr => $hostname,
			PeerPort => $hostport,
			Type => SOCK_STREAM,
			Timeout => 5,);
			
			unless ($sockfd) 
				{debug_entry "Socket $hostname:$hostport kann nicht erstellt werden: $!\n";
				sleep ($waitinterval);
				next;
				}
			
			if (!defined($sockfd)) 
			  {
			   debug_entry "Socket kann nicht erstellt werden: $!\n" ;
			   sleep ($waitinterval);
			   }
			else
				{
				  my $new_sockfd = $sockfd->accept(); debug_entry "<<Verbunden>>";		  
				  
				  $sockfd->autoflush(1);

				  if ($debug)
				  {
					$buffer = "Connected to solarmax $hostname on port $hostport";
					debug_entry($buffer);
				  }

				  # Start sending the data requests and logging the answers
				  while(1)
				  {
					# Get the current time
					my $start_time = time;

					if ($debug)
					{
					  $buffer = "Sending message: $message";
					  debug_entry($buffer);
					  break;
					}


					# Send message
					# print $sockfd $message;
					$sockfd->send("$message");
					my $sendresult =  $sockfd->send("$message");
					if (!defined($sendresult))
					{
					 $sockfd->close();
					 error_retry("ERROR sending TCP packet");
					 redo;
					}
					# Read answer
					$value="";
					my $sign = '';
					while($sign ne '}')
					  {
					  $sockfd->recv($sign, 1);
					  $value .= $sign;
					  }

					if( length $value <= 0)
					{
					  close($sockfd);
					  error_retry("ERROR receiving TCP packet");
					}


					if ($debug)
					{
					  $buffer = "Received answer: $value";
					  debug_entry($buffer);
					}


					# Extract the data fields from answer
					if( $value =~ /$expression/  )
					{
					  # Convert the extracted data fields to integer values
					  $kdy = hex $1;
					  $kmt = hex $2;
					  $kyr = hex $3;
					  $kt0 = hex $4;
					  $tnf = hex $5;     
					  $tkk = hex $6;
					  $pac = ((hex $7) / 2);
					  $prl = hex $8;
					  $il1 = hex $9;
					  $idc = hex $10;
					  $ul1 = hex $11;
					  $udc = hex $12;
					  $sys = hex $13;
					}
					else
					{
					  # TODO regerror(result, &rx, buffer, sizeof(buffer));
					  error_exit("ERROR no regexp match");
					}

					# Construct the query
					my $database1 = "$dbtabprefix$numberofinverters";
					$query = "INSERT INTO $database1 (kdy, kmt, kyr, kt0, tnf, tkk, pac, prl, il1, idc, ul1, udc, sys) VALUES ($kdy, $kmt, $kyr, $kt0, $tnf, $tkk, $pac, $prl, $il1, $idc, $ul1, $udc, $sys);";
					# print "\n QUERY: $query \n";

					if ($debug)
					{
					  $buffer = "Executing query: $query";
					  debug_entry($buffer);
					}

					# Execute the query to write the data into db
					my $statement = $connection->prepare($query);
					$statement->execute;
					$statement->finish;

					my $errno = $connection->{'mysql_errno'};
					if( $errno != 0)
					{
					  my $error = $connection->{'mysql_error'};
					  error_exit($error);

					}

					# Get the current time
					my $stop_time = time;

					# Wait for the specified number of seconds - calc duration - 1
					# sleep(log_interval + start_time - stop_time - 1);


					# Add a busy-loop for the last second to make sure we are perfectly accurate
					# TODO sleep time is 1s instead of 100ms, we have no usleep
					# At the moment the following statement doesn't do the job
					# while (time < $start_time + $log_interval) { sleep(1); }
					sleep ($loginterval) ;
				  }
				}

				# Disconnect from database
				$connection->disconnect();

				if($debug)
				{
				  $buffer = "Disconnected from database $dbname on host $dbhost";
				  debug_entry($buffer);
				}


				if(defined($sockfd))
				{
				  close $sockfd;

				}
				
				if(!defined($sockfd))
				{
				  close $sockfd;

				}
			}
