#!/bin/bash

# F5 backup installation script
echo -e "Starting installation of F5 backup program.\n"

# Check perl version
P_VER=`perl -e 'print $^V'`
echo -e "Your version of perl is $P_VER.\n"

# ------------ Check for perl modules --------------------------------------
echo "Checking for perl for required modules -"

# Put perl modules in this list
MODULES="
DateTime
Net::OpenSSH
Config::Tiny
DBI
IO::Pty
"

# Loop through module list and break if module not present
ERROR=""
NOT_INSTALLED=0

for i in $MODULES ; do
	RESULT=`perl -M$i -e 'print "yes";' 2> /dev/null`
	RESULT+="-one"     # prevents  unary operator expected error
	if [ $RESULT == "yes-one" ]; then
		echo "  $i is installed."
	else
		ERROR+="$i is not installed. Please install before continuing.\n"
		(( NOT_INSTALLED++ ))
	fi
done

# Exit if module is not installed
if [ $NOT_INSTALLED != 0 ] ; then 
	echo -e "\n\n$ERROR"
	exit
fi
echo "Perl modules are good!"

# ------------ Ask user for base directory ------------------------------------
echo -e "\nWhat directory would you like to install this program in ?"
echo "Press enter for default. [/var/f5backup]"
read -e -r BASE_DIR

# If entry is blank set BASE_DIR to default
if [ -z $BASE_DIR ]; then
	BASE_DIR="/var/f5backup"
fi 
echo -e "\nBase directory is $BASE_DIR."
echo -e "Checking if $BASE_DIR exists...\n"

# Check if base directory exists
if [ -d "$BASE_DIR" ]; then 
	echo "$BASE_DIR does exist."
	echo "This will overwrite any files already in the directory. Do you want to continue ?"; 
	# Loop until user provides correct answer
	while : ; do
		read -p "Type yes or no: " YES_NO
		if [ $YES_NO == "yes" ]; then
			echo -e "\nOverwriting old files."
			break
		elif [ $YES_NO == "no" ]; then
			echo -e "\nPlease choose another directory."
			exit
		fi
	done
else
	echo "$BASE_DIR does not exist. Creating directory."; 
	mkdir -p $BASE_DIR
	if [ $? = 1 ]; then
		echo -e "\nCould not create directory. Please solve issue and try again."
		exit
	fi
fi

# ---------------- Ask user for archive directory -----------------------------------------
echo -e "\nWhat directory would you like to be the device archive ?"
echo "Press enter for default. [$BASE_DIR/devices]"
read -e -r ARCHIVE_DIRECTORY

# If entry is blank set ARCHIVE_DIRECTORY to default
if [ -z $ARCHIVE_DIRECTORY ]; then
	ARCHIVE_DIRECTORY="$BASE_DIR/devices"
fi 
echo -e "\nArchive directory is $ARCHIVE_DIRECTORY."
echo -e "Checking if $ARCHIVE_DIRECTORY exists...\n"

# Check if directory exists
if [ -d "$ARCHIVE_DIRECTORY" ]; then 
	echo "$ARCHIVE_DIRECTORY already exists."
else
	echo "$ARCHIVE_DIRECTORY does not exist. Creating directory."; 
	mkdir $ARCHIVE_DIRECTORY
	if [ $? = 1 ]; then
		echo -e "\nCould not create directory. Please solve issue and try again."
		exit
	fi
fi

# ---------------- Ask user for device list -----------------------------------------

echo -e "\nWhat file would would you like to use for the device list ?"
echo "Put file name only. List will be created in install directory location."
echo "Press enter for default. [list.txt]"
read -e -r DEVICE_LIST

# If entry is blank set DEVICE_LIST to default
if [ -z $DEVICE_LIST ]; then
	DEVICE_LIST="list.txt"
fi 
echo -e "\nCreating device list $BASE_DIR/$DEVICE_LIST."
echo -e "# Put your list of devices in this file
# One line per device
#
# Format -
# For devices that can be reached through DNS use name only -
# Example - BIPIP_LTM1
#
# For devices that need an IP use NAME=IP -
# Example - BIGIP1=192.168.1.245
#
F5_BIGIP1=192.168.1.245
F5_BIGIP2=192.168.1.246
BIGIP3" > $BASE_DIR/$DEVICE_LIST

