import sys,ldap,time,logging, logging.handlers
import sqlite3 as sq

sys.path.append('%s/lib' % sys.path[0])
import adauth
import m2secret

def adauthenicate(user,passwd):
	'''
	Config backup function for authenticating user in active directory
	
	@param user:   Username you wish to authenicate
	@param passwd: Password of user
	@param log:    Log object
	
	This function gets the AD info from the DB and tracks which servers 
	in and up/down state. If AD server is down it is not attempted again 
	for another 10 minutes. 
	'''
	# Create logging for authentication
	authlog = logging.getLogger('authlog')
	authlog.setLevel(logging.NOTSET)
	authfh = logging.handlers.RotatingFileHandler(
				filename='/opt/f5backup/log/auth.log',
				maxBytes=10485760, backupCount=5)
	authfh.setLevel(logging.INFO)
	authfmt = logging.Formatter(
					fmt='%(asctime)s.%(msecs)d %(levelname)s: %(message)s',
					datefmt='%Y-%m-%d %H:%M:%S'
					)
	authfh.setFormatter(authfmt)
	authlog.addHandler(authfh) 
	
	# Connect to DB
	try:
		authlog.debug('Connecting to DB.')
		# Connect to DB
		db = sq.connect(sys.path[0] + '/db/main.db')
		dbc = db.cursor()
		
		# Retrieve bind user, password, domain from DB
		dbc.execute("SELECT AUTHACCT,AUTHHASH,DOMAIN FROM AUTH WHERE ID = 0")
		bind = dbc.fetchone()
	except:
		e = sys.exc_info()[1]
		authlog.critical('DB connect - %s' % e)
		authlog.removeHandler(authfh)
		raise StandardError(e)
	
	#Convert result into dict
	bind = {'acct' : bind[0],'passwd' : bind[1],'domain' : bind[2]}
	
	#Decrypt password 
	try:
		authlog.debug('Getting crypto key.')
		with open(sys.path[0] + '/.keystore/backup.key','r') as psfile:
			cryptokey = psfile.readline().rstrip()
		authlog.debug('Decrypting bind password from DB.')
		secret = m2secret.Secret()
		secret.deserialize(bind['passwd'])
		bind['passwd'] = secret.decrypt(cryptokey) 
	except:
		e = sys.exc_info()[1]
		authlog.critical('Can\'t get credentials from DB - %s' % e)
		authlog.removeHandler(authfh)
		raise StandardError(e)
	
	# Retrieve list of servers from DB
	authlog.debug('Getting list of servers from DB')
	dbc.execute("SELECT ID,SERVER,TLS,TIMEDOWN FROM AUTHSERVERS")
	servers = [ {'id' : id, 'server' : server, 'tls' : tls, 'timedown' : timedown} 
						for id, server, tls, timedown in dbc.fetchall() ]
	
	# loop through list of servers
	for server in servers:
		try:
			# Has server been declared down within the last 10 min ?
			if (server['timedown'] + 600) > int(time.time()): 
				# If yes skip to next server
				next_try = (server['timedown'] + 600) - int(time.time())
				authlog.debug('Server %s was down within 10 minutes. %d seconds until next retry.' % 
											(server['server'],next_try) )
				continue
			
			# If this server works do auth and break
			authlog.debug('Authenicating to server %s.' % server['server'])
			authlog.info('Authenicating user %s.' % user)
			auth = adauth.ADAuth(
							server['server'],
							bind['acct'],
							bind['passwd'],
							bind['domain'],
							server['tls'])
			result = auth.Authenticate(user,passwd)
			if result[0] == True:
				authlog.info('User successfully authenticated. User:%s.' % user)
				authlog.debug('User %s attr - %s' % (user,str(result[1])) )
			elif result[0] == False:
				authlog.info('User auth failed - User:%s, Reason:%s.' % (user,result[1]) )
			authlog.removeHandler(authfh)
			return result
		except ldap.SERVER_DOWN as e:
			# If not mark server down in DB and go to next  
			authlog.error('Server %s is not up. Marking server down.' % server['server'])
			try:
				dbc.execute('UPDATE AUTHSERVERS SET TIMEDOWN = ? WHERE ID = ?',
														( int(time.time()),server['id']) )
				db.commit()
			except:
				e = sys.exc_info()[1]
				authlog.error('Server down DB update - %s' % e)
			#Skip to next device
			continue
	else:
		authlog.error('No AD servers avilable.')
		authlog.removeHandler(authfh)
		return [Fasle, 'No auth servers avilable.']
	
	db.close()
	del bind
