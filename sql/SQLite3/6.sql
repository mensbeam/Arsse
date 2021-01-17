-- SPDX-License-Identifier: MIT
-- Copyright 2017 J. King, Dustin Wilson et al.
-- See LICENSE and AUTHORS files for details

-- Add a column to the token table to hold arbitrary class-specific data
-- This is a speculative addition to support OAuth login in the future
alter table arsse_tokens add column data text default null;

-- Add columns to subscriptions to store "keep" and "block" filtering rules from Miniflux, 
-- as well as a column to mark articles as hidden for users
alter table arsse_subscriptions add column keep_rule text default null;
alter table arsse_subscriptions add column block_rule text default null;
alter table arsse_marks add column hidden boolean not null default 0;

-- Add numeric identifier and admin columns to the users table
create table arsse_users_new(
-- users
    id text primary key not null collate nocase,    -- user id
    password text,                                  -- password, salted and hashed; if using external authentication this would be blank
    num integer unique not null,                    -- numeric identfier used by Miniflux
    admin boolean not null default 0                -- Whether the user is an administrator
) without rowid;
create temp table arsse_users_existing(
    id text not null,
    num integer primary key
);
insert into arsse_users_existing(id) select id from arsse_users;
insert into arsse_users_new(id, password, num) 
    select id, password, num 
    from arsse_users 
    join arsse_users_existing using(id);
drop table arsse_users;
drop table arsse_users_existing;
alter table arsse_users_new rename to arsse_users;

-- Add a table for other user metadata
create table arsse_user_meta(
    -- Metadata for users
    -- It is up to individual applications (i.e. the client protocols) to cooperate with names and types
    owner text not null references arsse_users(id) on delete cascade on update cascade,     -- the user to whom the metadata belongs
    key text not null,                                                                      -- metadata key
    modified text not null default CURRENT_TIMESTAMP,                                       -- time at which the metadata was last changed
    value text,                                                                             -- metadata value
    primary key(owner,key)
) without rowid;

-- Add a "scrape" column for subscriptions and copy any existing scraping
alter table arsse_subscriptions add column scrape boolean not null default 0;
update arsse_subscriptions set scrape = 1 where feed in (select id from arsse_feeds where scrape = 1);

-- Add a column for scraped article content, and re-order some columns
create table arsse_articles_new(
-- entries in newsfeeds
    id integer primary key,                                                 -- sequence number
    feed integer not null references arsse_feeds(id) on delete cascade,     -- feed for the subscription
    url text,                                                               -- URL of article
    title text collate nocase,                                              -- article title
    author text collate nocase,                                             -- author's name
    published text,                                                         -- time of original publication
    edited text,                                                            -- time of last edit by author
    modified text not null default CURRENT_TIMESTAMP,                       -- time when article was last modified in database
    guid text,                                                              -- GUID
    url_title_hash text not null,                                           -- hash of URL + title; used when checking for updates and for identification if there is no guid.
    url_content_hash text not null,                                         -- hash of URL + content, enclosure URL, & content type; used when checking for updates and for identification if there is no guid.
    title_content_hash text not null,                                       -- hash of title + content, enclosure URL, & content type; used when checking for updates and for identification if there is no guid.
    content_scraped text,                                                   -- scraped content, as HTML
    content text                                                            -- content, as HTML
);
insert into arsse_articles_new select id, feed, url, title, author, published, edited, modified, guid, url_title_hash, url_content_hash, title_content_hash, null, content from arsse_articles;
drop table arsse_articles;
alter table arsse_articles_new rename to arsse_articles;

-- Add a separate table for feed icons and replace their URLs in the feeds table with their IDs
-- Also remove the "scrape" column of the feeds table, which was never an advertised feature
create table arsse_icons(
    -- Icons associated with feeds
    -- At a minimum the URL of the icon must be known, but its content may be missing
    id integer primary key,         -- the identifier for the icon
    url text unique not null,       -- the URL of the icon
    modified text,                  -- Last-Modified date, for caching
    etag text not null default '',  -- ETag, for caching
    next_fetch text,                -- The date at which cached data should be considered stale
    orphaned text,                  -- time at which the icon last had no feeds associated with it
    type text,                      -- the Content-Type of the icon, if known
    data blob                       -- the binary data of the icon itself
);
insert into arsse_icons(url) select distinct favicon from arsse_feeds where favicon is not null and favicon <> '';
create table arsse_feeds_new(
-- newsfeeds, deduplicated
-- users have subscriptions to these feeds in another table
    id integer primary key,                                        -- sequence number
    url text not null,                                             -- URL of feed
    title text collate nocase,                                     -- default title of feed (users can set the title of their subscription to the feed)
    source text,                                                   -- URL of site to which the feed belongs
    updated text,                                                  -- time at which the feed was last fetched
    modified text,                                                 -- time at which the feed last actually changed
    next_fetch text,                                               -- time at which the feed should next be fetched
    orphaned text,                                                 -- time at which the feed last had no subscriptions
    etag text not null default '',                                 -- HTTP ETag hash used for cache validation, changes each time the content changes
    err_count integer not null default 0,                          -- count of successive times update resulted in error since last successful update
    err_msg text,                                                  -- last error message
    username text not null default '',                             -- HTTP authentication username
    password text not null default '',                             -- HTTP authentication password (this is stored in plain text)
    size integer not null default 0,                               -- number of articles in the feed at last fetch
    icon integer references arsse_icons(id) on delete set null,    -- numeric identifier of any associated icon
    unique(url,username,password)                                  -- a URL with particular credentials should only appear once
);
insert into arsse_feeds_new 
    select f.id, f.url, title, source, updated, f.modified, f.next_fetch, f.orphaned, f.etag, err_count, err_msg, username, password, size, i.id
    from arsse_feeds as f left join arsse_icons as i on f.favicon = i.url;
drop table arsse_feeds;
alter table arsse_feeds_new rename to arsse_feeds;

-- set version marker
pragma user_version = 7;
update arsse_meta set value = '7' where "key" = 'schema_version';
