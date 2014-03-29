#!/usr/bin/env python
##############################################
# List of common functions for Eric Flores' programs
#  hmstime
#  logwr
#  configfile
#
##############################################


############################################
# hmstime - Get now time in H:M:S format
############################################
import time
def hmstime():
	return time.strftime("%H:%M:%S", time.localtime())

######################################################################
# logwr - Write to logfile, but also to stdout if needed for debuging
# Usage -logwr(text), 
#	set var ecommon.logfile to filename  
#	set var ecommon.debug to 1 to write to stdout
######################################################################
logfile = ''
debug = False
def logwr(text):
	global logfile, debug
	logfile.write(text + '\n')
	# if debug is True, print to stdout
	if debug:
		print text

######################################################################
# configfile - open and parse config file with item=value format. 
#		Function will ignore any blank lines and everything to the 
#		right of any comments (#).
# Usage - configfile(filename)
# Returns -
#	On success - dict of all items
#	On fail - Exception with error
######################################################################
def configfile(cfgfile):
	with open(cfgfile,'r') as f:
		tconf = f.read().splitlines()
		config = []
		# Loop through config file lines
		for i in tconf:
			# take chars to the left of comments
			i = i.rsplit('#')[0]
			# of what's left, only take lines that have '='
			if '=' in i:
				# take only from the left of comments, remove tailing 
				# white space, split into item=value into nested list 
				# and append to list "config"
				config.append(i.rsplit('#')[0].rstrip().split('='))
		return dict(config)

