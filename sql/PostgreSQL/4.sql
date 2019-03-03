-- SPDX-License-Identifier: MIT
-- Copyright 2017 J. King, Dustin Wilson et al.
-- See LICENSE and AUTHORS files for details

-- Please consult the SQLite 3 schemata for commented version

create table arsse_tags(
    id bigserial primary key,
    owner text not null references arsse_users(id) on delete cascade on update cascade,
    name text not null collate "und-x-icu",
    modified timestamp(0) without time zone not null default CURRENT_TIMESTAMP,
    unique(owner,name)
);

create table arsse_tag_members(
    tag bigint not null references arsse_tags(id) on delete cascade,
    subscription bigint not null references arsse_subscriptions(id) on delete cascade,
    assigned smallint not null default 1,
    modified timestamp(0) without time zone not null default CURRENT_TIMESTAMP,
    primary key(tag,subscription)
);

update arsse_meta set value = '5' where "key" = 'schema_version';
