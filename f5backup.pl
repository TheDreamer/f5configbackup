#!/usr/bin/perl
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
my $JOB_ID;
my @DEVICE_W_ERRORS;
my $DEVICE_COMPLETE = 0;

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
# IncERROR - Increment the error counter in the DB
# IncERROR <optional device name>; or IncERROR 0;
############################################################################
sub IncERROR {
	if ($START) {
		my ($DEVICE) = @_;
		$ERROR++;
		push @DEVICE_W_ERRORS,"$DEVICE" if (length $DEVICE);
		unless ( $dbh->do("UPDATE JOBS SET ERRORS = $ERROR, 
					DEVICE_W_ERRORS = '@DEVICE_W_ERRORS' WHERE ID = $JOB_ID") ) {
			print LOG "Error at ",hms_time,": Can't write ERRORS to DB .\n" ;
		};		
	};
};

############################################################################
# DBJobID - Creates/overwrites job DB entry and returns row ID
# my $JOB_ID = LogJobDB;
############################################################################
sub DBJobID {
	if ($START) {
		my $START_TIME = time;
		print LOG "Adding record to JOB DB table at ",hms_time,".\n";
		# Does job w/ same date exsit ?
		my $ROW = join '',$dbh->selectrow_array("SELECT ID FROM JOBS WHERE DATE = '$DATE'");
		if ($ROW) {
			# Yes - Overwrite row
			print LOG "Job exists for this date $DATE. Overwriting old DB record at ",hms_time,"\n";
			unless ($dbh->do("UPDATE JOBS SET TIME = $START_TIME, COMPLETE = 0, 
									ERRORS = 0, DEVICE_TOTAL = 0,	DEVICE_COMPLETE = 0, 
									DEVICE_W_ERRORS = '0' WHERE DATE = '$DATE'")) {
				print LOG "Error at ",hms_time,": Can't create new row in JOBS table.\n" ;
				IncERROR 0;
			};
		} else {
			# No - create new row
			unless ( $dbh->do("INSERT INTO JOBS ('DATE','TIME','ERRORS',
									'COMPLETE','DEVICE_TOTAL','DEVICE_COMPLETE','DEVICE_W_ERRORS') 
									 VALUES ('$DATE',$START_TIME,0,0,0,0,0)") ) {
				print LOG "Error at ",hms_time,": Can't create new row in JOBS table.\n" ;
				IncERROR 0;
			};
			$ROW = $dbh->last_insert_id("","","JOBS","");
		};
		return $ROW;
	};
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
		foreach my $name1 (@names_temp1) {
		unless ( grep {$_ eq "$name1"} @devices_temp1) {
				print LOG "Device $name1 no longer in device list. Removing from DB at ",hms_time,".\n";
				unless ( $dbh->do("DELETE FROM DEVICES WHERE NAME = ?",undef,$name1) ) {
					print LOG "Error at ",hms_time,": Can't delete $name1 from DB\n" ;
					IncERROR 0;
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
		my $sth = $dbh->prepare("SELECT count('NAME') FROM DEVICES WHERE NAME = ?");
		foreach (keys %DEVICE_HASH) {
			$sth->execute($_);
			if (! $sth->fetchrow_array() ) {
				# If device is not in DB then add it
				my $time = time;
				print LOG "Device $_ is not in database. Adding to DB at ",hms_time,".\n";
				unless ( $dbh->do("INSERT INTO DEVICES ('NAME','IP','CID_TIME','DATE_ADDED') 
									VALUES (?,?,'0',$time)", undef,$_,$DEVICE_HASH{$_}) ) {
					print LOG "Error at ",hms_time,": Can't INSERT $_ to DB\n" ;
					IncERROR 0;
				};
			} else {
				# If IP has changed in file then update DB
				my $IP = join '',$dbh->selectrow_array("SELECT IP FROM DEVICES WHERE NAME = ?", undef,$_);
				if ($IP ne $DEVICE_HASH{$_} ) {
					print LOG "IP has changed for $_. Old IP is $IP. Updating to new IP of $DEVICE_HASH{$_}\n";
					unless ( $dbh->do("UPDATE DEVICES SET IP = ? WHERE NAME = ?",undef,$DEVICE_HASH{$_},$_) ) {
						print LOG "Error at ",hms_time,": Can't INSERT $_ to DB\n" ;
						IncERROR 0;
					};
				};
			};
		};
		$sth->finish();
	};
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
		print LOG "Error at ",hms_time,": Can't detect shell for $DEVICE - $output - $errput - $errput2.\n" ;
		IncERROR $DEVICE;	
		next;
	};
	return $SHELL
};

############################################################################
# CreateDeviceDIR - Make a new device folder if does not exist
# CreateDeviceDIR [DEVICE];
############################################################################
sub CreateDeviceDIR {
	my ($DEVICE) = @_;
	# If you cant open the dir then it does not exist
	unless (opendir(DIRECTORY,"$ARCHIVE_DIR/$DEVICE")) {
		print LOG "Device directory $ARCHIVE_DIR/$DEVICE does not exist. Creating folder $DEVICE at ",hms_time,".\n";
		my $NEW_DIR = "$ARCHIVE_DIR/$DEVICE";
		unless (mkdir $NEW_DIR,0755) {
			print LOG "Error: Cannot create folder $DEVICE - $!.\n" ;
			IncERROR $DEVICE;
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
	my ($DEVICE,$SSH,$SHELL) = @_;
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
		IncERROR $DEVICE;
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
				unlink ("$ARCHIVE_DIR/$DEVICE/$_") 
					or print LOG "Error: Cannot delete $ARCHIVE_DIR/$DEVICE/$_ - $!.\n" 
					and IncERROR 0;
			};
			closedir DIRECTORY;
		} else {
			print LOG "Error: Can not open directory $ARCHIVE_DIR/$DEVICE/ - $!.\n" ;
			IncERROR $DEVICE;
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
			unlink("$DIR/log/$_") or print LOG "Error: Cannot delete $DIR/log/$_ - $!.\n" 
				and IncERROR 0;
		};
		closedir DIRECTORY;
	} else {
		print LOG "Error: Can not open log directory: $!.\n" ;
		IncERROR 0;
	};
};

############################################################################################
# *************************************** MAIN PROGRAM *************************************
############################################################################################

$START = 1;

# Open files/arrays for logging
open LOG,"+>","$DIR/log/$DATE-backup.log";
print LOG "Starting configuration backup on $DATE at ",hms_time,".\n";

# Connect to DB
print LOG "Opening DB file $DIR/$DB_FILE at ",hms_time,".\n";
$dbh = DBI->connect(          
    "dbi:SQLite:dbname=$DIR/$DB_FILE", 
    "",
    "",
    { RaiseError => 1}
) or die $DBI::errstr;

# Log job in DB
$JOB_ID = DBJobID;

# Open device list, create device list hash
print LOG "Opening device list file $DIR/$DEVICE_LIST at ",hms_time,".\n";
open DEVICE_LIST,"<","$DIR/$DEVICE_LIST" or die "Cannot open device list - $!.\n";
%DEVICE_HASH = ParseDeviceList <DEVICE_LIST>;
close DEVICE_LIST;

# Get password from file
print LOG "Opening password file $PASS_FILE at ",hms_time,".\n";
open PSWD,"<","$PASS_FILE" or die "Error at ",hms_time,": Can't open password file - $!\n" ;
my $PASSWORD = <PSWD>;
chomp $PASSWORD;
close PSWD;

# Remove orphaned devices from DB
OrphansDBDelete;

# Add new devices to DB
NewDeviceDB;
undef %DEVICE_HASH;

# Get device names from DB
my @DEVICES_NAMES = @{$dbh->selectcol_arrayref("SELECT NAME FROM DEVICES")};

# Write number of devices to log DB
my $NUM_DEVICES = scalar @DEVICES_NAMES;
print LOG "There are $NUM_DEVICES device(s) to backup.\n";
unless ( $dbh->do("UPDATE JOBS SET DEVICE_TOTAL = $NUM_DEVICES WHERE ID = $JOB_ID")) {
	print LOG "Error at ",hms_time,": Can't INSERT device number to DB\n" ;
	IncERROR 0;
};
					
# Loop though device list
foreach (@DEVICES_NAMES) {
	print LOG "\nConnecting to $_ at ",hms_time,".\n";

	# Create device folder if it does not exist
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
		IncERROR $_;
		next;
	};

	# Detect device shell
	my $shell = DetectShell $_,$ssh;
	print LOG "Shell for $_ is $shell.\n";
	
	# get cid time from device and write to VAR
	my $new_cid_time = GetCIDtime $_,$ssh,$shell;

	# Check for new cid time or next if it does not exist
	unless (length $new_cid_time) {
		print LOG "Get CID time failed for $_ at ",hms_time,". Skipping to next device.\n";
		IncERROR $_;
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
	
	# Update DB with new complete count
	$DEVICE_COMPLETE++;
	unless ( $dbh->do("UPDATE JOBS SET DEVICE_COMPLETE = $DEVICE_COMPLETE WHERE ID = $JOB_ID")) {
		print LOG "Error at ",hms_time,": Can't INSERT device number to DB\n" ;
		IncERROR 0;
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

# Mark job as complete in DB
$dbh->do("UPDATE JOBS SET COMPLETE = 1 WHERE ID = $JOB_ID");

print LOG "\nClosing database.\n";
$dbh->disconnect();

# All done
print LOG "\nBackup job completed at ",hms_time,".\n";
close LOG;

