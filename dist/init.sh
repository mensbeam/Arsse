#!/bin/sh

### BEGIN INIT INFO
# Provides:          arsse
# Required-Start:    $local_fs $network
# Required-Stop:     $local_fs postgresql mysql
# Should-Start:      postgresql mysql
# Should-Stop:       postgresql mysql
# Default-Start:     2 3 4 5
# Default-Stop:      0 1 6
# Short-Description: The Advanced RSS Environment
# Description:       The Arsse is a multi-protocol Web newsfeed synchronization service
### END INIT INFO

NAME=arsse
DAEMON=/usr/bin/arsse

