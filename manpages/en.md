---
title: "ARSSE"
section: 1
date: 2021-07-03
footer: "arsse 0.10.0"
header: "User Commands"
---

# NAME

arsse - manage an instance of The Advanced RSS Environment (The Arsse)

# SYNOPSIS

**arsse user** [**list**]\
**arsse user add** <_username_> [<_password_>] [**--admin**]\
**arsse user remove** <_username_>\
**arsse user show** <_username_>\
**arsse user set** <_username_> <_property_> <_value_>\
**arsse user unset** <_username_> <_property_>\
**arsse user set-pass** <_username_> [<_password_>] [**--fever**]\
**arsse user unset-pass** <_username_> [**--fever**]\
**arsse user auth** <_username_> <_password_> [**--fever**]\
**arsse token list** <_username_>\
**arsse token create** <_username_> [<_label_>]\
**arsse token revoke** <_username_> [<_token_>]\
**arsse import** <_username_> [<_file_>] [**-f**|**--flat**] [**-r**|**--replace**]\
**arsse export** <_username_> [<_file_>] [**-f**|**--flat**]\
**arsse daemon** [**--fork=**<_pidfile_>]\ 
**arsse feed refresh-all**\
**arsse feed refresh** <_n_>\
**arsse conf save-defaults** [<_file_>]\
**arsse --version**\
**arsse -h**|**--help**

# DESCRIPTION

**arsse** allows a sufficiently privileged user to perform various administrative operations related to The Arsse, including:

- Adding and removing users and managing their metadata
- Managing passwords and authentication tokens
- Importing and exporting OPML newsfeed-lists

These are documented in the next section **PRIMARY COMMANDS**. Further, seldom-used commands are documented in the following section **ADDITIONAL COMMANDS**.

# PRIMARY COMMANDS

## Managing users and metadata

**arsse user [list]**

:   Displays a simple list of user names with one entry per line

**arsse user add** <_username_> [<_password_>] [**--admin**]

:   Adds a new user to the database with the specified username and password. If <_password_> is omitted a random password will be generated and printed.

    The **--admin** flag may be used to mark the user as an administrator. This has no meaning within the context of The Arsse as a whole, but it is used control access to certain features in the Miniflux and Nextcloud News protocols. 

**arsse user remove** <_username_>

:   Immediately removes a user from the database. All associated data (folders, subscriptions, etc.) are also removed.

**arsse user show** <_username_>

:   Displays a table of metadata properties and their assigned values for <_username_>. These properties are primarily used by the Miniflux protocol. Consult the section **USER METADATA** for details.

**arsse user set** <_username_> <_property_> <_value_>

:   Sets a metadata property for a user. These properties are primarily used by the Miniflux protocol. Consult the section **USER METADATA** for details.

**arsse user unset** <_username_> <_property_>

:   Clears a metadata property for a user. The property is thereafter set to its default value, which is protocol-dependent.

## Managing passwords and authentication tokens

**arsse user set-pass** <_username_> [<_password_>] [**--fever**]

:   Changes a user's password to the specified value. If no password is specified, a random password will be generated and printed.

    The **--fever** option sets a user's Fever protocol password instead of their general password. As the Fever protocol requires that passwords be stored insecurely, users do not have Fever passwords by default, and logging in to the Fever protocol is disabled until a suitable password is set. It is highly recommended that a user's Fever password be different from their general password.

**arsse user unset-pass** <_username_> [**--fever**]

:   Unsets a user's password, effectively disabling their account. As with password setting, the **--fever** option may be used to operate on a user's Fever password instead of their general password.

**arsse user auth** <_username_> <_password_> [**--fever**]

:   Tests logging a user in. This only checks that the user's password is correctly recognized; it has no side effects.

    The **--fever** option may be used to test the user's Fever protocol password, if any.

**arsse token list** <_username_>

:   Displays a user's authentication tokens in a simple tabular format. These tokens act as an alternative means of authentication for the Miniflux protocol and may be required by some clients. They do not expire.

**arsse token create** <_username_> [<_label_>]

:   Creates a new random login token and prints it. These tokens act as an alternative means of authentication for the Miniflux protocol and may be required by some clients. An optional <_label_> may be specified to give the token a meaningful name.

**arsse token revoke** <_username_> [<_token_>]

:   Deletes the specified token from the database. The token itself must be supplied, not its label. If it is omitted all tokens are revoked.

## Importing and exporting data

**arsse import** <_username_> [<_file_>] [**-r**|**--replace**] [**-f**|**--flat**]

:   Imports the newsfeeds, folders, and tags found in the OPML formatted <_file_> into the account of the specified user. If no file is specified, data is instead read from standard input. Import operations are atomic: if any of the newsfeeds listed in the input cannot be retrieved, the entire import operation will fail.

    The **--replace** (or **-r**) option interprets the OPML file as the list of **all** desired newsfeeds, folders and tags, performing any deletion or moving of existing entries which do not appear in the flle. If this option is not specified, the file is assumed to list desired **additions** only.

    The **--flat** (or **-f**) option can be used to ignore any folder structures in the file, importing any newsfeeds directly into the root folder. Combining this with the **--replace** option is possible.

