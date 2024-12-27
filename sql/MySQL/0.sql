-- SPDX-License-Identifier: MIT
-- Copyright 2017 J. King, Dustin Wilson et al.
-- See LICENSE and AUTHORS files for details

-- Please consult the SQLite 3 schemata for commented version

create table arsse_meta(
    "key" varchar(255) primary key,
    value longtext
) character set utf8mb4;

create table arsse_users(
    id varchar(255) primary key,
    password varchar(255),
    name varchar(255),
    avatar_type varchar(255),
    avatar_data longblob,
    admin boolean default 0,
    rights bigint not null default 0
) character set utf8mb4;

create table arsse_users_meta(
    owner varchar(255) not null,
    "key" varchar(255) not null,
    value varchar(255),
    primary key(owner,"key")
) character set utf8mb4;

create table arsse_folders(
    id serial primary key,
    owner varchar(255) not null,
    parent bigint,
    name varchar(255) not null,
    modified datetime(0) not null default CURRENT_TIMESTAMP,                                                       --
    unique(owner,name,parent)
) character set utf8mb4;

create table arsse_feeds(
    id serial primary key,
    url varchar(255) not null,
    title varchar(255),
    favicon varchar(255),
    source varchar(255),
    updated datetime(0),
    modified datetime(0),
    next_fetch datetime(0),
    orphaned datetime(0),
    etag varchar(255) not null default '',
    err_count bigint not null default 0,
    err_msg varchar(255),
    username varchar(255) not null default '',
    password varchar(255) not null default '',
    size bigint not null default 0,
    scrape boolean not null default 0,
    unique(url,username,password)
) character set utf8mb4;

create table arsse_subscriptions(
    id serial primary key,
    owner varchar(255) not null,
    feed bigint not null,
    added datetime(0) not null default CURRENT_TIMESTAMP,
    modified datetime(0) not null default CURRENT_TIMESTAMP,
    title varchar(255),
    order_type boolean not null default 0,
    pinned boolean not null default 0,
    folder bigint,
    unique(owner,feed)
) character set utf8mb4;

create table arsse_articles(
    id serial primary key,
    feed bigint not null,
    url varchar(255),
    title varchar(255),
    author varchar(255),
    published datetime(0),
    edited datetime(0),
    modified datetime(0) not null default CURRENT_TIMESTAMP,
    content longtext,
    guid varchar(255),
    url_title_hash varchar(255) not null,
    url_content_hash varchar(255) not null,
    title_content_hash varchar(255) not null
) character set utf8mb4;

create table arsse_enclosures(
    article bigint not null,
    url varchar(255),
    type varchar(255)
) character set utf8mb4;

create table arsse_marks(
    article bigint not null,
    subscription bigint not null,
    "read" boolean not null default 0,
    starred boolean not null default 0,
    modified datetime(0) not null default CURRENT_TIMESTAMP,
    primary key(article,subscription)
) character set utf8mb4;

create table arsse_editions(
    id serial primary key,
    article bigint not null,
    modified datetime(0) not null default CURRENT_TIMESTAMP
) character set utf8mb4;

create table arsse_categories(
    article bigint not null,
    name varchar(255)
) character set utf8mb4;

insert into arsse_meta("key",value) values('schema_version','1');
