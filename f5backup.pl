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

# F5 Config backup 2.4 - 
#	Added SQLite DB for device records
#	Added password based login and removed SSH key login
#	Added TMSH detection to allow non root login
#	Other misc fixes
# Version 2.4.1 -
#	Revamped shell detection
#	Fixed device dir creation bug
#	IP is updated in DB if a device IP changes in the device file
#
#

use strict;
use warnings;
use DateTime;
use Net::OpenSSH;
use Config::Tiny;
use DBI;

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
my $USERNAME = $config->{_}->{USERNAME};
my $PASS_FILE = $config->{_}->{PASS_FILE};
my $DB_FILE = $config->{_}->{DB_FILE};

# Declare VARs for subs
my $ERROR = 0;
my %DEVICE_HASH;
my $START = 0;
my $dbh;

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
# OrphansDBDelete - Remove orphaned devices from DB
############################################################################
sub OrphansDBDelete {
	# $START var is to keep sub from running sql query before start of main
	if ($START) {
		my @names_temp1 = @{$dbh->selectcol_arrayref("SELECT NAME FROM DEVICES")};
		my @devices_temp1 = (keys %DEVICE_HASH);
		# Loop through DB devices list, match against file device list
		# Remove any devices from DB that are not in file
		foreach (@names_temp1) {
			if ( "@devices_temp1" !~ "$_") {
				print LOG "Device $_ no longer in device list. Removing from DB at ",hms_time,".\n";
				unless ( $dbh->do("DELETE FROM DEVICES WHERE NAME = '$_'") ) {
					print LOG "Error at ",hms_time,": Can't delete $_ from DB\n" ;
				};
			};
		};
	};
};

