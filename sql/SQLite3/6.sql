-- SPDX-License-Identifier: MIT
-- Copyright 2017 J. King, Dustin Wilson et al.
-- See LICENSE and AUTHORS files for details

-- Add multiple columns to the users table
-- In particular this adds a numeric identifier for each user, which Miniflux requires
create table arsse_users_new(
-- users
    id text primary key not null collate nocase,    -- user id
    password text,                                  -- password, salted and hashed; if using external authentication this would be blank
    num integer unique not null,                    -- numeric identfier used by Miniflux
    admin boolean not null default 0,               -- Whether the user is an administrator
    lang text,                                      -- The user's chosen language code e.g. 'en', 'fr-ca'; null uses the system default
    tz text not null default 'Etc/UTC',             -- The user's chosen time zone, in zoneinfo format
    sort_asc boolean not null default 0             -- Whether the user prefers to sort articles in ascending order
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

-- Add a separate table for feed icons and replace their URLs in the feeds table with their IDs
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
    scrape boolean not null default 0,                             -- whether to use picoFeed's content scraper with this feed
    icon integer references arsse_icons(id) on delete set null,    -- numeric identifier of any associated icon
    unique(url,username,password)                                  -- a URL with particular credentials should only appear once
);
insert into arsse_feeds_new 
    select f.id, f.url, title, source, updated, f.modified, f.next_fetch, f.orphaned, f.etag, err_count, err_msg, username, password, size, scrape, i.id
    from arsse_feeds as f left join arsse_icons as i on f.favicon = i.url;
drop table arsse_feeds;
alter table arsse_feeds_new rename to arsse_feeds;

-- set version marker
pragma user_version = 7;
update arsse_meta set value = '7' where "key" = 'schema_version';
