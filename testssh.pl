#!/usr/bin/perl
use strict;
use warnings;
use Net::OpenSSH;
use Config::Tiny;

# Input variable check
if (! defined($ARGV[0])) {
	print "No config file defined!\n";
	print "Syntax: testssh.pl [config_file]\n\n";
	exit;
} elsif ($ARGV[0] eq "-h" || $ARGV[0] eq "--help") {
	print "Syntax: testssh.pl [config_file]\n\n";
	exit;
};

# Get contents of config file
my $config = Config::Tiny->read($ARGV[0]);

# Set VAR of config elements
my $DIR = $config->{_}->{BASE_DIRECTORY};
my $DEVICE_LIST = $config->{_}->{DEVICE_LIST};
my $USERNAME = $config->{_}->{USERNAME};
my $PASS_FILE = $config->{_}->{PASS_FILE};

############################################################################
# ParseDeviceList - Parse name & IP from device list and output list format
# my %DEVICE_HASH = ParseDeviceList <DEVICE_LIST>;
############################################################################
sub ParseDeviceList {
	# Exclude lines that are comments and blank
	my @DEVICES = grep (!(/#/|/^\n/),@_);
	chomp(@DEVICES);
	my @DEVICE_ARRAY;
	foreach (@DEVICES) {
		if ($_ =~ "=") {
			# Parse NAME=IP format
			my ($name,$ip) = split("=",$_);
			push (@DEVICE_ARRAY,$name);
			push (@DEVICE_ARRAY,$ip);
		} else {
			push (@DEVICE_ARRAY,$_);
			push (@DEVICE_ARRAY,"NULL");
		};
	};
	return @DEVICE_ARRAY;
};

############################################################################
# DetectShell - find out which shell the device is in
# my $shell = DetectShell $_,$ssh 
############################################################################
sub DetectShell {
	my ($DEVICE,$SSH) = (@_);
	my ($output,$errput) = $SSH->capture2("echo hello");
	my ($SHELL,$errput2);
	chomp $output;
	# if output is hello the shell is bash
	if ($output eq "hello")  {
		$SHELL = "bash";
	};
	($output,$errput2) = $SSH->capture2("show sys version");
	chomp $output;
	# If output contains Sys::Version then shell is TMSH
	if ($output =~ "Sys::Version")  {
		$SHELL = "tmsh";
	};
	unless ($SHELL) {
		print "Error: Can't detect shell for $DEVICE - $output - $errput - $errput2.\n" ;
		next;
	};
	return $SHELL
};


############################################################################################
# *************************************** MAIN PROGRAM *************************************
############################################################################################

print "Opening device list file $DIR/$DEVICE_LIST.\n";
open DEVICE_LIST,"<","$DIR/$DEVICE_LIST" or die "Cannot open device list - $!.\n";
my %DEVICE_HASH = ParseDeviceList <DEVICE_LIST>;
close DEVICE_LIST;

# Get password from file
print "Opening password file $PASS_FILE.\n";
open PSWD,"<","$PASS_FILE" or die "Error: Can't open password file - $!\n" ;
my $PASSWORD = <PSWD>;
chomp $PASSWORD;
close PSWD;

foreach (keys %DEVICE_HASH) {
	print "\nConnecting to $_.\n";

	# Get IP from DB or set to hostname if NULL
	my $IP = $DEVICE_HASH{$_};
	$IP = $_ if ($IP eq 'NULL');

	
	# Open SSH connection to host
	CONNECT:
	my $ssh = Net::OpenSSH->new($IP,
		user=>$USERNAME,
		passwd =>$PASSWORD,
		timeout => 5,
		master_stderr_discard => 1,
	);
	if (length($ssh->error) > 1) {
		my $error = $ssh->error;
		print "  Error: Can't connect to $_ - $error.\n" ;
		# If error is from key missing in known hosts file
		if (grep /known_hosts/,$error) {
			# Get key from device and make thumbprint. Display to user.
			my $key = `ssh-keyscan $IP 2> /dev/null` ;
			my $thumb = `key="$key"; ssh-keygen -lf /dev/stdin  <<<\$key | cut -d ' ' -f2; unset key`;
			chomp ($key,$thumb);
			print "  \n  The authenticity of host '$_ ($IP)' can't be established.\n";
			print "  RSA key fingerprint is $thumb. \n";
			
			# Ask user if they would like to add key. Ask until they give valid answer of yes/no
			ASK:
			print "  Do you want to add the key to ~/.ssh/known_hosts file? (yes/no): ";
			my $answer = <STDIN>;
			chomp $answer;
			if ($answer eq "yes") {
				# Check if key is already in known hosts file
				my $key_match = `cat ~/.ssh/known_hosts | grep $IP | wc -l`;
				chomp $key_match;
				if ($key_match) {
					# Error if key is in file
					print "  Key for $_ ($IP) is already in ~/.ssh/known_hosts file."
						." Please manually remove the offending key and re-run the script.\n";
					next;
				} else {
					# Append to known hosts file and re-attempt SSH connection 
					print "  Adding $_ key to ~/.ssh/known_hosts file.\n";
					`echo "$key" >> ~/.ssh/known_hosts`;
					goto CONNECT; 
				}
			} elsif ($answer eq "no") {
				# Skip is user does not want to add key
				print "  Skipping to next device.\n";
				next;
			} else {
				# Ask again is answer is not valid
				goto ASK;
			};
		};
		# If error is anything else then skip to next device.
		next;
	};

	# Detect device shell
	my $shell = DetectShell $_,$ssh;	
	print "  Shell for $_ is $shell.\n";
	sleep 1;
	undef $ssh;
};
print "\nDone.\n";
