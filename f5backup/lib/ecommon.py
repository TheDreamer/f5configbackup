#!/usr/bin/env python
############################ LICENSE #################################################
## Config Backup for F5 script. Perl script to manage daily backups of F5 BigIP devices
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

'''
This module contains various functions for Eric Flores' programs
Written by Eric Flores

Functions in this module -
  hmstime
  logwr
  configfile
  getpass
'''

import time


def hmstime():
	'''
hmstime - Get now time in H:M:S format	
	'''
	return time.strftime("%H:%M:%S", time.localtime())


logfile = ''
debug = False
def logwr(text):
	'''
logwr - Write to logfile, but also to stdout if needed for debuging
Usage -logwr(text), 
   set var ecommon.logfile to filename  
   set var ecommon.debug to 1 to write to stdout
	'''
	global logfile, debug
	logfile.write(text + '\n')
	# if debug is True, print to stdout
	if debug:
		print text


def configfile(cfgfile):
	'''
configfile - open and parse config file with item=value format. 
   Function will ignore any blank lines and everything to the 
   right of any comments (#).
Usage - configfile(filename)
Returns -
   On success - dict of all items
   On fail - Exception with error
	'''
	with open(cfgfile,'r') as f:
		tconf = f.read().splitlines()
		config = []
		# Loop through config file lines
		for i in tconf:
			# take text to the left of comments
			i = i.rsplit('#')[0]
			# of what's left, only take lines that have '='
			if '=' in i:
				# Split item at '=',	remove trailing white space 
				# and append to list "config"
				config.append(i.rstrip().split('='))
		return dict(config)

def getpass(passfile):
	'''
getpass('filename') - Gets text from a single line file
Returns - 
  On success - string
  On fail - Exception with error 
	'''
	with open(passfile,'r') as psfile:
		return psfile.readline().rstrip()