**arsse export** <_username_> [<_file_>] [**-f**|**--flat**]

:   Exports a user's newsfeeds, folders, and tags to the OPML file specified by <_file_>, or standard output if no file is specified. Note that due to a limitation of the OPML format, any commas present in tag names will not be retained in the export.

    The **--flat** (or **-f**) option can be used to omit folders from the export. Some OPML implementations may not support folders, or arbitrary nesting; this option may be used when planning to import into such software.

# ADDITIONAL COMMANDS

**arsse daemon** [**--fork=**<_pidfile_>]

:   Starts the newsfeed fetching service. Normally this command is only invoked by Systemd.

    The **--fork** option executes an "old-style" fork-then-terminate daemon rather than a "new-style" non-terminating daemon. This option should only be employed if using a System V-style init daemon on POSIX systems; normally Systemd is used. When using this option the daemon will write its process identifier to <_pidfile_> after forking.

**arsse feed refresh-all**

:   Performs a one-time fetch of all stale feeds. This command can be used as the basis of a **cron** job to keep newsfeeds up-to-date.

**arsse feed refresh** <_n_>

:   Performs a one-time fetch of the feed (not subscription) identified by integer <_n_>. This is used internally by the fetching service and should not normally be needed.

**arsse conf save-defaults** [<_file_>]

:   Prints default configuration parameters to standard output, or to <_file_> if specified. Each parameter is annotated with a short description of its purpose and usage.

# USER METADATA

User metadata are primarily used by the Miniflux protocol, and most properties have identical or similar names to those used by Miniflux. Properties may also affect other protocols, or conversely may have no effect even when using the Miniflux protocol; this is noted below when appropriate.

Booleans accept any of the values **true**/**false**, **1**/**0**, **yes**/**no**, or **on**/**off**.

The following metadata properties exist for each user:

**num**
:   Integer. The numeric identifier of the user. This is assigned at user creation and is read-only.

**admin**
:   Boolean. Whether the user is an administrator. Administrators may manage other users via the Miniflux protocol, and also may trigger feed updates manually via the Nextcloud News protocol.

**lang**
:   String. The preferred language of the user, as a BCP 47 language tag e.g. "en-ca". Note that since The Arsse currently only includes English text it is not used by The Arsse itself, but clients may use this metadatum in protocols which expose it.

**tz**
:   String. The time zone of the user, as a tzdata identifier e.g. "America/Los_Angeles".

**root_folder_name**
:   String. The name of the root folder, in protocols which allow it to be renamed.

**sort_asc**
:   Boolean. Whether the user prefers ascending sort order for articles. Descending order is usually the default, but explicitly setting this property false will also make a preference for descending order explicit.

**theme**
:   String. The user's preferred theme. This is not used by The Arsse itself, but clients may use this metadatum in protocols which expose it.

**page_size**
:   Integer. The user's preferred page size when listing articles. This is not used by The Arsse itself, but clients may use this metadatum in protocols which expose it.

**shortcuts**
:   Boolean. Whether to enable keyboard shortcuts. This is not used by The Arsse itself, but clients may use this metadatum in protocols which expose it.

**gestures**
:   Boolean. Whether to enable touch gestures. This is not used by The Arsse itself, but clients may use this metadatum in protocols which expose it.

**reading_time**
:   Boolean. Whether to calculate and display the estimated reading time for articles. Currently The Arsse does not calculate reading time, so changing this will likely have no effect.

**stylesheet**
:   String. A user CSS stylesheet. This is not used by The Arsse itself, but clients may use this metadatum in protocols which expose it.

# EXAMPLES

- Add an administrator to the database with an explicit password

      $ arsse user add --admin alice "Curiouser and curiouser!"

- Add a regular user to the database with a random password

      $ arsse user add "Bob the Builder"
      bLS!$_UUZ!iN2i_!^IC6

- Make Bob the Builder an administrator

      $ arsse user set "Bob the Builder" admin true

- Disable Alice's account by clearing her password

      $ arsse user unset-pass alice

- Move all of Foobar's newsfeeds to the root folder

      $ arsse export foobar -f | arsse import -r foobar

- Fail to log in as Alice

      $ arsse user auth alice "Oh, dear!"
      Authentication failed
      $ echo $?
      1

# REPORTING BUGS

Any bugs found in The Arsse may be reported on the Web at [https://code.mensbeam.com/MensBeam/arsse](). Reports may also be directed to the authors (below) by e-mail.

# AUTHORS

J. King\
[https://jkingweb.ca/]()

Dustin Wilson\
[https://dustinwilson.com/]()
