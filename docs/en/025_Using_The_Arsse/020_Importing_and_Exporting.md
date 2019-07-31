[TOC]

# Preface

This section describes in brief some CLI commands. Please read [the general notes on the command line interface](index) before continuing.

# Importing Newsfeeds from OPML

It's possible to import not only newsfeeds but also folders and Fever groups using OPML files. The process is simple:

```sh
sudo -u www-data php arsse.php import "user@example.com" "subscriptions.opml"
```

The importer is forgiving, but some OPML files may fail, with the reason printed out. Files are either imported in total, or not at all.

# Exporting Newsfeeds to OPML

It's possible to export not only newsfeeds but also folders and Fever groups to OPML files. The process is simple:

```sh
sudo -u www-data php arsse.php export "user@example.com" "subscriptions.opml"
```

The output might look like this:

```xml
<opml version="2.0">
    <head/>
    <body>
        <outline text="Folder">
            <outline text="Subfolder">
                <outline type="rss" text="Feed 1" xmlUrl="http://example.com/feed1"/>
            </outline>
            <outline type="rss" text="Feed 2" xmlUrl="http://example.com/feed2" category="group 1,group 2"/>
            <outline type="rss" text="Feed 3" xmlUrl="http://example.com/feed3" category="group 1"/>
        </outline>
        <outline type="rss" text="Feed 4" xmlUrl="http://example.com/feed4" category="group 2,group 3"/>
    </body>
</opml>
```

# Managing Newsfeeds via OPML

Not all protocols supported by The Arsse allow modifying newsfeeds or folders, et cetera; additionally, not all clients support these capabilities even if the protocol has the necessary features. An OPML export/import sequence with the `--replace` import option specified, however, makes any kind of modification possible. For example:

```sh
# export your newsfeeds
sudo -u www-data php arsse.php export "user@example.com" "subscriptions.opml"
# make any changes you want in your editor of choice
nano "subscriptions.opml"
# re-import the modified information
sudo -u www-data php arsse.php import "user@example.com" "subscriptions.opml" --replace
```
