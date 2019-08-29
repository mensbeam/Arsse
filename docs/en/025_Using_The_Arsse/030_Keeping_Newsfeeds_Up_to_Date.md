[TOC]

# Preface

In normal operation The Arsse is expected to regularly check whether newsfeeds might have new articles, then fetch them and process them to present new or updated articles to clients. This can be achieved either by having The Arsse operate a persistent background process (termed a [daemon](https://en.wikipedia.org/wiki/Daemon_(computing)) or service), or by using an external scheduler to periodically perform single checks. Normally a daemon is preferred.

There are many ways to administer daemons, and many schedulers can be used. This section outlines a few, but many other arrangements are possible.

# As a daemon via systemd

The Arsse includes a sample systemd service unit file which can be used to quickly get a daemon running with the following procedure:

```sh
# Copy the service unit
sudo cp "/usr/share/arsse/dist/arsse.service" "/etc/systemd/system"
# Modify the unit file if needed
sudoedit "/etc/systemd/system/arsse.service"
# Enable and start the service
sudo systemctl enable --now arsse
```

The Arsse's feed updater can then be manipulated as with any other service. Consult [the `systemctl` manual](https://www.freedesktop.org/software/systemd/man/systemctl.html) for details.

# As a cron job

Keeping newsfeeds updated with [cron](https://en.wikipedia.org/wiki/Cron) is not difficult. Simply run the following command:


```sh
sudo crontab -u www-data -e
```

And add a line such as this one:

```cron
*/2 * * * * /usr/bin/env php /usr/share/arsse/arsse.php refresh-all
```

Thereafter The Arsse's will be scheduled to check newsfeeds every two minutes. Consult the manual pages for the `crontab` [format](http://man7.org/linux/man-pages/man5/crontab.5.html) and [command](http://man7.org/linux/man-pages/man1/crontab.1.html) for details.

# Appendix: how often newsfeeds are fetched

Though by default The Arsse will wake up every two minutes, newsfeeds are not actually downloaded so frequently. Instead, each newsfeed is assigned a time at which it should next be fetched, and once that time is reached a [conditional request](https://developer.mozilla.org/en-US/docs/Web/HTTP/Conditional_requests) is made. The interval between requests for a particular newsfeed can vary from 15 minutes to 24 hours based on multiple factors such as:

- The length of time since the newsfeed last changed
- The interval between publishing of articles in the newsfeed
- Whether the last fetch or last several fetches resulted in error

As a general rule, newsfeeds which change frequently are checked frequently, and those which change seldom are fetched at most daily.
