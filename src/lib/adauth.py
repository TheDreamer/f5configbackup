import ldap
import ldap.filter

class ADAuth(object):
	'''
ADAuth - class to authenticate a user via Active Directory
 by sAMAccountName and return group memberships 
	'''
	def __init__(self,server,binduser,bindpass,domain,tls=None):
		'''
      __init__(server,binduser,bindpass,domain,tls=None)
      
      @param server:   'str' The domain controller IP or FDQN
      @param binduser: 'str' The user for the LDAP bind. Can be a normal domain user.
      @param bindpass: 'str' password for binduser
      @param domain:   'str' The domain name in FQDN format (e.g. us.acme.local)
      @param tls:      'int' Make true for LDAPS (LDAP over SSL/TLS)
      '''
		self.binduser = binduser
		self.bindpass = bindpass
		# Parse domain fqdn into dn
		self.domain = ','.join([ 'dc=%s' % i for i in domain.split('.')])
		# Is this TLS or not
		if tls: 
			self.server = 'ldaps://%s' % server
		else:
			self.server = 'ldap://%s' % server
		
		ldap.set_option(ldap.OPT_X_TLS_REQUIRE_CERT, ldap.OPT_X_TLS_NEVER)
		ldap.set_option(ldap.OPT_REFERRALS, 0)
	
	def _ldSearchBySam(self,user):
		'''
_ldSearchBySam(user)
Gets the UPN for a user from the sAMAccountName
Output -
   If user not found - False
   If user found - {'userPrincipalName' : str, 'memberOf' : [list of groups]}
		'''
		ld = ldap.initialize(self.server)
		ld.protocol_version = ldap.VERSION3
		# Are the bind credentials valid ?
		try:
			bind = ld.simple_bind_s(self.binduser,self.bindpass)
		except ldap.INVALID_CREDENTIALS as e:
			# If not, tell me why
			return {'found' : False, 'error' : 'Bind credentials invalid'}
			
		# ldap search 
		user = ldap.filter.escape_filter_chars(user,1) # Esacpe user input
		criteria = '(&(objectClass=user)(sAMAccountName=%s))' % user
		attributes = ['memberOf','userPrincipalName']
		search = ld.search_s(self.domain, ldap.SCOPE_SUBTREE, criteria, attributes)
		# is there a match ?
		if search[0][0] == None: return {'found' : False, 'error' : 'User not found'}
		# Parse response to give dict of UPN and group membership
		upn = search[0][1]['userPrincipalName'][0]
		memberOf = [ i for i in search[0][1]['memberOf'] ]
		ld.unbind()
		del ld
		return { 'found' : True ,'userPrincipalName' : upn, 'memberOf' : memberOf}
	
	def Authenticate(self,user,passwd):
		'''
ldAuth(user,passwd)
Authenticates user by sAMAccountName and gets group membership
Returns -
   If user not found - [False, 'reason why auth failed']
   If user found - [True, [list of group memberships] ]
		'''
		userinfo = self._ldSearchBySam(user)
		# Did search fail ?
		if userinfo['found'] == False: return [False,userinfo['error']]
		ld = ldap.initialize(self.server)
		ld.protocol_version = ldap.VERSION3
		# See if you can bind with user creds
		try:
			bind = ld.simple_bind_s(userinfo['userPrincipalName'],passwd)
			ld.unbind()
			return [True , userinfo['memberOf']]
		except ldap.INVALID_CREDENTIALS as e:
			# If not, tell me why
			return [False,e[0]['desc']]
