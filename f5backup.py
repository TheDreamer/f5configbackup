#!/usr/bin/env python

############################ LICENSE #################################################
## Config Backup for F5 program to manage daily backups of F5 BigIP devices
## Copyright (C) 2014 Eric Flores
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
dev_complete = 0

# If '-debug' is in args
ecommon.debug = 1 if next((arg for arg in sys.argv if '-debug' in arg),None) else 0


############################################
# Error function
############################################
def add_error(device = ''):
	#Inc error counter
	global error,dev_errors,job_id
	error += 1
	
	#If device error add to list
	if len(device):
		dev_errors.append(device)
	
	# Insert error status into DB
	try:
		dbc.execute('''UPDATE JOBS SET ERRORS = $ERROR, DEVICE_W_ERRORS = ? 
							WHERE ID = ?''', (error,' '.join(dev_errors),job_id) )
		db.commit()
	except:
		e = sys.exc_info()[1]
		logwr('Error: Can\'t update DB: add_error - %s' % e )	

############################################
# getpass() - Gets password from file
# Return - password string 
############################################
def getpass():
	try:
		ps = open( config['PASS_FILE'] , 'r')
		password = ps.readline().rstrip()
		ps.close()
		return password
	except:
		e = sys.exc_info()[1]
		logwr('Error: Can\'t open password file - %s \nExiting program!' % e)
		add_error()
		exit()

############################################
# jobid() - Create or clear job ID in DB
# 	Returns int of job ID
############################################
def jobid():
	global date
	# Check for job on same date
	dbc.execute("SELECT ID FROM JOBS WHERE DATE = ?", (date,))
	row=dbc.fetchone()
	
	try:
		if row:
			# Overwrite job info if same date
			dbc.execute('''UPDATE JOBS SET TIME = ?, COMPLETE = 0, 
				ERRORS = 0, DEVICE_TOTAL = 0,	DEVICE_COMPLETE = 0, 
				DEVICE_W_ERRORS = '0' WHERE ID = ?''',(int(time.time()),row[0]) )
			db.commit()
			jobid = row[0]
		else:
			# Create new job info
			dbc.execute('''INSERT INTO JOBS ('DATE','TIME','ERRORS','COMPLETE',
								'DEVICE_TOTAL','DEVICE_COMPLETE','DEVICE_W_ERRORS') 
								VALUES (?,?,0,0,0,0,0)''', (date,int(time.time())) )
			db.commit()
			# Get new job ID
			jobid = dbc.lastrowid
	except:
		e = sys.exc_info()[1]
		logwr('Error: Can\'t update DB: job id - %s' % e )	
		add_error()
		exit()
	return jobid

############################################
# dev_info(bigsuds_obj,dev_id) - gets bigip info 
#  from device and inserts into DB
############################################
def dev_info(obj,dev_id):
	dinfo = device_info(obj)
	dinfo.update(active_image(obj))
	dbc.execute('''UPDATE DEVICES SET VERSION = ?,
				BUILD = ?,
				MODEL = ?,
				HOSTNAME = ?,
				DEV_TYPE = ?,
				SERIAL = ?,
				ACT_PARTITION = ? 
				WHERE ID = ?''', 
				(dinfo['version'],
				dinfo['build'],
				dinfo['model'],
				dinfo['hostname'],
				dinfo['type'],
				dinfo['serial'],
				dinfo['partition'],
				dev_id)
			)
	db.commit() 

############################################
# get_certs(bigsuds_obj) - gets cert info 
#  from device and inserts into DB
############################################
def get_certs(obj):
	ha_pair = obj.System.Failover.is_redundant()
	standby = obj.System.Failover.get_failover_state()
	# Is device stand alone or active device?
	if not ha_pair or standby == 'FAILOVER_STATE_ACTIVE':
		logwr('Device is standalone or active unit. Downloading cert info at %s.' % hmstime() )
		
		# Get certs from device
		certs = obj.Management.KeyCertificate.get_certificate_list("MANAGEMENT_MODE_DEFAULT")
		
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
		logwr('Device is not standalone or active unit. Skipping cert info download at %s.' % hmstime() )

