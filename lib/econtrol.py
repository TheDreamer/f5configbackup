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
import sys, time, bigsuds, os
from base64 import b64decode,b64decode

############################################################################
# file_download - Download file from F5
# Usage - file_download(bigip_obj,src_file,dst_file,chunk_size,buff = n)
#		bigip_obj - the bigsuds icontol object
#		src_file - file on F5
#		dst_file - local file
#		chunk_size - download size for each chunk
#		buff - (optional) size of file write buffer, default 1MB
# Returns - list  
# Element 0 - Job completed - True/False
# Element 1 - dict keys - 
#			  bytes - returns file size in bytes if job completed
#			  error - returns error message if job failed
############################################################################
def file_download(bigip_obj,src_file,dst_file,chunk_size,buff = 1048576):
	# Set begining vars
	download = 1
	foffset = 0
	timeout_error = 0 
	fbytes = 0
	
	# Open file for writing, default buffer size is 1MB
	try:
		# Open partial file for writing
		f_dst = open(dst_file + '.part','w',buff)
	except:
		e = sys.exc_info()[1]
		raise bigsuds.ConnectionError('Can\'t create file: %s' % e)
	
	# Main loop
	while download:
		# Try to download chunk
		try:
			chunk = bigip_obj.System.ConfigSync.download_file(file_name = src_file, chunk_size = chunk_size, file_offset = foffset)
		except:
			e = sys.exc_info()[1]
			timeout_error += 1
			# is this the 3rd connection attempt?
			if (timeout_error >= 3):
				# Close partial file & delete, raise error
				f_dst.close()
				os.remove(dst_file + '.part')
				raise bigsuds.ConnectionError(e)
			else:
				# Otherwise wait 2 seconds before retry
				time.sleep(2)
				continue
		# reset error counter after a good connect
		timeout_error = 0
		
		# Write contents to file
		fchunk = b64decode(chunk['return']['file_data'])
		f_dst.write(fchunk)
		fbytes += sys.getsizeof(fchunk) - 40
		
		# Check to see if chunk is end of file
		fprogress = chunk['return']['chain_type']
		if (fprogress == 'FILE_FIRST_AND_LAST')  or (fprogress == 'FILE_LAST' ):
			# Close file, rename from name.part to name
			f_dst.close()
			os.rename(dst_file + '.part' , dst_file)
			download = 0
			return fbytes
		
		# set new file offset
		foffset = chunk['file_offset']

############################################################################
# active_image - Return a list of the active image
# Usage - active_image(obj)
#		  obj - The bigsuds connection object
# Returns - dict
#     {'version': 'str', 'build': 'str', 'partition': 'str'}
############################################################################
def active_image(obj):
	software = obj.System.SoftwareManagement.get_all_software_status()
	for i in software:
		if i['active'] == True:
			return {'version' : i['version'], 'build' : i['build'], 'partition' : i['installation_id']['install_volume']}

############################################################################
# device_info - returns useful info about F5 device
# Usage - device_info(obj)
#		  obj - The bigsuds connection object 
# Returns - dict
#		  {'model': 'str', 'hostname': 'str', 'type': 'str', 'serial': 'str'}
############################################################################
def device_info(obj):
	device = obj.System.SystemInfo.get_system_information()
	return {'hostname' : device['host_name'],'model' : device['platform'], 'type' : device['product_category'], 'serial' : device['chassis_serial']}

