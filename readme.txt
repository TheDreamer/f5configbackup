F5 Config backup release notes
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

##F5 Config backup 2.5 ##################################
	Added WebUI
	Added JOB table cleaner 
	

##F5 Config backup 2.4 ##################################
	Added SQLite DB for device records
	Added password based login and removed SSH key login
	Added TMSH detection to allow non root login
	Other misc fixes
# Version 2.4.1 -
	Revamped shell detection
	Fixed device dir creation bug
	IP is updated in DB if a device IP changes in the device file
# Version 2.4.4 -
	Added job reporting table to DB
# Version 2.4.5 -
	SQL query enhancements
	Shell detection bug fix
	Fixed delete orphan matching bug
	Fixed new CID time validation 