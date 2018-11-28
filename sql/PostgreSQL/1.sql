-- SPDX-License-Identifier: MIT
-- Copyright 2017 J. King, Dustin Wilson et al.
-- See LICENSE and AUTHORS files for details

-- Please consult the SQLite 3 schemata for commented version

create table arsse_sessions (
    id text primary key,
    created timestamp(0) without time zone not null default CURRENT_TIMESTAMP,
    expires timestamp(0) without time zone not null,
    "user" text not null references arsse_users(id) on delete cascade on update cascade
);

create table arsse_labels (
    id bigserial primary key,
    owner text not null references arsse_users(id) on delete cascade on update cascade,
    name text not null,
    modified timestamp(0) without time zone not null default CURRENT_TIMESTAMP,
    unique(owner,name)
);

create table arsse_label_members (
    label bigint not null references arsse_labels(id) on delete cascade,
    article bigint not null references arsse_articles(id) on delete cascade,
    subscription bigint not null references arsse_subscriptions(id) on delete cascade,
    assigned smallint not null default 1,
    modified timestamp(0) without time zone not null default CURRENT_TIMESTAMP,
    primary key(label,article)
);

alter table arsse_marks add column note text not null default '';

update arsse_meta set value = '2' where key = 'schema_version';
