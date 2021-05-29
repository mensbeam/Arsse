% ARSSE(1) arsse 0.9.2
% J. King
% 2021-05-28

# NAME

arsse - manage an instance of The Advanced RSS Environment (The Arsse)

# SYNOPSIS

**arsse** <*command*> [<*args*>]\
**arsse** --version\
**arsse** -h|--help

# DESCRIPTION

**arsse** allows a sufficiently privileged user to perform various administrative operations related to The Arsse, including:

- Adding and removing users
- Managing passwords and authentication tokens
- Importing and exporting OPML newsfeed-lists

These are documented in the next section **PRIMARY COMMANDS**. Further, seldom-used commands are documented in the following section **ADDITIONAL COMMANDS**.

# PRIMARY COMMANDS

## Managing users

**arsse user [list]**

: Displays a simple list of user names with one entry per line

**arsse user add** <*username*> [<*password*>] [--admin]

: Adds a new user to the database with the specified username and password. If <*password*> is omitted a random password will be generated and printed.

   The **--admin** flag may be used to mark the user as an administrator. This has no meaning within the context of The Arsse as a whole, but it is used control access to certain features in the Miniflux and Nextcloud News protocols. 

**arsse user remove** <*username*>

: Immediately removes a user from the database. All associated data (folders, subscriptions, etc.) are also removed.
