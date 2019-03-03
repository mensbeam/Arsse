-- SPDX-License-Identifier: MIT
-- Copyright 2017 J. King, Dustin Wilson et al.
-- See LICENSE and AUTHORS files for details

-- Make the database WAL-journalled; this is persitent
PRAGMA journal_mode = wal;

create table arsse_meta(
-- application metadata
    key text primary key not null,                                                                          -- metadata key
    value text                                                                                              -- metadata value, serialized as a string
);

create table arsse_users(
-- users
    id text primary key not null,                                                                           -- user id
    password text,                                                                                          -- password, salted and hashed; if using external authentication this would be blank
    name text,                                                                                              -- display name
    avatar_type text,                                                                                       -- internal avatar image's MIME content type
    avatar_data blob,                                                                                       -- internal avatar image's binary data
    admin boolean default 0,                                                                                -- whether the user is a member of the special "admin" group
    rights integer not null default 0                                                                       -- temporary admin-rights marker FIXME: remove reliance on this
);

create table arsse_users_meta(
-- extra user metadata (not currently used and will be removed)
    owner text not null references arsse_users(id) on delete cascade on update cascade,
    key text not null,
    value text,
    primary key(owner,key)
);

create table arsse_folders(
-- folders, used by NextCloud News and Tiny Tiny RSS
-- feed subscriptions may belong to at most one folder;
-- in Tiny Tiny RSS folders may nest
    id integer primary key,                                                                                 -- sequence number
    owner text not null references arsse_users(id) on delete cascade on update cascade,                     -- owner of folder
    parent integer references arsse_folders(id) on delete cascade,                                          -- parent folder id
    name text not null,                                                                                     -- folder name
    modified text not null default CURRENT_TIMESTAMP,                                                       -- time at which the folder itself (not its contents) was changed; not currently used
    unique(owner,name,parent)                                                                               -- cannot have multiple folders with the same name under the same parent for the same owner
);

create table arsse_feeds(
-- newsfeeds, deduplicated
-- users have subscriptions to these feeds in another table
    id integer primary key,                                                                                 -- sequence number
    url text not null,                                                                                      -- URL of feed
    title text,                                                                                             -- default title of feed (users can set the title of their subscription to the feed)
    favicon text,                                                                                           -- URL of favicon
    source text,                                                                                            -- URL of site to which the feed belongs
    updated text,                                                                                           -- time at which the feed was last fetched
    modified text,                                                                                          -- time at which the feed last actually changed
    next_fetch text,                                                                                        -- time at which the feed should next be fetched
    orphaned text,                                                                                          -- time at which the feed last had no subscriptions
    etag text not null default '',                                                                          -- HTTP ETag hash used for cache validation, changes each time the content changes
    err_count integer not null default 0,                                                                   -- count of successive times update resulted in error since last successful update
    err_msg text,                                                                                           -- last error message
    username text not null default '',                                                                      -- HTTP authentication username
    password text not null default '',                                                                      -- HTTP authentication password (this is stored in plain text)
    size integer not null default 0,                                                                        -- number of articles in the feed at last fetch
    scrape boolean not null default 0,                                                                      -- whether to use picoFeed's content scraper with this feed
    unique(url,username,password)                                                                           -- a URL with particular credentials should only appear once
);

create table arsse_subscriptions(
-- users' subscriptions to newsfeeds, with settings
    id integer primary key,                                                                                 -- sequence number
    owner text not null references arsse_users(id) on delete cascade on update cascade,                     -- owner of subscription
    feed integer not null references arsse_feeds(id) on delete cascade,                                     -- feed for the subscription
    added text not null default CURRENT_TIMESTAMP,                                                          -- time at which feed was added
    modified text not null default CURRENT_TIMESTAMP,                                                       -- time at which subscription properties were last modified
    title text,                                                                                             -- user-supplied title
    order_type int not null default 0,                                                                      -- NextCloud sort order
    pinned boolean not null default 0,                                                                      -- whether feed is pinned (always sorts at top)
    folder integer references arsse_folders(id) on delete cascade,                                          -- TT-RSS category (nestable); the first-level category (which acts as NextCloud folder) is joined in when needed
    unique(owner,feed)                                                                                      -- a given feed should only appear once for a given owner
);

create table arsse_articles(
-- entries in newsfeeds
    id integer primary key,                                                                                 -- sequence number
    feed integer not null references arsse_feeds(id) on delete cascade,                                     -- feed for the subscription
    url text,                                                                                               -- URL of article
    title text,                                                                                             -- article title
    author text,                                                                                            -- author's name
    published text,                                                                                         -- time of original publication
    edited text,                                                                                            -- time of last edit by author
    modified text not null default CURRENT_TIMESTAMP,                                                       -- time when article was last modified in database
    content text,                                                                                           -- content, as (X)HTML
    guid text,                                                                                              -- GUID
    url_title_hash text not null,                                                                           -- hash of URL + title; used when checking for updates and for identification if there is no guid.
    url_content_hash text not null,                                                                         -- hash of URL + content, enclosure URL, & content type; used when checking for updates and for identification if there is no guid.
    title_content_hash text not null                                                                        -- hash of title + content, enclosure URL, & content type; used when checking for updates and for identification if there is no guid.
);

create table arsse_enclosures(
-- enclosures (attachments) associated with articles
    article integer not null references arsse_articles(id) on delete cascade,                               -- article to which the enclosure belongs
    url text,                                                                                               -- URL of the enclosure
    type text                                                                                               -- content-type (MIME type) of the enclosure
);

create table arsse_marks(
    article integer not null references arsse_articles(id) on delete cascade,                               -- article associated with the marks
    subscription integer not null references arsse_subscriptions(id) on delete cascade on update cascade,   -- subscription associated with the marks; the subscription in turn belongs to a user
    read boolean not null default 0,                                                                        -- whether the article has been read
    starred boolean not null default 0,                                                                     -- whether the article is starred
    modified text not null default CURRENT_TIMESTAMP,                                                       -- time at which an article was last modified by a given user
    primary key(article,subscription)                                                                       -- no more than one mark-set per article per user
);

create table arsse_editions(
-- IDs for specific editions of articles (required for at least NextCloud News)
-- every time an article is updated by its author, a new unique edition number is assigned
-- with NextCloud News this prevents users from marking as read an article which has been 
-- updated since the client state was last refreshed
    id integer primary key,                                                                                 -- sequence number
    article integer not null references arsse_articles(id) on delete cascade,                               -- the article of which this is an edition
    modified datetime not null default CURRENT_TIMESTAMP                                                    -- tiem at which the edition was modified (practically, when it was created)
);

create table arsse_categories(
-- author categories associated with newsfeed entries
-- these are not user-modifiable
    article integer not null references arsse_articles(id) on delete cascade,                               -- article associated with the category
    name text                                                                                               -- freeform name of the category
);

-- set version marker
pragma user_version = 1;
insert into arsse_meta("key",value) values('schema_version','1');
