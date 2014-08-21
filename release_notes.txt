Config Backup for F5 release notes
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

This product includes PHP software, freely available from <www.php.net/software/>

## Version 3.0 ##################################
	Created appliance
	Moved backup settings to web UI
	Moved backup job credentials via web UI
	Add status indication to webUI

## Version 2.7 ##################################
	Ported to Python 
	Device communication through iControl (no more SSH)
	Added cert info download
	Added device info download
	Added device add/delete through web UI

## Version 2.5 ##################################
	Added WebUI
	Added JOB table cleaner 

## Version 2.4.5 ##################################
	SQL query enhancements
	Shell detection bug fix
	Fixed delete orphan matching bug
	Fixed new CID time validation 

## Version 2.4.4 ##################################
	Added job reporting table to DB

## Version 2.4.1 ##################################
	Revamped shell detection
	Fixed device dir creation bug
	IP is updated in DB if a device IP changes in the device file
	
## Version 2.4 ##################################
	Added SQLite DB for device records
	Added password based login and removed SSH key login
	Added TMSH detection to allow non root login
	Other misc fixes