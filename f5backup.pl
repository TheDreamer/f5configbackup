#!/bin/perl

# F5 Config backup 2.0 - Rewriten in perl
# Version 2.0.1 -
# 	Added SSH using Net::OpenSSH
# Version 2.0.2 -
#	Added device folder creation
# Version 2.0.3 -
#	Add file based config method
# Version 2.0.5 -
#	Bug fixes - Fixed/improved error handling
#
#

use strict;
use warnings;
use DateTime;
use Net::OpenSSH;
use Config::Tiny;

# Input variable check
if (! defined($ARGV[0])) {
	print "No config file defined!\n";
	print "Syntax: f5backup.pl [config_file]\n\n";
	exit;
} elsif ($ARGV[0] eq "-h" || $ARGV[0] eq "--help") {
	print "Syntax: f5backup.pl [config_file]\n\n";
	exit;
};

# Get contents of config file
my $config = Config::Tiny->read($ARGV[0]);

# Set VAR of config elements
my $DIR = $config->{_}->{DIRECTORY};
my $ARCHIVE_SIZE = $config->{_}->{ARCHIVE_SIZE};
my $DEVICE_LIST = $config->{_}->{DEVICE_LIST};
my $SSH_KEY = $config->{_}->{SSH_KEY};

# Set date
my $DATE = DateTime->now->ymd("-");

# Open files/arrays for logging
open LOG,"+>$DIR/log/$DATE-backup.log";
print LOG "Starting configuration backup on $DATE at ",DateTime->now->hms,".\n";
my $ERROR = 0;

# Open device list, set into array and chomp
open DEVICE_LIST,"<$DIR/$DEVICE_LIST" or die "Cannot open device list - $!.\n";
my @DEVICE_LIST = <DEVICE_LIST>;
chomp(@DEVICE_LIST);

# Loop though device list
foreach (@DEVICE_LIST) {
	print LOG "\nConnecting to $_ at ",DateTime->now->hms,".\n";

	# Create device folder is it does not exist
	unless (opendir(DIRECTORY,"$DIR/devices/$_")) {
		print LOG "Device directory $DIR/devices/$_ does not exist. Creating folder $_ at ",DateTime->now->hms,".\n";
		my $NEW_DIR = "$DIR/devices/$_";
		unless (mkdir $NEW_DIR,0755) {
			print LOG "Error: Cannot create folder $_ - $!.\n" ;
			$ERROR++;
			next;
		};
	};
	
	# Open SSH connection to host
	my $ssh = Net::OpenSSH->new($_,
		user=>'root',
		key_path=>$SSH_KEY,
		master_stderr_discard => 1,
		timeout => 5,
	);
	if (length($ssh->error) > 1) {
		print LOG "Error at ",DateTime->now->hms,": Can't connect to $_ - ",$ssh->error, ".\n" ;
		$ERROR++ ;
		next;
	};

	# get hash from device and write to VAR
	my ($NEW_HASH,$errput) = $ssh->capture2("tmsh list | sha1sum | cut -d ' ' -f1");
	chomp ($NEW_HASH,$errput);
	if (length($errput) != 0) { 
		print LOG "Error: Get hash failed for $_: $errput.\n" ;
		$ERROR++;
		next ;
	};
	print LOG "Hash for $_ is - $NEW_HASH.\n";
	undef $errput;

	# Check for new hash and break if it does not exist
	if (! defined($NEW_HASH) || length $NEW_HASH != 40) {
		print LOG "Get HASH failed for $_ at ",DateTime->now->hms,". Skipping to next device.\n";
		$ERROR++;
		next;
	};

	# Check for old hash. if not present the set OLD_HASH to null
	my $OLD_HASH = "";
	if (open DEVICE_HASH,"<$DIR/devices/$_/backup-hash") {
		$OLD_HASH = <DEVICE_HASH> ;
		close DEVICE_HASH;
	};

	# Compare old hash to new hash
	if ($OLD_HASH eq $NEW_HASH) {
		print LOG "Hashes match for $_ at ",DateTime->now->hms,". Configuration unchanged. Skipping download.\n";
	} else {
		# Make device create UCS file
		print LOG "Hashes do not match for $_ at ",DateTime->now->hms,". Downloading backup file.\n";
		my ($output,$errput) = $ssh->capture2("tmsh save sys ucs /shared/tmp/backup.ucs");
		chomp ($output,$errput);
		print LOG "Making device create UCS - $errput.\n" ;
		print LOG "Result - $output .\n";
		undef $output,$errput;

		# Download file
		print LOG "Downloading UCS file at ",DateTime->now->hms,".\n";
		my $UCS_FILE = "/var/f5backup/devices/$_/$DATE-$_-backup.ucs";
		$ssh->scp_get({},'/shared/tmp/backup.ucs',$UCS_FILE);
		if (length($ssh->error) > 1) {
			print LOG "Error: UCS file download failed - ",$ssh->error, ".\n" ;
			$ERROR++;
			next;
		};
		undef $ssh;

		# Write new hash to file
		print LOG "Overwriting old hash file at ",DateTime->now->hms,".\n";
		if (open(NEW_HASH,"+>$DIR/devices/$_/backup-hash")) {
			print NEW_HASH $NEW_HASH ;
			close NEW_HASH;
		} else {
			print LOG "Error: Could now write new hash file for $_ - $! .\n" ;
			$ERROR++;
		};
	}
}

#  Add deletion note to log file
print LOG "\nDeleting old files:\n";

# Keep only the number of UCS files specified by ARCHIVE_SIZE and write deletion to log
foreach (@DEVICE_LIST) {
	my $DEVICE = $_;
	if (opendir(DIRECTORY,"$DIR/devices/$DEVICE")) { 
		my @DIRECTORY = readdir(DIRECTORY);
		@DIRECTORY = reverse sort grep(/backup.ucs/,@DIRECTORY); 
		foreach (@DIRECTORY[$ARCHIVE_SIZE..($#DIRECTORY)]) {
			print LOG "Deleting backup file at ",DateTime->now->hms,": $DEVICE/$_.\n" ;
			unlink ("$DIR/devices/$DEVICE/$_") or print LOG "Error: Cannot delete $DIR/devices/$DEVICE/$_ - $!.\n" and $ERROR++;
		};
		closedir DIRECTORY;
	} else {
		print LOG "Error: Can not open directory $DIR/devices/$DEVICE/ - $!.\n" ;
		$ERROR++ ;
		next;
	};
};

# Keep only the number of log files specified by ARCHIVE_SIZE and write deletion to log
if (opendir(DIRECTORY,"/var/f5backup/log/")) {
	my @DIRECTORY = readdir(DIRECTORY);
	@DIRECTORY = reverse sort grep(/backup.log/,@DIRECTORY);
	foreach (@DIRECTORY[$ARCHIVE_SIZE..($#DIRECTORY)]) {
		print LOG "Deleting log file at ",DateTime->now->hms,": $_.\n" ;
		unlink("$DIR/log/$_") or print LOG "Error: Cannot delete $DIR/log/$_ - $!.\n" and $ERROR++;
	};
	closedir DIRECTORY;
} else {
	print LOG "Error: Can not open log directory: $!.\n" ;
	$ERROR++;
};

# All done
print LOG "\nBackup job completed.\n";

# Check number of errors. Print line if > 0
print LOG "There are $ERROR error(s).\n" if ($ERROR > 0);
close LOG;
