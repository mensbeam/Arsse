-- SPDX-License-Identifier: MIT
-- Copyright 2017 J. King, Dustin Wilson et al.
-- See LICENSE and AUTHORS files for details

-- Please consult the SQLite 3 schemata for commented version

create table arsse_tags(
    id serial primary key,
    owner varchar(255) not null references arsse_users(id) on delete cascade on update cascade,
    name varchar(255) not null,
    modified datetime(0) not null default CURRENT_TIMESTAMP,
    unique(owner,name)
) character set utf8mb4 collate utf8mb4_unicode_ci;

create table arsse_tag_members(
    tag bigint not null references arsse_tags(id) on delete cascade,
    subscription bigint not null references arsse_subscriptions(id) on delete cascade,
    assigned boolean not null default 1,
    modified datetime(0) not null default CURRENT_TIMESTAMP,
    primary key(tag,subscription)
) character set utf8mb4 collate utf8mb4_unicode_ci;

create table arsse_tokens(
    id varchar(255) not null,
    class varchar(255) not null,
    "user" varchar(255) not null references arsse_users(id) on delete cascade on update cascade,
    created datetime(0) not null default CURRENT_TIMESTAMP,
    expires datetime(0),
    primary key(id,class)
) character set utf8mb4 collate utf8mb4_unicode_ci;

alter table arsse_users drop column name;
alter table arsse_users drop column avatar_type;
alter table arsse_users drop column avatar_data;
alter table arsse_users drop column admin;
alter table arsse_users drop column rights;

drop table arsse_users_meta;


update arsse_meta set value = '5' where "key" = 'schema_version';