############################################
# clean_archive() - Deletes old UCS files as
#   set by UCS_ARCHIVE_SIZE setting
############################################
def clean_archive():
	dev_folders = os.listdir(config['ARCHIVE_DIRECTORY'])
	for folder in dev_folders:
		# Get list of file from dir, match only ucs files, reverse sort
		ucslist = os.listdir('%s/%s' % (config['ARCHIVE_DIRECTORY'],folder))
		ucslist = [i  for i in ucslist if '-backup.ucs' in i]
		ucslist.sort(reverse=True)
		# loop thought list from index of archive size onward
		for ucs in ucslist[int(config['UCS_ARCHIVE_SIZE']):]:
			# Delete files
			ucsfile = '%s/%s' %(folder,ucs)
			logwr('Deleting file at %s: %s' % (hmstime(),ucsfile))
			os.remove('%s/%s' % (config['ARCHIVE_DIRECTORY'],ucsfile))

############################################
# ucs_db() - Put ucs file names into DB
############################################
def ucs_db():
	# Clear DB entries
	dbc.execute("DELETE FROM ARCHIVE")
	db.commit()
	
	file_list = []
	for dev in devices:
		# Get list of file from dir, match only ucs files, sort
		ucslist = os.listdir('%s/%s' % (config['ARCHIVE_DIRECTORY'],dev['name']))
		ucslist = [i  for i in ucslist if '-backup.ucs' in i]
		ucslist.sort()
		
		# add files to list
		file_list += ([ (dev['id'],'%s/%s' % (config['ARCHIVE_DIRECTORY'],
								'%s/' % dev['name']),ucs) for ucs in ucslist])
	
	# insert file info into DB
	dbc.executemany("INSERT INTO ARCHIVE ('DEVICE','DIR','FILE') VALUES (?,?,?);", file_list)
	db.commit()

############################################
# clean_logs() - Deletes old log files as
#   set by LOG_ARCHIVE_SIZE setting
############################################
def clean_logs():
	# Get list of files, match only log files, reverse sort
	logs = os.listdir('%s/log/' %base_dir)
	logs = [i  for i in logs if '-backup.log' in i]
	logs.sort(reverse=True)
	for log in logs[int(config['LOG_ARCHIVE_SIZE']):]:
		logwr('Deleting log file at %s: %s' % (hmstime(),log))
		os.remove('%s/log/%s' % (base_dir,log))

############################################
# clean_logdb() - Deletes old job info from
#   BD as set by LOG_ARCHIVE_SIZE setting
############################################
def clean_jobdb():
	dbc.execute('SELECT ID FROM JOBS')
	jobs = [ idn[0] for idn in dbc.fetchall() ]
	jobs.sort(reverse=True)
	deljobs = jobs[int(config['LOG_ARCHIVE_SIZE']):]
	dbc.executemany('DELETE FROM JOBS WHERE ID = ?', str(deljobs) )
	db.commit()

#*************************************************************************
#    START OF MAIN PR0GRAM
#*************************************************************************

# Open/overwrite new log file. Quit if permission denied.
try:
	ecommon.logfile = open('%s/log/%s-backup.log' % (base_dir, date),'w',0)
except:
	e = sys.exc_info()[1]
	print 'Error:', e,'\nCan\'t write to log file. Exiting program!'
	exit()

logwr('Starting backup job on %s at %s'% (date,hmstime()))

# Get password from file
logwr('\nRetrieving password from file.')
passwd = getpass()

# Connect to DB
logwr('\nOpening database file.')
try:
	db = sq.connect(base_dir + '/db/main.db')
except:
	e = sys.exc_info()[1]
	logwr('Error: Can\'t open data base - %s \nExiting program!' % e)
	add_error()
	exit()

