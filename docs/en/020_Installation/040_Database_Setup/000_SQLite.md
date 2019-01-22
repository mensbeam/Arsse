SQLite requires very little setup. By default the database will be created at the root of The Arsse's program directory (e.g. `/usr/share/arsse/arsse.db`), but this can be changed with the [`dbSQLite3File` setting](/en/Configuring_The_Arsse#dbSQLite3File). 

Regardless of the location chosen, The Arsse **must** be able to both read from and write to the database file, as well as create files in the directory containing it. This is because SQLite also creates a write-ahead log file and a shared-memory file during operation. 
