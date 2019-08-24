# About

<dl>
    <dt>Supported since</dt>
        <dd>0.1.0</dd>
    <dt>dbDriver identifier</dt>
        <dd>sqlite3</dd>
    <dt>Minimum version</dt>
        <dd>3.8.3</dd>
    <dt>Configuration</dt>
        <dd><a href="../Configuration.html#page_Database-settings">General</a>, <a href="../Configuration.html#page_Database-settings-specific-to-SQLite-3">Specific</a></dd>
</dl>

SQLite requires very little set-up. By default the database will be created at the root of The Arsse's program directory (e.g. `/usr/share/arsse/arsse.db`), but this can be changed with the [`dbSQLite3File` setting](/en/Getting_Started/Configuration#page_dbSQLite3File). 

Regardless of the location chosen, The Arsse **must** be able to both read from and write to the database file, as well as create files in its directory. This is because SQLite also creates a write-ahead log file and a shared-memory file during operation.
