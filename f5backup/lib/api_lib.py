#!/usr/bin/env python
import os, sys 
from ecommon
from flask import Flask, jsonify, abort, request, make_response

# Get program libs
sys.path.append('%s/lib' % sys.path[0])
import m2secret
from ecommon import getpass

app = Flask(__name__)

############################################################
### Error handling 
############################################################
@app.errorhandler(400)
def not_found(error):
	return make_response(jsonify( { 'error': 'BAD REQUEST' } ), 400)

@app.errorhandler(404)
def not_found(error):
	return make_response(jsonify( { 'error': 'RESOURCE NOT FOUND' } ), 404)

@app.errorhandler(500)
def not_found(error):
	return make_response(jsonify( { 'error': 'INTERNAL SERVER ERROR' } ), 500)

############################################################
### Start web service functions
############################################################
@app.route("/")
def hello():
	return '''<html>
<h1>F5 Backup API Functions</h1>
<p><strong>status</strong><br />
URI - /api/v1.0/status<br />
Description - Function to use as a health check for web service</p>
<p><strong>encrypt</strong><br />
URI - /api/v1.0/crypto/encrypt/<br />
Description - Encrypt string using key from file</p>
<html>'''

### Function to use as a health check for web service
@app.route("/api/v1.0/status")
def status():
	return jsonify( {'status' : 'ONLINE'} )

### Encryption function - encrypt string using key from file
@app.route('/api/v1.0/crypto/encrypt/', methods = ['POST'])
def encrypt():
	# Check for element string and that it is not blank
	if 'string' not in request.json.keys():
		abort(400)
	elif not request.json or len(request.json['string']) == 0:
		abort(400)
	
	# Get encryption password or give 500 error
	try:
		cryptokey = getpass('%s/.keystore/backup.key' % sys.path[0])
	except:
		abort(500)
	
	# Encrypt string and return response
	secret = m2secret.Secret()
	secret.encrypt(str(request.json['string']), cryptokey)
	serialized = secret.serialize()
	return jsonify( {'result' : serialized } )
	
	# Clear key from mem
	cryptokey = None
