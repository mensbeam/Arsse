Upgrading The Arsse is usually simple:

1. Download the latest release
2. Check the `UPGRADING` file for any special notes
3. Stop the newsfeed refreshing service if it is running
4. Extract the new version on top of the old one
5. Ensure permissions are still correct
6. Restart the newsfeed refreshing service

By default The Arsse will perform any required database schema upgrades when the new version is executed, and release packages contain all newly required library dependencies. 

Occasionally changes to Web server configuration have been required, when new protocols become supported; such changes are always explicit in the `UPGRADING` file
