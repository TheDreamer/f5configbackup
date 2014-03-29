#!/usr/bin/env python
import os, sys, time
from itertools import chain
import sqlite3 as sq
import bigsuds

# Add fbackup lib folder to sys.path 
sys.path.append('%s/lib' % sys.path[0])
# Import F5 backup libs
import ecommon
from ecommon import *
from econtrol import *

# Open config file
config = ecommon.configfile(sys.argv[1])

# set global vars
base_dir = config['BASE_DIRECTORY']
date = time.strftime("%Y-%m-%d",time.localtime()) 
error = 0
dev_errors = []

# If '-debug' is in args
ecommon.debug = 1 if 'debug' in sys.argv else 0

############################################
# Error function
############################################
def add_error(text,device = ''):
	#Inc error counter
	global error
	error += 1
	# insert new error count into DB
	
	# write error to file
	logwr(text)
	#If device error add to list
	if len(device):
		dev_errors.append(device)
		# Insert new list into DB


#*****************************************************
#    START OF MAIN PR0GRAM
#*****************************************************

# Open/overwrite new log file. Quit if permission denied.
try:
	ecommon.logfile = open('%s/log/%s-backup.log' % (base_dir, date),'w',0)
except:
	e = sys.exc_info()[1]
	print 'Error:', e,'\nCan\'t write to log file. Exiting program!'
	exit()

logwr('Starting backup job on %s at %s'% (date,hmstime()))

# Get password from file
logwr('Retrieving password from file.')
try:
	ps = open( config['PASS_FILE'] , 'r')
	passwd = ps.readline().rstrip()
	ps.close()
	del ps
except:
	e = sys.exc_info()[1]
	add_error('Error: Can\'t open password file - %s \nExiting program!' % e)
	exit()	


# Connect to DB
logwr('\nOpening database file.')
try:
	db = sq.connect(base_dir + '/db/main.db')
except:
	e = sys.exc_info()[1]
	add_error('Error: Can\'t open data base - %s \nExiting program!' % e)
	exit()

dbc = db.cursor()

# Log job in DB

# Get list of devices
dbc.execute("SELECT ID,NAME,IP,CID_TIME FROM DEVICES")
devices = [ {'id' : idn, 'name' : name, 'ip' : ip, 'cid': cid} for idn, name, ip, cid in dbc.fetchall() ]

# Write number of devices to log DB
num_devices = len(devices)
logwr('\nThere are %d devices to backup.' % num_devices)

del num_devices

# Loop through devices
for dev in devices:
	logwr('\nConnecting to %s at %s.' % (dev['name'],hmstime()))
	# Create device folder if it does not exist
	try:
		os.mkdir('%s/%s' % (config['ARCHIVE_DIRECTORY'],dev['name']),0750)
	except OSError, e:
		# If error is not from existing file errno 17
		if e.errno != 17: 
			add_error('Error: Cannot create device archive folder - %s \nSkipping to next device' % e, str(dev['id']) )
			continue
	else:
		logwr('Created device directory %s at %s.' % ('%s/%s' % (config['ARCHIVE_DIRECTORY'],dev['name']),hmstime()))
	
	# Get IP for device or keep hostname if NULL
	ip = dev['name'] if dev['ip'] == 'NULL' else dev['ip']
	
	# create connection object
	b = bigsuds.BIGIP(hostname = ip, username = config['USERNAME'], password = passwd)
	
	# Get device info
	
	# Get CID from device
	try:
		cid = int(b.Management.DBVariable.query(['Configsync.LocalConfigTime'])[0]['value'])
	except:
		e = sys.exc_info()[1]
		add_error('Error: %s' % e , str(dev['id']) )
		continue
	# Compare old cid time to new cid time
	if cid == dev['cid']:
		logwr('CID times match for %s at %s. Configuration unchanged. Skipping download.' % (dev['name'],hmstime()))
	else:
		logwr('CID times do not match. Downloading backup file at %s.' % hmstime()) 
		# Make device create UCS file, Download UCS file, Disconnect session, Write new cid time to DB
		try:
			b.System.ConfigSync.save_configuration(filename = 'configbackup.ucs',save_flag = 'SAVE_FULL')
			dbytes = file_download(
				b,'/var/local/ucs/configbackup.ucs',
				'%s/%s/%s-%s-backup.ucs' % (config['ARCHIVE_DIRECTORY'],
				dev['name'],date,dev['name']) ,65535
			)
			logwr('Downloaded UCS file for %s - %d bytes.' % (dev['name'],dbytes))
			db.execute("""UPDATE DEVICES SET CID_TIME = ?, 
						LAST_DATE = ? WHERE ID = ?""", (cid,int(time.time()),dev['id']))
			db.commit()
		except:
			e = sys.exc_info()[1]
			add_error('Error: %s' % e , str(dev['id']) )
			continue
		
		#### Get cert info ####
		try:
			ha_pair = b.System.Failover.is_redundant()
			standby = b.System.Failover.get_failover_state()
			# Is device stand alone or active device?
			if not ha_pair or standby == 'FAILOVER_STATE_ACTIVE':
				# Clear certs from DB for this device
				db.execute("DELETE FROM CERTS WHERE DEVICE = ?", (dev['id'],))
				db.commit()
				
				# Get certs from device
				certs = b.Management.KeyCertificate.get_certificate_list("MANAGEMENT_MODE_DEFAULT")
				
				#Create list of certs for DB
				certlist = []
				for i in certs:
					certlist.append(
						(dev['id'],
						i['certificate']['cert_info']['id'], 
						i['certificate']['issuer']['organization_name'],
						i['certificate']['expiration_date'],
						i['certificate']['serial_number'],
						i['certificate']['bit_length'],
						i['certificate']['subject']['country_name'],
						i['certificate']['subject']['state_name'],
						i['certificate']['subject']['locality_name'],
						i['certificate']['subject']['organization_name'],
						i['certificate']['subject']['division_name'],
						i['certificate']['subject']['common_name'])
					)
				
				# Insert list into DB
				dbc.executemany( '''INSERT INTO CERTS ('DEVICE',
				'NAME','ISSUER','EXPIRE','SN','KEY','SUB_C',
				'SUB_S','SUB_L','SUB_O','SUB_OU','SUB_CN') 
				VALUES (?,?,?,?,?,?,?,?,?,?,?,?)''', certlist)
				db.commit()
			else:
				logwr('Device is not standalone or active unit %s. Skipping cert info download.' % hmstime() )
		except:
			e = sys.exc_info()[1]
			add_error('Error: %s' % e , str(dev['id']) )
			continue
		
	# Update DB with new complete count
	
#  Add deletion note to log file

# Keep only the number of UCS files specified by UCS_ARCHIVE_SIZE and write deletion to log

# Insert files names into DB

# Keep only the number of log files specified by LOG_ARCHIVE_SIZE and write deletion to log

# Clean jobs logs from DB


# Check number of errors. Print line if > 0


	