############################################################################
# NewDeviceDB - Add/Update new devices in DB
############################################################################
sub NewDeviceDB {
	if ($START) {
		foreach (keys %DEVICE_HASH) {
			if (! join '',$dbh->selectrow_array("SELECT count('NAME') FROM DEVICES WHERE NAME = '$_'")) {
				# If device is not in DB then add it
				my $time = time;
				print LOG "Device $_ is not in database. Adding to DB at ",hms_time,".\n";
				unless ( $dbh->do("INSERT INTO DEVICES ('NAME','IP','CID_TIME','DATE_ADDED') 
									VALUES ('$_','$DEVICE_HASH{$_}','0',$time)") ) {
					print LOG "Error at ",hms_time,": Can't INSERT $_ to DB\n" ;
				};
			} else {
				# If IP has changed in file then update DB
				my $IP = join '',$dbh->selectrow_array("SELECT IP FROM DEVICES WHERE NAME = '$_'");
				if ($IP ne $DEVICE_HASH{$_} ) {
					print LOG "IP has changed for $_. Old IP is $IP. Updating to new IP of $DEVICE_HASH{$_}\n";
					unless ( $dbh->do("UPDATE DEVICES SET IP = '$DEVICE_HASH{$_}' WHERE NAME = '$_'") ) {
						print LOG "Error at ",hms_time,": Can't INSERT $_ to DB\n" ;
					};
				};
			};
		};
	};
};
############################################################################
# DetectShell - find out which shell the device is in
# my $shell = DetectShell $_,$ssh 
############################################################################
sub DetectShell {
	my ($DEVICE,$SSH) = (@_);
	my ($output,$discard) = $SSH->capture2("echo hello");
	chomp $output;
	# if output is hello the shell is bash
	if ($output eq "hello")  {
		print LOG "Shell for $DEVICE is bash.\n";
		return "bash";
	};
	($output,$discard) = $SSH->capture2("show sys version");
	chomp $output;
	# If output contains Sys::Version then shell is TMSH
	if ($output =~ "Sys::Version")  {
		print LOG "Shell for $DEVICE is tmsh.\n";
		return "tmsh";
	};
};

############################################################################
# CreateDeviceDIR - Make a new device folder if does not exist
# CreateDeviceDIR [DEVICE];
############################################################################
sub CreateDeviceDIR {
	my ($DEVICE) = @_;
	print LOG "device - $DEVICE, dev - $DEVICE\n";
	# If you cant open the dir then it does not exist
	unless (opendir(DIRECTORY,"$ARCHIVE_DIR/$DEVICE")) {
		print LOG "Device directory $ARCHIVE_DIR/$DEVICE does not exist. Creating folder $DEVICE at ",hms_time,".\n";
		my $NEW_DIR = "$ARCHIVE_DIR/$DEVICE";
		unless (mkdir $NEW_DIR,0755) {
			print LOG "Error: Cannot create folder $DEVICE - $!.\n" ;
			$ERROR++;
			next;
		};
	} else {
		closedir DIRECTORY;
	};
};

############################################################################
# GetCIDtime & ParseDBkey - Get the CID time from device
# my $new_cid_time = GetCIDtime [device],[ssh_handle],[shell];
############################################################################
sub ParseDBkey {
	my $text = join '',@_;
	# get value between quotes
	$text = join '',($text =~ /"(.+?)"/);
	return $text;
};
sub GetCIDtime {
	my ($DEVICE,$SSH,$SHELL) = (@_);
	my $DB_KEY;
	if ($SHELL eq "bash")  {
		$DB_KEY = ParseDBkey $SSH->capture("tmsh list sys db configsync.localconfigtime");
	} elsif ($SHELL eq "tmsh") {
		$DB_KEY = ParseDBkey $SSH->capture("list sys db configsync.localconfigtime");
	};
	print LOG "CID time for $DEVICE is - $DB_KEY.\n";
	return $DB_KEY
};

############################################################################
# CreateUCS - Creates UCS file on device
# CreateUCS [device],[ssh_handle],[shell];
############################################################################
sub CreateUCS {
	my ($DEVICE,$SSH,$SHELL) = (@_);
	print LOG "CID times do not match for $DEVICE at ",hms_time,". Downloading backup file.\n";
	my ($output,$errput);
	if ($SHELL eq "bash")  {
		($output,$errput) = $SSH->capture2("tmsh save sys ucs /shared/tmp/backup.ucs");
	} elsif ($SHELL eq "tmsh") {
		($output,$errput) = $SSH->capture2("save sys ucs /shared/tmp/backup.ucs");
	};
	chomp $errput;
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
# CleanArchive - Delete old archive files
# CleanArchive [DEVICE_LIST];
############################################################################
sub CleanArchive {
	my @DEVICES = @_;
	# Go into each device directory
	foreach (@DEVICES) {
		my $DEVICE = $_;
		if (opendir(DIRECTORY,"$ARCHIVE_DIR/$DEVICE")) { 
			# Delete files in array with index higher than archive size
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
		# Delete files in array with index higher than archive size
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

$START = 1;

# Open files/arrays for logging
open LOG,"+>","$DIR/log/$DATE-backup.log";
print LOG "Starting configuration backup on $DATE at ",hms_time,".\n";

# Open device list, create device list hash
print LOG "Opening device list file /$DIR/$DEVICE_LIST at ",hms_time,".\n";
open DEVICE_LIST,"<","$DIR/$DEVICE_LIST" or die "Cannot open device list - $!.\n";
%DEVICE_HASH = ParseDeviceList <DEVICE_LIST>;
close DEVICE_LIST;

# Get password from file
print LOG "Opening password file $PASS_FILE at ",hms_time,".\n";
open PSWD,"<","$PASS_FILE" or die "Error at ",hms_time,": Can't open password file - $!\n" ;
my $PASSWORD = <PSWD>;
chomp $PASSWORD;
close PSWD;

# Connect to DB
print LOG "Opening DB file $DIR/$DB_FILE at ",hms_time,".\n";
$dbh = DBI->connect(          
    "dbi:SQLite:dbname=$DIR/$DB_FILE", 
    "",
    "",
    { RaiseError => 1}
) or die $DBI::errstr;

# Remove orphaned devices from DB
OrphansDBDelete;

# Add new devices to DB
NewDeviceDB;
undef %DEVICE_HASH;

# Get device names from DB
my @DEVICES_NAMES = @{$dbh->selectcol_arrayref("SELECT NAME FROM DEVICES")};

# Loop though device list
foreach (@DEVICES_NAMES) {
	print LOG "\nConnecting to $_ at ",hms_time,".\n";

	# Create device folder is it does not exist
	CreateDeviceDIR $_;
	
	# Get IP from DB or set to hostname if NULL
	my $IP = join '',$dbh->selectrow_array("SELECT IP FROM DEVICES WHERE NAME = '$_'");
	$IP = $_ if ($IP eq 'NULL');
	
	# Open SSH connection to host
	my $ssh = Net::OpenSSH->new($IP,
		user=>$USERNAME,
		passwd =>$PASSWORD,
		master_stderr_discard => 1,
		timeout => 5,
	);
	if (length($ssh->error) > 1) {
		print LOG "Error at ",hms_time,": Can't connect to $_ - ",$ssh->error, ".\n" ;
		$ERROR++ ;
		next;
	};

	# Detect device shell
	my $shell = DetectShell $_,$ssh;
	
	# get cid time from device and write to VAR
	my $new_cid_time = GetCIDtime $_,$ssh,$shell;

	# Check for new cid time or next if it does not exist
	if (! defined $new_cid_time) {
		print LOG "Get CID time failed for $_ at ",hms_time,". Skipping to next device.\n";
		$ERROR++;
		next;
	};

	# Get old CID time from DB
	my $old_cid_time = join '',$dbh->selectrow_array("SELECT CID_TIME FROM DEVICES WHERE NAME = '$_'"),"\n";
	chomp $old_cid_time;
	
	# Compare old cid time to new cid time
	if ($old_cid_time eq $new_cid_time) {
		print LOG "CID times match for $_ at ",hms_time,". Configuration unchanged. Skipping download.\n";
	} else {
		# Make device create UCS file, Download UCS file, Disconnect SSH session, Write new cid time to DB
		my $time = time;
		CreateUCS $_,$ssh,$shell;
		GetUCS($_,$ssh);
		undef $ssh;
		unless ( $dbh->do("UPDATE DEVICES SET CID_TIME = '$new_cid_time', 
			LAST_DATE = $time WHERE NAME = '$_'") ) {
			print LOG "Error at ",hms_time,": Can't INSERT CID time $_ into DB\n" ;
		};
	};
};

#  Add deletion note to log file
print LOG "\nDeleting old files:\n";

# Keep only the number of UCS files specified by UCS_ARCHIVE_SIZE and write deletion to log
CleanArchive @DEVICES_NAMES;

# Keep only the number of log files specified by LOG_ARCHIVE_SIZE and write deletion to log
CleanLogs;

# Check number of errors. Print line if > 0
print LOG "\nThere is $ERROR error(s).\n" if ($ERROR > 0);

# All done
print LOG "\nBackup job completed.\n";
close LOG;
