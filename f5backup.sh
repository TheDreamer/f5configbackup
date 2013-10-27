#!/bin/bash

# Set date
DATE=`date  +%Y%m%d`

#Set logging
LOG="/var/f5backup/log/$DATE-backup.log"
ERRORLOG="/var/f5backup/log/$DATE-error.log"

# Look for CIFS mount of device directory
MOUNT=$(mount | grep /var/f5backup/devices 2> /dev/null | wc -l)

# If CIFS directory not mounted write to error log and kill job
if [ $MOUNT != 1 ] ; then
	echo "Backup directory not available" >> $ERRORLOG ;
	exit 1
fi

# Create or clear backup log and error files
echo > $LOG
echo > $ERRORLOG

# Connect to all F5 devices in list and copy
# For every device in list echo string to log file,
# Download config hash file and compare
# scp each backup file recursively device to dir with date in format and write stderr & stdout to logfile
# overwrite old hash file with new 
for i in `cat /var/f5backup/list`
 do
	echo -e "\r\nConnecting to $i at `date`" >> $LOG ;
	
	# Download new hash file
	scp -r -o LogLevel=error root@$i:/var/tmp/$DATE-backup-hash /var/f5backup/devices/$i/backup-hash-new >> $LOG  2>> $ERRORLOG;	

	# Check for new hash file and break if it does not exist
	NEW_HASH_PRESENT=$(find /var/f5backup/devices/$i/backup-hash-new 2> /dev/null | wc -l)
	if [ $NEW_HASH_PRESENT != 1 ] ; then
		echo -e "New hash file not downloaded for $i. Skipping to next device." >> $LOG ;
		echo -e "New hash file not downloaded for $i." >> $ERRORLOG ;
		continue 
	fi
	
	# Check for old hash. if not present the set OLD_HASH to null
	OLD_HASH_PRESENT=$(find /var/f5backup/devices/$i/backup-hash 2> /dev/null | wc -l)
	if [ $OLD_HASH_PRESENT == 1 ] ; then
		OLD_HASH=$(cat /var/f5backup/devices/$i/backup-hash)
	else
		OLD_HASH="null"	
	fi
	
	# Set  new hash var
	NEW_HASH=$(cat /var/f5backup/devices/$i/backup-hash-new)
	
	# Compare old hash to new hash
	if [ $OLD_HASH == $NEW_HASH ] ; then
		echo -e "Hashes match for $i. Configuration unchanged. Skipping download." >> $LOG ; 
	else
		echo -e "Hashes do not match. Downloading backup file." >> $LOG ; 	

		# Download backup file
		scp -r -o LogLevel=error root@$i:/var/tmp/backup.ucs /var/f5backup/devices/$i/$DATE-$i-backup.ucs >> $LOG  2>> $ERRORLOG;
	fi
	
	# Make new hash file current
	echo -e "Overwriting old hash file" >> $LOG ;
	mv -vf /var/f5backup/devices/$i/backup-hash-new /var/f5backup/devices/$i/backup-hash >> $LOG  2>> $ERRORLOG

done

#  Add deletion note to log file
echo -e "\r\nThe following files have been deleted:" >> $LOG
	
# Keep only the the most current 15 files and write deletion to log
# For every sub dir, cd into, ls the dir, use sed to remove the 15 most recent from ls,
# xarg the rest to rm in verbose and write stderr & stdout to logfile
for i in `ls -1 /var/f5backup/devices/` ; do 
   cd /var/f5backup/devices/$i
   ls -1r | sed -n '1,15d;p' | xargs rm -vf >> $LOG  2>> $ERRORLOG; 
done

# Delete old log files
# ls the log dir, grep only backup log files, sed to remove most recent 15 from ls,
# send output to xargs to rm with verbose out written to log file
cd /var/f5backup/log/ 
ls -1r | grep backup.log | sed -n '1,15d;p' | xargs rm -vf >> $LOG  2>> $ERRORLOG

# Check error file for lines
# If equal to 0 then delete error file
# otherwise put notice in log file
ERROR=`wc -L /var/f5backup/log/$DATE-error.log | cut -d " " -f 1`

if [ $ERROR == 0 ] ; then
	rm -f $ERRORLOG
	echo -e "\r\nErrors:\r\n No errors present" >> $LOG
 else 
	echo -e "\r\nErrors:\r\n Errors are present. Please check file $ERRORLOG for details" >> $LOG
fi

# Add completion note to end
echo -e "\r\nBackup job completed." >> $LOG


