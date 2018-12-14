-- SPDX-License-Identifier: MIT
-- Copyright 2017 J. King, Dustin Wilson et al.
-- See LICENSE and AUTHORS files for details

-- Correct collation sequences in order for various things to sort case-insensitively
-- SQLite has limited ALTER TABLE support, so the tables must be re-created
-- and their data re-entered; other database systems have a much simpler prodecure
alter table arsse_users rename to arsse_users_old;
create table arsse_users(
-- users
    id text primary key not null collate nocase,                                                            -- user id
    password text,                                                                                          -- password, salted and hashed; if using external authentication this would be blank
    name text collate nocase,                                                                               -- display name
    avatar_type text,                                                                                       -- internal avatar image's MIME content type
    avatar_data blob,                                                                                       -- internal avatar image's binary data
    admin boolean default 0,                                                                                -- whether the user is a member of the special "admin" group
    rights integer not null default 0                                                                       -- temporary admin-rights marker FIXME: remove reliance on this
);
insert into arsse_users(id,password,name,avatar_type,avatar_data,admin,rights) select id,password,name,avatar_type,avatar_data,admin,rights from arsse_users_old;
drop table arsse_users_old;

alter table arsse_folders rename to arsse_folders_old;
create table arsse_folders(
-- folders, used by NextCloud News and Tiny Tiny RSS
-- feed subscriptions may belong to at most one folder;
-- in Tiny Tiny RSS folders may nest
    id integer primary key,                                                                                 -- sequence number
    owner text not null references arsse_users(id) on delete cascade on update cascade,                     -- owner of folder
    parent integer references arsse_folders(id) on delete cascade,                                          -- parent folder id
    name text not null collate nocase,                                                                      -- folder name
    modified text not null default CURRENT_TIMESTAMP,                                                       -- time at which the folder itself (not its contents) was changed; not currently used
    unique(owner,name,parent)                                                                               -- cannot have multiple folders with the same name under the same parent for the same owner
);
insert into arsse_folders select * from arsse_folders_old;
drop table arsse_folders_old;

alter table arsse_feeds rename to arsse_feeds_old;
create table arsse_feeds(
-- newsfeeds, deduplicated
-- users have subscriptions to these feeds in another table
    id integer primary key,                                                                                 -- sequence number
    url text not null,                                                                                      -- URL of feed
    title text collate nocase,                                                                              -- default title of feed (users can set the title of their subscription to the feed)
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
insert into arsse_feeds select * from arsse_feeds_old;
drop table arsse_feeds_old;

alter table arsse_subscriptions rename to arsse_subscriptions_old;
create table arsse_subscriptions(
-- users' subscriptions to newsfeeds, with settings
    id integer primary key,                                                                                 -- sequence number
    owner text not null references arsse_users(id) on delete cascade on update cascade,                     -- owner of subscription
    feed integer not null references arsse_feeds(id) on delete cascade,                                     -- feed for the subscription
    added text not null default CURRENT_TIMESTAMP,                                                          -- time at which feed was added
    modified text not null default CURRENT_TIMESTAMP,                                                       -- time at which subscription properties were last modified
    title text collate nocase,                                                                              -- user-supplied title
    order_type int not null default 0,                                                                      -- NextCloud sort order
    pinned boolean not null default 0,                                                                      -- whether feed is pinned (always sorts at top)
    folder integer references arsse_folders(id) on delete cascade,                                          -- TT-RSS category (nestable); the first-level category (which acts as NextCloud folder) is joined in when needed
    unique(owner,feed)                                                                                      -- a given feed should only appear once for a given owner
);
insert into arsse_subscriptions select * from arsse_subscriptions_old;
drop table arsse_subscriptions_old;

alter table arsse_articles rename to arsse_articles_old;
create table arsse_articles(
-- entries in newsfeeds
    id integer primary key,                                                                                 -- sequence number
    feed integer not null references arsse_feeds(id) on delete cascade,                                     -- feed for the subscription
    url text,                                                                                               -- URL of article
    title text collate nocase,                                                                              -- article title
    author text collate nocase,                                                                             -- author's name
    published text,                                                                                         -- time of original publication
    edited text,                                                                                            -- time of last edit by author
    modified text not null default CURRENT_TIMESTAMP,                                                       -- time when article was last modified in database
    content text,                                                                                           -- content, as (X)HTML
    guid text,                                                                                              -- GUID
    url_title_hash text not null,                                                                           -- hash of URL + title; used when checking for updates and for identification if there is no guid.
    url_content_hash text not null,                                                                         -- hash of URL + content, enclosure URL, & content type; used when checking for updates and for identification if there is no guid.
    title_content_hash text not null                                                                        -- hash of title + content, enclosure URL, & content type; used when checking for updates and for identification if there is no guid.
);
insert into arsse_articles select * from arsse_articles_old;
drop table arsse_articles_old;

alter table arsse_categories rename to arsse_categories_old;
create table arsse_categories(
-- author categories associated with newsfeed entries
-- these are not user-modifiable
    article integer not null references arsse_articles(id) on delete cascade,                               -- article associated with the category
    name text collate nocase                                                                                -- freeform name of the category
);
insert into arsse_categories select * from arsse_categories_old;
drop table arsse_categories_old;


alter table arsse_labels rename to arsse_labels_old;
create table arsse_labels (
-- user-defined article labels for Tiny Tiny RSS
    id integer primary key,                                                                 -- numeric ID
    owner text not null references arsse_users(id) on delete cascade on update cascade,     -- owning user
    name text not null collate nocase,                                                      -- label text
    modified text not null default CURRENT_TIMESTAMP,                                       -- time at which the label was last modified
    unique(owner,name)
);
insert into arsse_labels select * from arsse_labels_old;
drop table arsse_labels_old;

-- set version marker
pragma user_version = 3;
update arsse_meta set value = '3' where key = 'schema_version';
