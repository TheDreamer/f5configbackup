#!/bin/perl

# F5 Config backup 2.0 - Port to perl
#
# To do -
# 	Use config file rather then cmd vars
# 	Add device folder creation
# 	Use perl module for SSH/SCP
# 
#
# 

use strict;
use warnings;
use DateTime;

# Input variable check
if (! defined($ARGV[0])) {
	print "No device list defined!\n";
	print "Syntax: f5backup.pl [device_list] [backup_directory]\n\n";
	exit;
} elsif ($ARGV[0] eq "-h" || $ARGV[0] eq "--help") {
	print "Syntax: f5backup.pl [device_list] [backup_directory]\n\n";
	exit;
} elsif (! defined($ARGV[1])) {
	print "No directory define.\n";
	print "Syntax: f5backup.pl [device_list] [backup_directory]\n\n";
	exit;
};

# Set directory 
my $DIR = $ARGV[1];

# Set number of file to maintain
my $ARCHIVE_SIZE = 15;

# Set date
my $DATE = DateTime->now->ymd("-");

# Open arrays for logging
open LOG,"+>$DIR/log/$DATE-backup.log";
print LOG "Starting configuration backup on $DATE at ",DateTime->now->hms,".\n";
my @ERROR = "The following errors have occured:\n";

# Open device list, set into array and chomp
open DEVICE_LIST,"<$DIR$ARGV[0]" or die "Cannot open device list - $!\n";
my @DEVICE_LIST = <DEVICE_LIST>;
chomp(@DEVICE_LIST);

# Loop though device list
foreach (@DEVICE_LIST) {
	print LOG "\nConnecting to $_ at ",DateTime->now->hms,".\n";

	# get hash from device and write to VAR
	my $NEW_HASH = `ssh root\@$_ tmsh list | sha1sum | cut -d ' ' -f1` or push(@ERROR,"Error: $!\n") and print LOG "Error: $!\n" and next;
	chomp $NEW_HASH;
	print LOG "Hash for $_ is - $NEW_HASH.\n";

	# Check for new hash and break if it does not exist
	if (! defined($NEW_HASH) || length $NEW_HASH != 40) {
		print LOG "Get HASH failed for $_ at ",DateTime->now->hms,". Skipping to next device.\n";
		push(@ERROR,"Get HASH failed for $_ at ",DateTime->now->hms,".\n");
		next;
	}

	# Check for old hash. if not present the set OLD_HASH to null
	my $OLD_HASH = "";
	if (open DEVICE_HASH,"<$DIR/devices/$_/backup-hash") {
		$OLD_HASH = <DEVICE_HASH> ;
		close DEVICE_HASH;
	} 

	# Compare old hash to new hash
	if ($OLD_HASH eq $NEW_HASH) {
		print LOG "Hashes match for $_ at ",DateTime->now->hms,". Configuration unchanged. Skipping download.\n";
	} else {
		# Download backup file
		print LOG "Hashes do not match for $_ at ",DateTime->now->hms,". Downloading backup file.\n";
		print LOG `ssh root\@$_ tmsh save sys ucs /shared/tmp/backup.ucs` or push(@ERROR,"Error - Unable to create UCS file at ",DateTime->now->hms," $!\n") and print LOG "Error - Unable to create UCS file at ",DateTime->now->hms," $!\n" and next;
		print LOG `scp -r root\@$_:/var/tmp/backup.ucs /var/f5backup/devices/$_/$DATE-$_-backup.ucs`;
		# Write new hash to file
		print LOG "Overwriting old hash file at ",DateTime->now->hms,"\n";
		open NEW_HASH,"+>$DIR/devices/$_/backup-hash";
		print NEW_HASH $NEW_HASH ;
		close NEW_HASH;
	}
}

#  Add deletion note to log file
print LOG "\nDeleting old files:\n";

# Keep only the the most current 15 files and write deletion to log
foreach (@DEVICE_LIST) {
	my $DEVICE = $_;
	opendir(DIRECTORY,"$DIR/devices/$DEVICE") or push(@ERROR,"Error at ",DateTime->now->hms,": $!\n") and next;
	my @DIRECTORY = readdir(DIRECTORY);
	@DIRECTORY = reverse sort grep(/backup.ucs/,@DIRECTORY); 
	foreach (@DIRECTORY[$ARCHIVE_SIZE..($#DIRECTORY)]) {
		print LOG "Deleting backup file at ",DateTime->now->hms,": $DEVICE/$_\n" ;
		unlink ("$DIR/devices/$DEVICE/$_") or push(@ERROR,"Error deleting $_: $!\n") and print LOG "Error: $!\n" and next;
	};
	closedir DIRECTORY
}

# Delete old log files
opendir(DIRECTORY,"/var/f5backup/log/") or push(@ERROR,"Error: $!\n") and next;
my @DIRECTORY = readdir(DIRECTORY);
@DIRECTORY = reverse sort grep(/backup.log/,@DIRECTORY);
foreach (@DIRECTORY[$ARCHIVE_SIZE..($#DIRECTORY)]) {
	print LOG "Deleting log file at ",DateTime->now->hms,": $_\n" ;
	unlink ("$DIR/log/$_") or push(@ERROR,"Error deleting $_: $!\n") and print LOG "Error: $!\n" and next;
};
closedir DIRECTORY;

# Check error file for lines
if ($#ERROR > 0) {
	print LOG "\nError: There are errors present. Please check the error log.\n";
	open ERROR_LOG,"+>$DIR/log/$DATE-error.log";
	print ERROR_LOG @ERROR ;
	close ERROR_LOG;
}

print LOG "\nBackup job completed.\n";
close LOG;
