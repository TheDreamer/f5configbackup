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
#	Added SQLite DB for back records
#	Changed config change detection from hash to CID time
#	Added password based login and removed SSH key login
#	Added TMSH detection to allow non root login
#	Other misc fixes
#
# To do -
# 	figure out a better way to detect TMSH
# 	need new devices to update IP
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
			push (@DEVICE_ARRAY,"NULL");
		};
	};
	return @DEVICE_ARRAY;
};

############################################################################
# OrphansDBDelete - Remove orphaned devices from DB
############################################################################
sub OrphansDBDelete {
	# Check DB for orphaned devices 
	if ($START) {
		my @names_temp1 = @{$dbh->selectcol_arrayref("SELECT NAME FROM DEVICES")};
		my @devices_temp1 = (keys %DEVICE_HASH);
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
				my $time = time;
				print LOG "Device $_ is not in database. Adding to DB at ",hms_time,".\n";
				unless ( $dbh->do("INSERT INTO DEVICES ('NAME','IP','CID_TIME','DATE_ADDED') 
									VALUES ('$_','$DEVICE_HASH{$_}','0',$time)") ) {
					print LOG "Error at ",hms_time,": Can't INSERT $_ to DB\n" ;
				};
			};
		};
	};
};
############################################################################
# CreateDeviceDIR - Make a new device folder if does not exist
# CreateDeviceDIR [DEVICE];
############################################################################
sub CreateDeviceDIR {
	my $DEVICE = @_;
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
# GetCIDtime & ParseDBkey - Get the CID time from device
# my $new_cid_time = GetCIDtime([device],[ssh_handle]);
############################################################################
sub ParseDBkey {
	my $text = join '',@_;
	$text = join '',($text =~ /"(.+?)"/);
	return $text;
};

sub GetCIDtime {
	my ($DEVICE,$SSH) = (@_);
	my ($output,$errput) = $SSH->capture2("echo tmsh");
	chomp ($output,$errput);
	my $DB_KEY;
	if ($output eq "tmsh")  {
		$DB_KEY = ParseDBkey $SSH->capture("tmsh list sys db configsync.localconfigtime");
	} else {
		$DB_KEY = ParseDBkey $SSH->capture("list sys db configsync.localconfigtime");
	};
	print LOG "CID time for $DEVICE is - $DB_KEY.\n";
	return $DB_KEY
};

############################################################################
# CreateUCS - Creates UCS file on device
# CreateUCS([device],[ssh_handle]);
############################################################################
sub CreateUCS {
	my ($DEVICE,$SSH) = (@_);
	print LOG "CID times do not match for $DEVICE at ",hms_time,". Downloading backup file.\n";
	my ($tmsh,$discard) = $SSH->capture2("echo tmsh");
	chomp $tmsh;
	my ($output,$errput);
	if ($tmsh eq "tmsh")  {
		($output,$errput) = $SSH->capture2("tmsh save sys ucs /shared/tmp/backup.ucs");
	} else {
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

$START = 1;

# Open files/arrays for logging
open LOG,"+>","$DIR/log/$DATE-backup.log";
print LOG "Starting configuration backup on $DATE at ",hms_time,".\n";

# Open device list, create device list hash
open DEVICE_LIST,"<","$DIR/$DEVICE_LIST" or die "Cannot open device list - $!.\n";
%DEVICE_HASH = ParseDeviceList <DEVICE_LIST>;
close DEVICE_LIST;

# Get password from file
open PSWD,"<","$PASS_FILE" or die "Error at ",hms_time,": Can't open password file - $!\n" ;
my $PASSWORD = <PSWD>;
chomp $PASSWORD;
close PSWD;

# Connect to DB
$dbh = DBI->connect(          
    "dbi:SQLite:dbname=$DB_FILE", 
    "",
    "",
    { RaiseError => 1}
) or die $DBI::errstr;

# Remove orphaned devices from DB
OrphansDBDelete;

# Add new devices to DB
NewDeviceDB;

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

	# get cid time from device and write to VAR
	my $new_cid_time = GetCIDtime($_,$ssh);

	# Check for new cid time and break if it does not exist
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
		CreateUCS($_,$ssh);
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
CleanArchive keys %DEVICE_HASH;

# Keep only the number of log files specified by LOG_ARCHIVE_SIZE and write deletion to log
CleanLogs;

# Check number of errors. Print line if > 0
print LOG "\nThere is $ERROR error(s).\n" if ($ERROR > 0);

# All done
print LOG "\nBackup job completed.\n";
close LOG;