if [ $? = 1 ]; then
	echo -e "\nCould not create device list. Please solve issue and try again."
	exit
fi

# ---------------- Ask user about config file -----------------------------------------
# Username 
echo -e "\nWhat username would you like to use for device login ? "
echo "The user needs to be an administrator on the F5 so that it can create UCS files."
echo "Press enter for default. [admin]"
read USERNAME

# If entry is blank set USERNAME to default
if [ -z $USERNAME ]; then
	USERNAME="admin"
fi 

# Password file
echo -e "\nWhat password file would you like to use for device login ?"
echo "Recommend file be in users home directory with proper file ownership and attributes of 0400 (readable by user only)"
read -e -r PASS_FILE

# Archive size
echo -e "\nHow many backup files do you want to keep for each device ?"
echo "Press enter for default. [15]"
read  UCS_ARCHIVE_SIZE

# If entry is blank set UCS_ARCHIVE_SIZE to default
if [ -z $UCS_ARCHIVE_SIZE ]; then
	UCS_ARCHIVE_SIZE=15
fi

# Log archive size
echo -e "\nHow many backup files do you want to keep for each device ?"
echo "Press enter for default. [30]"
read  LOG_ARCHIVE_SIZE

# If entry is blank set LOG_ARCHIVE_SIZE to default
if [ -z $LOG_ARCHIVE_SIZE ]; then
	LOG_ARCHIVE_SIZE=30
fi


# Create DB
echo -e "\nCreating DB file $BASE_DIR/main.db"

echo > $BASE_DIR/main.db
echo -e "PRAGMA foreign_keys=OFF;
BEGIN TRANSACTION;
CREATE TABLE DEVICES (
ID INTEGER PRIMARY KEY AUTOINCREMENT,
NAME TEXT NOT NULL,
IP CHAR(15),
CID_TIME INT,
DATE_ADDED INT,
LAST_DATE INT
);
CREATE TABLE JOBS (
ID INTEGER PRIMARY KEY AUTOINCREMENT,
DATE DATE NOT NULL,
TIME INT NOT NULL,
ERRORS INT,
COMPLETE BOOLEAN,
DEVICE_TOTAL INT,
DEVICE_COMPLETE INT,
DEVICE_W_ERRORS TEXT
);
COMMIT;" | sqlite3 $BASE_DIR/main.db


# Create config file
echo -e "\nCreating config file $BASE_DIR/f5backup.conf with the options you selected."

echo -e "# base directory for F5 backup
BASE_DIRECTORY=$BASE_DIR

# Archive directory for UCS files
ARCHIVE_DIRECTORY=$ARCHIVE_DIRECTORY

# List of devices, one per line
# List needs to be in BASE_DIRECTORY location
DEVICE_LIST=$DEVICE_LIST

# Username to log into devices
USERNAME=$USERNAME

# Location of password file, file should only contain password.
# The user needs to be an administrator on the F5 so that it can create UCS files.
# Recommend file be in users home directory with proper file ownership 
# 	and attributes of 0400 (readable by user only)
PASS_FILE=$PASS_FILE

# Location of DB file
# DB file needs to be in BASE_DIRECTORY location
DB_FILE=main.db

# Number of UCS files to retain per devices
UCS_ARCHIVE_SIZE=$UCS_ARCHIVE_SIZE

# Number of log files to maintain
LOG_ARCHIVE_SIZE=$LOG_ARCHIVE_SIZE" > $BASE_DIR/f5backup.conf

echo -e "\nCopying f5backup.pl file to $BASE_DIR/"
cp ./f5backup.pl $BASE_DIR/f5backup.pl
chmod 0755 $BASE_DIR/f5backup.pl

echo -e "\nCopying testssh.pl file to $BASE_DIR/"
cp ./testssh.pl $BASE_DIR/testssh.pl
chmod 0755 $BASE_DIR/testssh.pl


echo -e "\nCreating log directory $BASE_DIR/log"
mkdir $BASE_DIR/log

echo -e "\nDone installing program. Check $BASE_DIR for file contents."	

