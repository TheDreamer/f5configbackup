#!/usr/bin/env python
#
import sys
from daemon import runner
from tornado.wsgi import WSGIContainer
from tornado.httpserver import HTTPServer
from tornado.ioloop import IOLoop
from tornado.options import options

sys.path.append('%s/lib' % sys.path[0])
from api_lib import app


class webservice():

	def __init__(self):
		self.stdin_path = '/dev/null'
		self.stdout_path = '/dev/tty'
		self.stderr_path = '/dev/tty'
		self.pidfile_path = '/opt/f5backup/pid/api.pid'
		self.pidfile_timeout = 5

	def run(self):
		options.log_file_prefix = '/opt/f5backup/log/api.log'
		options.log_file_num_backups = 3
		options.log_file_max_size = 10485760
		options.parse_command_line()
		http_server = HTTPServer(WSGIContainer(app))
		http_server.listen(5380, address='127.0.0.1')
		IOLoop.instance().start()

daemon_runner = runner.DaemonRunner( webservice() )

try:
	daemon_runner.do_action()
except:
	e = sys.exc_info()[1]
	print 'Error: %s' % e
	exit()