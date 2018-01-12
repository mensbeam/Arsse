-- SPDX-License-Identifier: MIT
-- Copyright 2017 J. King, Dustin Wilson et al.
-- See LICENSE and AUTHORS files for details

-- Correct collation sequences
alter table arsse_users rename to arsse_users_old;
create table arsse_users(
    id text primary key not null collate nocase,
    password text,
    name text collate nocase,
    avatar_type text,
    avatar_data blob,
    admin boolean default 0,
    rights integer not null default 0
);
insert into arsse_users(id,password,name,avatar_type,avatar_data,admin,rights) select id,password,name,avatar_type,avatar_data,admin,rights from arsse_users_old;
drop table arsse_users_old;

alter table arsse_folders rename to arsse_folders_old;
create table arsse_folders(
    id integer primary key,
    owner text not null references arsse_users(id) on delete cascade on update cascade,
    parent integer references arsse_folders(id) on delete cascade,
    name text not null collate nocase,
    modified text not null default CURRENT_TIMESTAMP,                                                       --
    unique(owner,name,parent)
);
insert into arsse_folders select * from arsse_folders_old;
drop table arsse_folders_old;

alter table arsse_feeds rename to arsse_feeds_old;
create table arsse_feeds(
    id integer primary key,
    url text not null,
    title text collate nocase,
    favicon text,
    source text,
    updated text,
    modified text,
    next_fetch text,
    orphaned text,
    etag text not null default '',
    err_count integer not null default 0,
    err_msg text,
    username text not null default '',
    password text not null default '',
    size integer not null default 0,
    scrape boolean not null default 0,
    unique(url,username,password)
);
insert into arsse_feeds select * from arsse_feeds_old;
drop table arsse_feeds_old;

alter table arsse_subscriptions rename to arsse_subscriptions_old;
create table arsse_subscriptions(
    id integer primary key,
    owner text not null references arsse_users(id) on delete cascade on update cascade,
    feed integer not null references arsse_feeds(id) on delete cascade,
    added text not null default CURRENT_TIMESTAMP,
    modified text not null default CURRENT_TIMESTAMP,
    title text collate nocase,
    order_type int not null default 0,
    pinned boolean not null default 0,
    folder integer references arsse_folders(id) on delete cascade,
    unique(owner,feed)
);
insert into arsse_subscriptions select * from arsse_subscriptions_old;
drop table arsse_subscriptions_old;

alter table arsse_articles rename to arsse_articles_old;
create table arsse_articles(
    id integer primary key,
    feed integer not null references arsse_feeds(id) on delete cascade,
    url text,
    title text collate nocase,
    author text collate nocase,
    published text,
    edited text,
    modified text not null default CURRENT_TIMESTAMP,
    content text,
    guid text,
    url_title_hash text not null,
    url_content_hash text not null,
    title_content_hash text not null
);
insert into arsse_articles select * from arsse_articles_old;
drop table arsse_articles_old;

alter table arsse_categories rename to arsse_categories_old;
create table arsse_categories(
    article integer not null references arsse_articles(id) on delete cascade,
    name text collate nocase
);
insert into arsse_categories select * from arsse_categories_old;
drop table arsse_categories_old;


alter table arsse_labels rename to arsse_labels_old;
create table arsse_labels (
    id integer primary key,
    owner text not null references arsse_users(id) on delete cascade on update cascade,
    name text not null collate nocase,
    modified text not null default CURRENT_TIMESTAMP,
    unique(owner,name)
);
insert into arsse_labels select * from arsse_labels_old;
drop table arsse_labels_old;

-- set version marker
pragma user_version = 3;
update arsse_meta set value = '3' where key = 'schema_version';