dbc = db.cursor()

# Log job in DB
job_id = jobid()

# Get list of devices
dbc.execute("SELECT ID,NAME,IP,CID_TIME FROM DEVICES")
devices = [ {'id' : idn, 'name' : name, 'ip' : ip, 'cid': cid} for idn, name, ip, cid in dbc.fetchall() ]

# Delete all certs from DB
try:
	dbc.execute('DELETE FROM CERTS')
	db.commit()
except:
	e = sys.exc_info()[1]
	logwr('Error: Can\'t update DB: clear certs - %s' % e )	
	add_error()

# Write number of devices to log DB
num_devices = len(devices)
logwr('\nThere are %d devices to backup.' % num_devices)
try:
	dbc.execute('UPDATE JOBS SET DEVICE_TOTAL = ? WHERE ID = ?',(num_devices,job_id))
	db.commit()
except:
	e = sys.exc_info()[1]
	logwr('Error: Can\'t update DB: num_devices - %s' % e )	
	add_error()
	exit()
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
			logwr('Error: Cannot create device archive folder - %s \nSkipping to next device' % e )
			add_error(str(dev['id']))
			continue
	else:
		logwr('Created device directory %s at %s.' % ('%s/%s' % (config['ARCHIVE_DIRECTORY'],dev['name']),hmstime()))
	
	# Get IP for device or keep hostname if NULL
	ip = dev['name'] if dev['ip'] == 'NULL' else dev['ip']
	
	# create connection object
	b = bigsuds.BIGIP(hostname = ip, username = config['USERNAME'], password = passwd)
	
	# Get device info
	try:
		dev_info(b,dev['id'])
	except:
		e = sys.exc_info()[1]
		logwr('Error: %s' % e)
		add_error(str(dev['id']) )
		continue
	
	# Get CID from device
	try:
		cid = int(b.Management.DBVariable.query(['Configsync.LocalConfigTime'])[0]['value'])
	except:
		e = sys.exc_info()[1]
		logwr('Error: %s' % e)
		add_error(str(dev['id']) )
		continue
	
	# Compare old cid time to new cid time
	if cid == dev['cid']:
		logwr('CID times match for %s at %s. Configuration unchanged. Skipping download.' % (dev['name'],hmstime()))
	else:
		logwr('CID times do not match. Old - %d, New - %d. Downloading backup file at %s.' % (dev['cid'],cid,hmstime())) 
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
			logwr('Error: %s' % e)
			add_error(str(dev['id']) )
			continue
	
	# Get cert info 
	try:
		get_certs(b)
	except:
		e = sys.exc_info()[1]
		logwr('Error: %s' % e )
		add_error(str(dev['id']))
		continue
	
	# Update DB with new complete count
	dev_complete += 1
	try:
		dbc.execute('UPDATE JOBS SET DEVICE_COMPLETE = ? WHERE ID = ?', (dev_complete,job_id) )
		db.commit()
	except:
		e = sys.exc_info()[1]
		logwr('Error: Can\'t update DB: dev_complete - %s' % e )
		add_error(str(dev['id']))

#  Add deletion note to log file
logwr('\nDeleting old files:')

# Keep only the number of UCS files specified by UCS_ARCHIVE_SIZE and write deletion to log
clean_archive()

# Insert files names into DB
ucs_db()

# Keep only the number of log files specified by LOG_ARCHIVE_SIZE and write deletion to log
clean_logs()

# Clean jobs logs from DB
clean_jobdb()

# Check number of errors. Print line if > 0
if error:
	logwr('\nThere is %d error(s).' % error)

# Mark job as complete in DB
db.execute('UPDATE JOBS SET COMPLETE = 1 WHERE ID = %d' % job_id)
db.commit()

# Close DB connection
logwr('\nClosing DB connection at %s' % hmstime())
db.close()

# All done
logwr('\nBackup job completed at %s.' % hmstime())
