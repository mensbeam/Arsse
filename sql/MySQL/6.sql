-- SPDX-License-Identifier: MIT
-- Copyright 2017 J. King, Dustin Wilson et al.
-- See LICENSE and AUTHORS files for details

-- Please consult the SQLite 3 schemata for commented version

alter table arsse_tokens add column data longtext default null;

alter table arsse_users add column num bigint unsigned unique;
alter table arsse_users add column admin boolean not null default 0;
create temporary table arsse_users_existing(
    id text not null,
    num serial primary key
) character set utf8mb4 collate utf8mb4_unicode_ci;
insert into arsse_users_existing(id) select id from arsse_users;
update arsse_users as u, arsse_users_existing as n
    set u.num = n.num
where u.id = n.id;
drop table arsse_users_existing;
alter table arsse_users modify num bigint unsigned not null;

create table arsse_user_meta(
    owner varchar(255) not null,
    "key" varchar(255) not null,
    value longtext,
    foreign key(owner) references arsse_users(id) on delete cascade on update cascade,
    primary key(owner,"key")
) character set utf8mb4 collate utf8mb4_unicode_ci;

create table arsse_icons(
    id serial primary key,
    url varchar(767) unique not null,
    modified datetime(0),
    etag varchar(255) not null default '',
    next_fetch datetime(0),
    orphaned datetime(0),
    type text,
    data longblob
) character set utf8mb4 collate utf8mb4_unicode_ci;
insert into arsse_icons(url) select distinct favicon from arsse_feeds where favicon is not null and favicon <> '';
alter table arsse_feeds add column icon bigint unsigned;
alter table arsse_feeds add constraint foreign key (icon) references arsse_icons(id) on delete set null;
update arsse_feeds as f, arsse_icons as i set f.icon = i.id where f.favicon = i.url;
alter table arsse_feeds drop column favicon;

update arsse_meta set value = '7' where "key" = 'schema_version';
