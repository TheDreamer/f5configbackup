#!/usr/bin/env python
import sys, time
import traceback
import sqlite3 as sq
from daemon import runner

sys.path.append('%s/lib' % sys.path[0])
import f5backup_lib
import logsimple

# Default action for uncaught errors
def execption_hook(type,value,tb):
   exception = open('/opt/f5backup/log/backupd.core','w',0)
   exception.write('Traceback (most recent call last):\n')
   exception.write( ''.join(traceback.format_tb(tb)) )
   exception.write( type.__name__ + ': ' + str(value) + '\n')
   exception.close()
   exit()

sys.excepthook = execption_hook


class f5backup():
   def __init__(self):
      self.stdin_path = '/dev/null'
      self.stdout_path = '/dev/null'
      self.stderr_path = '/dev/null'
      self.pidfile_path = '/opt/f5backup/pid/f5backup.pid'
      self.pidfile_timeout = 5
   
   def dbtime(self):
      '''
   dbtime() - Get backup time from DB
      '''
      try:
         # Connect to DB
         db = sq.connect(sys.path[0] + '/db/main.db')
         dbc = db.cursor()
         
         # Get backup time from DB
         dbc.execute("""SELECT VALUE FROM BACKUP_SETTINGS_INT
                      WHERE NAME = 'BACKUP_TIME'""")
         db_time = dbc.fetchone()[0]
         db.close()
      except:
         e = sys.exc_info()[1]
         self.log.critical('Error: Cant open DB - %s' % e)
         exit()
      return db_time
   
   def run(self):
      # Open new log file. Quit if permission denied.
      try:
         logfile = '%s/log/backupd.log' % sys.path[0]
         self.log = logsimple.LogSimple(logfile,max_files=1,max_bytes=1048576)
         self.log.setlevel('INFO')
      except:
         e = sys.exc_info()[1]
         print 'Unable to open logfile - %s' % e
         exit()
      
      # Get log level from DB and reset in logging object
      self.log.info('Getting log level from db.')
      try:
         db = sq.connect(sys.path[0] + '/db/main.db')
         dbc = db.cursor()
         dbc.execute("SELECT LEVEL FROM LOGGING WHERE NAME = 'BACKUPD'")
      except:
         e = sys.exc_info()[1]
         print 'Error: Cant open DB - %s' % e
         exit()
      self.log.setlevel( str(dbc.fetchone()[0]) )
      db.close()

      # Check time every 10 seconds
      self.log.info('Starting backup daemon.')
      while True:
         # Get local time of day in minutes
         ltime = time.localtime()
         tcheck = (ltime[3] * 60) + ltime[4]

         # Get DB time
         self.log.debug('Checking DB backup time.')
         db_time = self.dbtime()
         self.log.debug('DB backup time is %d' % db_time)
         
         # Does minute time match DB time ?
         if (tcheck == db_time):
            # Run backup job
            # Catch errors 
            self.log.info('Backup time is now. Running backup job.')
            try:
               f5backup_lib.main()
            except f5backup_lib.BackupError as e:
               self.log.error('An error has occured - %s' % e[1])
            
            self.log.info('Backup job completed.')
            # wait 61 seconds after finishing to 
            # ensure job does not run twice
            time.sleep(61)
         
         # wait 10 secs before trying again
         time.sleep(10)

# Run main daemon
daemon_runner = runner.DaemonRunner( f5backup() )
daemon_runner.do_action()