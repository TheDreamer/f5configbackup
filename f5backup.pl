#!/usr/bin/perl

############################ LICENSE #################################################
## F5 Config backup script. Perl script to manage daily backups of F5 BigIP devices
## Copyright (C) 2013 Eric Flores
##
## This program is free software; you can redistribute it and/or
## modify it under the terms of the GNU General Public License
## as published by the Free Software Foundation; either version 2
## of the License, or any later version.
##
## This program is distributed in the hope that it will be useful,
## but WITHOUT ANY WARRANTY; without even the implied warranty of
## MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
## GNU General Public License for more details.
##
## You should have received a copy of the GNU General Public License
## along with this program; if not, write to the Free Software
## Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
#####################################################################################

# F5 Config backup 2.1  
# Version 2.1.1 -
# 	Added Name/IP format to device list w/ comment and empty line exclusion
# Version 2.1.1.5 -
# 	Bug fix for new list format and 
# Version 2.1.2 -
#	Separate config item for log & UCS archives
#	Separate directory for device archive
#	Fixed time localization	
# 	Fixed bug for log directory location
# Version 2.1.2.5 - 
#	fixed open file arguments  
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
my $DIR = $config->{_}->{BASE_DIRECTORY};
my $ARCHIVE_DIR = $config->{_}->{ARCHIVE_DIRECTORY};
my $UCS_ARCHIVE_SIZE = $config->{_}->{UCS_ARCHIVE_SIZE};
my $LOG_ARCHIVE_SIZE = $config->{_}->{LOG_ARCHIVE_SIZE};
my $DEVICE_LIST = $config->{_}->{DEVICE_LIST};
my $SSH_KEY = $config->{_}->{SSH_KEY};
my $ERROR = 0;

# Set date
my $DATE = DateTime->now(time_zone=>'local')->ymd("-");

########################## DEFINE SUBS #####################################

############################################################################
# hms_time - Get local time hh:mm:ss at that second 
############################################################################
sub hms_time {
	DateTime->now(time_zone=>'local')->hms;
};

