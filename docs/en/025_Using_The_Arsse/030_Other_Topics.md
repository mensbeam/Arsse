[TOC]

# Refreshing newsfeeds with a cron job

Normally The Arsse has a systemd service which checks newsfeeds for updates and processes them into its database for the user. If for whatever reason this is not practical a [cron](https://en.wikipedia.org/wiki/Cron) job may be used instead.

Keeping newsfeeds updated with cron is not difficult. Simply run the following command:


```sh
sudo crontab -u arsse -e
```

And add a line such as this one:

```
*/2 * * * * /usr/bin/arsse refresh-all
```

Thereafter The Arsse's will be scheduled to check newsfeeds every two minutes. Consult the manual pages for the `crontab` [format](http://man7.org/linux/man-pages/man5/crontab.5.html) and [command](http://man7.org/linux/man-pages/man1/crontab.1.html) for details.

# How often newsfeeds are fetched

Though by default The Arsse will wake up every two minutes, newsfeeds are not actually downloaded so frequently. Instead, each newsfeed is assigned a time at which it should next be fetched, and once that time is reached a [conditional request](https://developer.mozilla.org/en-US/docs/Web/HTTP/Conditional_requests) is made. The interval between requests for a particular newsfeed can vary from 15 minutes to 24 hours based on multiple factors such as:

- The length of time since the newsfeed last changed
- The interval between publishing of articles in the newsfeed
- Whether the last fetch or last several fetches resulted in error

As a general rule, newsfeeds which change frequently are checked frequently, and those which change seldom are fetched at most daily.
