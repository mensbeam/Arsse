# Preface

This section details a few administrative tasks which may need to be performed after installing The Arsse. As no Web-based administrative interface is included, these tasks are generally performed via command line interface.

Though this section describes some commands briefly, complete documentation of The Arsse's command line interface is not included in this manual. Documentation for CLI commands can instead be viewed with the CLI itself by executing `arsse --help`.

# A Note on Command Invocation

Particularly if using an SQLite database, it's important that administrative commands be executed as the same user who owns The Arsse's files. To that end our releases include an `arsse` executable which drops privileges when executed as root. Commands in this section assume this executable is being used.
