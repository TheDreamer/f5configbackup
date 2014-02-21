#!/bin/bash
# backup web ui installation script

echo -e "Starting installation of the Backup Program Web UI.\n"

#------------- Is this the root user ? ------------- 

if [ `whoami` != "root" ];then
	echo -e "Not the root user! Please run script as root."
	exit
fi

#------------- Check for apache ------------- 
echo -e "Checking for Apache."
APACHE=$(apachectl -v 2> /dev/null | grep version | cut -d : -f2)
if [ -z "$APACHE" ]; then 
	echo -e 'Apache not installed! Please install.'
	exit
fi
echo -e " Version info - $APACHE\n"

# Apache username 
echo -e "\nWhat username does apache run as ? "
echo -e "CentOS/Redhat/Fedora uses default name of \"apache\"."
echo -e "Ubuntu uses default name of \"www-data\"."
echo -e "Press enter for default. [apache]"
read APACHE_USER

# If entry is blank set APACHE_USER to default
if [ -z $APACHE_USER ]; then
	APACHE_USER="apache"
fi 
echo -e "Apache username is $APACHE_USER"
#------------- Check for mod_ssl ------------- 
echo -e "\nChecking for mod_ssl in apache."
SSL=$(apachectl -M 2> /dev/null | grep ssl)
if [ -z "$SSL" ]; then 
	echo -e " Mod SSL not installed! Please install."
	exit
fi
echo -e " Mod SSL is installed.\n"

#------------- Check for php ------------- 
echo -e "Checking for PHP."
PHP=$(php -v 2> /dev/null | sed q | cut -d ' ' -f 1-2)
if [ -z "$PHP" ]; then
	echo -e ' PHP is not installed! Please install.'
	exit
fi
echo -e " Version - $PHP"
MOD_PHP=$(apachectl -M 2> /dev/null | grep php)
if [ -z "$MOD_PHP" ]; then 
	echo -e ' Mod PHP not installed! Please install.'
	exit
fi
echo -e " Mod PHP is installed.\n"

#------------- Check for PDO ------------- 
echo -e "Checking for PHP PDO SQLite driver."
PDO=$(php -m 2> /dev/null | grep pdo_sqlite)
if [ -z "$PDO" ]; then
	echo -e " PDO_Sqlite is not installed! Please install."
	exit
fi
echo -e " PDO_Sqlite is installed.\n"

#------------- Location of backup program config file ------------- 
echo -e "Where is the Backup Program config file ?"
echo "Press enter for default. [/var/f5backup/f5backup.conf]"
read -e -r CONFIG_FILE

# If entry is blank set CONFIG_FILE to default
if [ -z $CONFIG_FILE ]; then
	CONFIG_FILE="/var/f5backup/f5backup.conf"
fi 
echo -e "\nConfig file is $CONFIG_FILE"

# Get variables from file
. $CONFIG_FILE

#------------- Copy files -------------
echo -e "Copying Web UI files"
cp -vR redirect/ $BASE_DIRECTORY
cp -vR ui/ $BASE_DIRECTORY

#------------- Change file ownership -------------
echo -e "\nChaning file ownership for directories."
chown -vR root:$APACHE_USER $BASE_DIRECTORY/db
chown -vR root:$APACHE_USER $BASE_DIRECTORY/log
chown -vR root:$APACHE_USER $BASE_DIRECTORY/ui
chown -vR root:$APACHE_USER $BASE_DIRECTORY/redirect
chown -vR root:$APACHE_USER $ARCHIVE_DIRECTORY

#------------- Change file permissions -------------
echo -e "\nChaning file permissions for directories."
chmod -vR 0775 $BASE_DIRECTORY/db
chmod -vR 0775 $BASE_DIRECTORY/log
chmod -vR 0775 $BASE_DIRECTORY/ui
chmod -vR 0775 $BASE_DIRECTORY/redirect
chmod -R 0775 $ARCHIVE_DIRECTORY

#------------- Check for selinux -------------
echo -e "\nChecking for selinux."
SELINUX=$(getenforce 2> /dev/null)

if [ $? = 0 ]; then
	if [ $SELINUX == "Disabled" ]; then
		echo -e " Selinux is disabled."
	else
		echo -e " ---------------------------------------------------------------
 Selinux found and is in the $SELINUX state.
 Please set selinux permissions for the following directories -
  - $BASE_DIRECTORY/db
  - $BASE_DIRECTORY/ui
  - $BASE_DIRECTORY/redirect
  - $BASE_DIRECTORY/log
  - $ARCHIVE_DIRECTORY
  
  How to do in CentOS - 
  chcon -Rv --type=httpd_sys_content_t $BASE_DIRECTORY/db
  chcon -Rv --type=httpd_sys_content_t $BASE_DIRECTORY/ui 
  chcon -Rv --type=httpd_sys_content_t $BASE_DIRECTORY/redirect
  chcon -Rv --type=httpd_sys_content_t $BASE_DIRECTORY/log
  chcon -Rv --type=httpd_sys_content_t $ARCHIVE_DIRECTORY
 ---------------------------------------------------------------"
	fi
else
	echo -e " Selinux not found."
fi

#------------- Create config files -------------
echo -e "\nCreating config files."

cp conf/centos.tmp httpd.conf_centos
sed -i s@BASE_DIRECTORY@$BASE_DIRECTORY@g httpd.conf_centos
sed -i s@ARCHIVE_DIRECTORY@$ARCHIVE_DIRECTORY@g httpd.conf_centos

cp conf/ubuntu.tmp apache2.conf_ubuntu
sed -i s@BASE_DIRECTORY@$BASE_DIRECTORY@g apache2.conf_ubuntu
sed -i s@ARCHIVE_DIRECTORY@$ARCHIVE_DIRECTORY@g apache2.conf_ubuntu

echo -e "---------------------------------------------------------------
Installation completed! Some manual steps are required -
  - If SELinux is enabled see message above.
  - If iptables is enabled open port 80 and 443.
  - Look for the PHP INI file in the same directory as install script. 
     1) Backup the original PHP INI file
         For the CentOS default install, the config file is /etc/php.ini
         For the Ubuntu default install, the config file is /etc/php5/apache2/php.ini
     2) Pick the config file that matches your distro.
     3) Copy the contents to the config file.
  - Look for Apache config file in the same directory as install script.
     1) Back up original config file.
         For the CentOS default Apache install, the config file is /etc/httpd/conf/httpd.conf
         For the Ubuntu default Apache install, the config file is /etc/apache2/apache2.conf
     2) Pick config file that matches your distro.
     3) Copy contents to Apache config file and tweak to your liking (certs,ssl,logging,etc) - 
     4) (Re)start Apache.			
---------------------------------------------------------------"
