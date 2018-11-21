-- SPDX-License-Identifier: MIT
-- Copyright 2017 J. King, Dustin Wilson et al.
-- See LICENSE and AUTHORS files for details

-- metadata
create table arsse_meta(
    key text primary key,
    value text
);

-- users
create table arsse_users(
    id text primary key,
    password text,
    name text,
    avatar_type text,
    avatar_data bytea,
    admin smallint default 0,
    rights bigint not null default 0
);

-- extra user metadata
create table arsse_users_meta(
    owner text not null references arsse_users(id) on delete cascade on update cascade,
    key text not null,
    value text,
    primary key(owner,key)
);

-- NextCloud News folders and TT-RSS categories
create table arsse_folders(
    id bigserial primary key,
    owner text not null references arsse_users(id) on delete cascade on update cascade,
    parent bigint references arsse_folders(id) on delete cascade,
    name text not null,
    modified timestamp(0) with time zone not null default CURRENT_TIMESTAMP,                                                       --
    unique(owner,name,parent)
);

-- newsfeeds, deduplicated
create table arsse_feeds(
    id bigserial primary key,
    url text not null,
    title text,
    favicon text,
    source text,
    updated timestamp(0) with time zone,
    modified timestamp(0) with time zone,
    next_fetch timestamp(0) with time zone,
    orphaned timestamp(0) with time zone,
    etag text not null default '',
    err_count bigint not null default 0,
    err_msg text,
    username text not null default '',
    password text not null default '',
    size bigint not null default 0,
    scrape smallint not null default 0,
    unique(url,username,password)
);

-- users' subscriptions to newsfeeds, with settings
create table arsse_subscriptions(
    id bigserial primary key,
    owner text not null references arsse_users(id) on delete cascade on update cascade,
    feed bigint not null references arsse_feeds(id) on delete cascade,
    added timestamp(0) with time zone not null default CURRENT_TIMESTAMP,
    modified timestamp(0) with time zone not null default CURRENT_TIMESTAMP,
    title text,
    order_type smallint not null default 0,
    pinned smallint not null default 0,
    folder bigint references arsse_folders(id) on delete cascade,
    unique(owner,feed)
);

-- entries in newsfeeds
create table arsse_articles(
    id bigserial primary key,
    feed bigint not null references arsse_feeds(id) on delete cascade,
    url text,
    title text,
    author text,
    published timestamp(0) with time zone,
    edited timestamp(0) with time zone,
    modified timestamp(0) with time zone not null default CURRENT_TIMESTAMP,
    content text,
    guid text,
    url_title_hash text not null,
    url_content_hash text not null,
    title_content_hash text not null
);

-- enclosures associated with articles
create table arsse_enclosures(
    article bigint not null references arsse_articles(id) on delete cascade,
    url text,
    type text
);

-- users' actions on newsfeed entries
create table arsse_marks(
    article bigint not null references arsse_articles(id) on delete cascade,
    subscription bigint not null references arsse_subscriptions(id) on delete cascade on update cascade,
    read smallint not null default 0,
    starred smallint not null default 0,
    modified timestamp(0) with time zone not null default CURRENT_TIMESTAMP,
    primary key(article,subscription)
);

-- IDs for specific editions of articles (required for at least NextCloud News)
create table arsse_editions(
    id bigserial primary key,
    article bigint not null references arsse_articles(id) on delete cascade,
    modified timestamp(0) with time zone not null default CURRENT_TIMESTAMP
);

-- author categories associated with newsfeed entries
create table arsse_categories(
    article bigint not null references arsse_articles(id) on delete cascade,
    name text
);

-- set version marker
insert into arsse_meta(key,value) values('schema_version','1');
