#!/opt/bin/perl

# Author: Stefan Riedelsheimer © 2010 stefanos-web@arcor.de
# licensed under GPLv2 or later http://www.gnu.org/licenses/gpl2.html


use DBI();
use IO::Socket;


my $DEBUG = 1;

my $error_file;
#my $error_file_name = "/var/log/solarmax-error.log";
my $error_file_name = "./solarmax-error.log";
my $error_mode = '>';

my $debug_file;
#my $debug_file_name = "/var/log/solarmax-debug.log";
my $debug_file_name = "./solarmax-debug.log";
my $debug_mode = '>';

my $log_interval;

my $dbhost = "localhost";
my $dbname;
my $dbuser;
my $dbpass;
my $message = "{FB;01;46|64:KDY;KMT;KYR;KT0;TNF;TKK;PAC;PRL;IL1;IDC;UL1;UDC;SYS|1199}\n";
my $expression = "...=([0-9A-F]*);"x12 . "...=([0-9A-F]*)";
my $value;

my $buffer;
my $query;

my $connection;


sub error_exit
{

  # TODO print to error instead to out
  my $msg = shift @_;
  print "$msg\n";

  if(defined(fileno $error_file))
  {
    close $error_file;
  }
  

  if(defined(fileno $debug_file))
  {
    close $debug_file;
  }
  die; # TODO does die return 0?
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

  # test if file operations in perl work like this
  print $debug_file "$debug_msg\n";

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

  print $error_file "$error_msg\n";
}







# Check commandline arguments
my $argc = @ARGV;
if( $argc < 3)
{
  error_exit "ERROR program needs hostname, port and loginterval (in seconds) as parameters";
}


# Try to open error log file

$is_error_file_open = open($error_file, $error_mode, $error_file_name);
if(!defined($is_error_file_open))
{
  error_exit "ERROR opening error.log file";
}

# Make file unbuffered
# TODO setbuf(error_file, NULL);


# Try to open debug log file, if necessary
if($DEBUG)
{

  $is_debug_file_open = open($debug_file, $debug_mode, $debug_file_name);
  if(!defined($is_debug_file_open))
  {
    error_exit "ERROR opening debug.log file";
  }
  # Make file unbuffered
  # TODO setbuf(debug_file, NULL);
}


# Get log interval from command line argument
$log_interval = $ARGV[2];

# Get dbname, dbuser and dbpass
my $dbname = $ARGV[3];
my $dbuser = $ARGV[4];
my $dbpass = $ARGV[5];


# Connect to database
$connection = DBI->connect("DBI:mysql:database=$dbname;host=$dbhost",
                         $dbuser, $dbpass,
                         {'RaiseError' => 1});

$connection->{mysql_auto_reconnect} = 1;


if($DEBUG)
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
  $sockfd = IO::Socket::INET->new(
    Proto => "tcp",
    PeerAddr => $ARGV[0],
    PeerPort => $ARGV[1],
   );

   
  if(!defined($sockfd))
  {
      error_retry("Can't connect to solarmax $ARGV[0], $ARGV[1]");
      sleep(60);
      redo;
  }
  
  
  $sockfd->autoflush(1);

  if ($DEBUG)
  {
    $buffer = "Connected to solarmax $ARGV[0] on port $ARGV[1]";
    debug_entry($buffer);
  }

  # Start sending the data requests and logging the answers
  while(1)
  {
    # Get the current time
    my $start_time = time;

    if ($DEBUG)
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
     break;
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


    if ($DEBUG)
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
    $query = "INSERT INTO logsolarmax1 (kdy, kmt, kyr, kt0, tnf, tkk, pac, prl, il1, idc, ul1, udc, sys) VALUES ($kdy, $kmt, $kyr, $kt0, $tnf, $tkk, $pac, $prl, $il1, $idc, $ul1, $udc, $sys);";
     print "\n QUERY: $query \n";

    if ($DEBUG)
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
    sleep ($log_interval) ;
  }
}

# Disconnect from database
$connection->disconnect();

if($DEBUG)
{
  $buffer = "Disconnected from database $dbname on host $dbhost";
  debug_entry($buffer);
}


if(defined($sockfd))
{
  close $sockfd;

}

exit(0);