############################################################################
# ParseDeviceList - Parse name & IP from device list and output hash format
# my %DEVICE_HASH = ParseDeviceList <DEVICE_LIST>;
############################################################################
sub ParseDeviceList {
	my @DEVICES= @_;
	# Exclude lines that are comments and blank
	@DEVICES = grep (!(/#/|/^\n/),@DEVICES);
	chomp(@DEVICES);
	my @DEVICE_ARRAY;
	foreach (@DEVICES) {
		if ($_ =~ "=") {
			my ($name,$ip) = split("=",$_);
			push (@DEVICE_ARRAY,$name);
			push (@DEVICE_ARRAY,$ip);
		} else {
			push (@DEVICE_ARRAY,$_);
			push (@DEVICE_ARRAY,$_);
		};
	};
	return @DEVICE_ARRAY;
}
############################################################################
# CreateDeviceDIR - Make a new device folder if does not exist
# CreateDeviceDIR [DEVICE];
############################################################################
sub CreateDeviceDIR {
	my ($DEVICE) = (@_);
	unless (opendir(DIRECTORY,"$ARCHIVE_DIR/$DEVICE")) {
		print LOG "Device directory $ARCHIVE_DIR/$DEVICE does not exist. Creating folder $DEVICE at ",hms_time,".\n";
		my $NEW_DIR = "$ARCHIVE_DIR/$DEVICE";
		unless (mkdir $NEW_DIR,0755) {
			print LOG "Error: Cannot create folder $DEVICE - $!.\n" ;
			$ERROR++;
			next;
		};
	};
};

############################################################################
# GetHash - Get the config hash from device
# my $NEW_HASH = GetHash([device],[ssh_handle]);
############################################################################
sub GetHash {
	my ($DEVICE,$SSH) = (@_);
	my ($HASH,$errput) = $SSH->capture2("tmsh list | sha1sum | cut -d ' ' -f1");
	chomp ($HASH,$errput);
	if (length($errput) != 0) { 
		print LOG "Error: Get hash failed for $DEVICE: $errput.\n" ;
		$ERROR++;
		next ;
	};
	print LOG "Hash for $DEVICE is - $HASH.\n";
	return $HASH
};

############################################################################
# CreateUCS - Creates UCS file on device
# CreateUCS([device],[ssh_handle]);
############################################################################
sub CreateUCS {
	my ($DEVICE,$SSH) = (@_);
	print LOG "Hashes do not match for $DEVICE at ",hms_time,". Downloading backup file.\n";
	my ($output,$errput) = $SSH->capture2("tmsh save sys ucs /shared/tmp/backup.ucs");
	chomp ($output,$errput);
	print LOG "Making device create UCS - $errput.\n" ;
};

############################################################################
# GetUCS - Download UCS file from device
# GetUCS([device],[ssh_handle]);
############################################################################
sub GetUCS {
	my ($DEVICE,$SSH) = (@_);
	print LOG "Downloading UCS file at ",hms_time,".\n";
	my $UCS_FILE = "$ARCHIVE_DIR/$DEVICE/$DATE-$DEVICE-backup.ucs";
	$SSH->scp_get({},'/shared/tmp/backup.ucs',$UCS_FILE);
	if (length($SSH->error) > 1) {
		print LOG "Error: UCS file download failed - ",$SSH->error, ".\n" ;
		$ERROR++;
		next;
	};
};

############################################################################
# WriteHASH - write new hash to file
# WriteHash([device],[hash]);
############################################################################
sub WriteHash {
	my ($DEVICE,$HASH) = (@_);
	print LOG "Overwriting old hash file at ",hms_time,".\n";
	if (open HASH,"+>","$ARCHIVE_DIR/$DEVICE/backup-hash") {
		print HASH $HASH ;
		close HASH;
	} else {
		print LOG "Error: Could not write new hash file for $DEVICE - $! .\n" ;
		$ERROR++;
	};
};

############################################################################
# CleanArchive - Delete old archive files
# CleanArchive [DEVICE_LIST];
############################################################################
sub CleanArchive {
	my @DEVICES = @_;
	foreach (@DEVICES) {
		my $DEVICE = $_;
		if (opendir(DIRECTORY,"$ARCHIVE_DIR/$DEVICE")) { 
			my @DIRECTORY = readdir(DIRECTORY);
			@DIRECTORY = reverse sort grep(/backup.ucs/,@DIRECTORY); 
			foreach (@DIRECTORY[$UCS_ARCHIVE_SIZE..($#DIRECTORY)]) {
				print LOG "Deleting backup file at ",hms_time,": $DEVICE/$_.\n" ;
				unlink ("$ARCHIVE_DIR/$DEVICE/$_") or print LOG "Error: Cannot delete $ARCHIVE_DIR/$DEVICE/$_ - $!.\n" and $ERROR++;
			};
			closedir DIRECTORY;
		} else {
			print LOG "Error: Can not open directory $ARCHIVE_DIR/$DEVICE/ - $!.\n" ;
			$ERROR++ ;
			next;
		};
	};
};

############################################################################
# CleanLogs - Delete old log files
############################################################################
sub CleanLogs {
	if (opendir(DIRECTORY,"$DIR/log/")) {
		my @DIRECTORY = readdir(DIRECTORY);
		@DIRECTORY = reverse sort grep(/backup.log/,@DIRECTORY);
		foreach (@DIRECTORY[$LOG_ARCHIVE_SIZE..($#DIRECTORY)]) {
			print LOG "Deleting log file at ",hms_time,": $_.\n" ;
			unlink("$DIR/log/$_") or print LOG "Error: Cannot delete $DIR/log/$_ - $!.\n" and $ERROR++;
		};
		closedir DIRECTORY;
	} else {
		print LOG "Error: Can not open log directory: $!.\n" ;
		$ERROR++;
	};
};

############################################################################################
# *************************************** MAIN PROGRAM *************************************
############################################################################################

# Open files/arrays for logging
open LOG,"+>","$DIR/log/$DATE-backup.log";
print LOG "Starting configuration backup on $DATE at ",hms_time,".\n";

# Open device list, create device list hash
open DEVICE_LIST,"<","$DIR/$DEVICE_LIST" or die "Cannot open device list - $!.\n";
my %DEVICE_HASH = ParseDeviceList <DEVICE_LIST>;


# Loop though device list
foreach (keys %DEVICE_HASH) {
	print LOG "\nConnecting to $_ at ",hms_time,".\n";

	# Create device folder is it does not exist
	CreateDeviceDIR $_;

	# Open SSH connection to host
	my $ssh = Net::OpenSSH->new($DEVICE_HASH{$_},
		user=>'root',
		key_path=>$SSH_KEY,
		master_stderr_discard => 1,
		timeout => 5,
	);
	if (length($ssh->error) > 1) {
		print LOG "Error at ",hms_time,": Can't connect to $_ - ",$ssh->error, ".\n" ;
		$ERROR++ ;
		next;
	};

	# get hash from device and write to VAR
	my $NEW_HASH = GetHash($_,$ssh);

	# Check for new hash and break if it does not exist
	if (! defined($NEW_HASH) || length $NEW_HASH != 40) {
		print LOG "Get HASH failed for $_ at ",hms_time,". Skipping to next device.\n";
		$ERROR++;
		next;
	};

	# Check for old hash. if not present the set OLD_HASH to null
	my $OLD_HASH = "";
	if (open DEVICE_HASH,"<","$ARCHIVE_DIR/$_/backup-hash") {
		$OLD_HASH = <DEVICE_HASH> ;
		close DEVICE_HASH;
	};

	# Compare old hash to new hash
	if ($OLD_HASH eq $NEW_HASH) {
		print LOG "Hashes match for $_ at ",hms_time,". Configuration unchanged. Skipping download.\n";
	} else {
		# Make device create UCS file, Download UCS file, Disconnect SSH session, Write new hash to file
		CreateUCS($_,$ssh);
		GetUCS($_,$ssh);
		undef $ssh;
		WriteHash($_,$NEW_HASH);
	};
};

#  Add deletion note to log file
print LOG "\nDeleting old files:\n";

# Keep only the number of UCS files specified by UCS_ARCHIVE_SIZE and write deletion to log
CleanArchive keys %DEVICE_HASH;

# Keep only the number of log files specified by LOG_ARCHIVE_SIZE and write deletion to log
CleanLogs;

# Check number of errors. Print line if > 0
print LOG "\nThere is $ERROR error(s).\n" if ($ERROR > 0);

# All done
print LOG "\nBackup job completed.\n";
close LOG;
