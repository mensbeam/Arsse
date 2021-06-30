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

# This script is designed for Debian; some adaptation will be required for other systems

PATH=/usr/sbin/:/usr/bin:/sbin:/bin
NAME=arsse
DESC=newsfeed synchronization server
PIDFILE=/run/arsse.pid
DAEMON=/usr/bin/$NAME

. /lib/init/vars.sh
. /lib/lsb/init-functions

arsse_start() {
    touch "$PIDFILE"
    chown arsse:arsse "$PIDFILE"
    $DAEMON daemon --fork="$PIDFILE" || return 2
}

arsse_stop() {
    killproc -p "$PIDFILE" "$DAEMON"
}

arsse_reload() {
    killproc -p "$PIDFILE" "$DAEMON" HUP 
}

case "$1" in
    start)
        log_daemon_msg "Starting $DESC" "$NAME"
        if pidofproc -p $PIDFILE "$DAEMON" > /dev/null 2>&1 ; then
	        return 1
	    fi
        arsse_start
        ;;
    stop)
        log_daemon_msg "Stopping $DESC" "$NAME"
        arsse_stop
        ;;
    restart)
        log_daemon_msg "Restarting $DESC" "$NAME"
        if pidofproc -p $PIDFILE "$DAEMON" > /dev/null 2>&1 ; then
            arsse_stop
        fi
        arsse_start
        ;;
    try-restart)
        if pidofproc -p $PIDFILE "$DAEMON" > /dev/null 2>&1 ; then
            log_daemon_msg "Restarting $DESC" "$NAME"
            arsse_stop
            arsse_start
        fi
        ;;
    reload|force-reload)
        log_daemon_msg "Reloading $DESC" "$NAME"
        arsse_reload
        ;;
    status)
        status_of_proc -p $PIDFILE $DAEMON $NAME
        exit $?
        ;;
    *)
        echo "Usage: $0 {start|stop|restart|try-restart|reload|status}" >&2
        exit 3
        ;;
esac
