-- SPDX-License-Identifier: MIT
-- Copyright 2017 J. King, Dustin Wilson et al.
-- See LICENSE and AUTHORS files for details

-- Please consult the SQLite 3 schemata for commented version

create table arsse_sessions (
    id varchar(255) primary key,
    created datetime(0) not null default CURRENT_TIMESTAMP,
    expires datetime(0) not null,
    "user" varchar(255) not null
) character set utf8mb4;

create table arsse_labels (
    id serial primary key,
    owner varchar(255) not null,
    name varchar(255) not null,
    modified datetime(0) not null default CURRENT_TIMESTAMP,
    unique(owner,name)
) character set utf8mb4;

create table arsse_label_members (
    label bigint not null,
    article bigint not null,
    subscription bigint not null,
    assigned boolean not null default 1,
    modified datetime(0) not null default CURRENT_TIMESTAMP,
    primary key(label,article)
) character set utf8mb4;

alter table arsse_marks add column note longtext;

update arsse_meta set value = '2' where "key" = 'schema_version';
