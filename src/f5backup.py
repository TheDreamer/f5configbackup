#!/usr/bin/env python

import sys, time
import sqlite3 as sq
from daemon import runner

sys.path.append('%s/lib' % sys.path[0])
import f5backup_lib

def dbtime():
	'''
dbtime() - Get backup time from DB
	'''
	try:
		# Connect to DB
		db = sq.connect(sys.path[0] + '/db/main.db')
		dbc = db.cursor()
		
		# Get backup time from DB
		dbc.execute("SELECT VALUE FROM BACKUP_SETTINGS_INT"
						+ " WHERE NAME = 'BACKUP_TIME'")
		db_time = dbc.fetchone()[0]
		db.close()
	except:
		e = sys.exc_info()[1]
		print 'Error: Cant open DB - %s' % e
		exit()
	return db_time


class f5backup():
	def __init__(self):
		self.stdin_path = '/dev/null'
		self.stdout_path = '/dev/null'
		self.stderr_path = '/dev/null'
		self.pidfile_path = '/opt/f5backup/pid/f5backup.pid'
		self.pidfile_timeout = 5
	
	def run(self):
		# Check time every 10 seconds
		while True:
			# Get local time of day in minutes
			ltime = time.localtime()
			tcheck = (ltime[3] * 60) + ltime[4]
			
			# Get DB time
			db_time = dbtime()
			
			# Does minute time match DB time ?
			if (tcheck == db_time):
				# Run backup job
				# Catch errors 
				try:
					f5backup_lib.main()
				except f5backup_lib.BackupError as e:
					print e
				
				# wait 61 seconds after finishing to 
				# ensure job does not run twice
				time.sleep(61)
			
			# wait 10 secs before trying again
			time.sleep(10)

daemon_runner = runner.DaemonRunner( f5backup() )
daemon_runner.do_action()