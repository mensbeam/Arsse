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

- Adding and removing users and managing their metadata
- Managing passwords and authentication tokens
- Importing and exporting OPML newsfeed-lists

These are documented in the next section **PRIMARY COMMANDS**. Further, seldom-used commands are documented in the following section **ADDITIONAL COMMANDS**.

# PRIMARY COMMANDS

## Managing users and metadata

**arsse user [list]**

: Displays a simple list of user names with one entry per line

**arsse user add** <*username*> [<*password*>] [--admin]

: Adds a new user to the database with the specified username and password. If <*password*> is omitted a random password will be generated and printed.

: The **--admin** flag may be used to mark the user as an administrator. This has no meaning within the context of The Arsse as a whole, but it is used control access to certain features in the Miniflux and Nextcloud News protocols. 

**arsse user remove** <*username*>

: Immediately removes a user from the database. All associated data (folders, subscriptions, etc.) are also removed.

**arsse user show** <*username*>

: Displays a table of metadata properties and their assigned values for <*username*>. These properties are primarily used by the Miniflux protocol. Consult the section **USER METADATA** for details.

**arsse user set** <*username*> <*property*> <*value*>

: Sets a metadata property for a user. These properties are primarily used by the Miniflux protocol. Consult the section **USER METADATA** for details.

**arsse user unset** <*username*> <*property*>

: Clears a metadata property for a user. The property is thereafter set to its default value, which is protocol-dependent.

## Managing passwords and authentication tokens

**arsse user set-pass** <*username*> [<*password*>] [--fever]

: Changes a user's password to the specified value. If no password is specified, a random password will be generated and printed.
\
: The **--fever** option sets a user's Fever protocol password instead of their general password. As Fever requires that passwords be stored insecurely, users do not have Fever passwords by default, and logging in to the Fever protocol is disabled until a password is set. It is highly recommended that a user's Fever password be different from their general password.
