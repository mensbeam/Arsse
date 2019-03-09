-- SPDX-License-Identifier: MIT
-- Copyright 2017 J. King, Dustin Wilson et al.
-- See LICENSE and AUTHORS files for details

create table arsse_tags(
-- user-defined subscription tags
    id integer primary key,                                                                 -- numeric ID
    owner text not null references arsse_users(id) on delete cascade on update cascade,     -- owning user
    name text not null collate nocase,                                                      -- tag text
    modified text not null default CURRENT_TIMESTAMP,                                       -- time at which the tag was last modified
    unique(owner,name)
);

create table arsse_tag_members(
-- tag assignments for subscriptions
    tag integer not null references arsse_tags(id) on delete cascade,                       -- tag ID associated to a subscription
    subscription integer not null references arsse_subscriptions(id) on delete cascade,     -- Subscription associated to a tag
    assigned boolean not null default 1,                                                    -- whether the association is current, to support soft deletion
    modified text not null default CURRENT_TIMESTAMP,                                       -- time at which the association was last made or unmade
    primary key(tag,subscription)                                                           -- only one association of a given tag to a given subscription
) without rowid;

create table arsse_tokens(
-- access tokens that are managed by the protocol handler and may optionally expire
    id text,                                                                                -- token identifier
    class text not null,                                                                    -- symbolic name of the protocol handler managing the token
    user text not null references arsse_users(id) on delete cascade on update cascade,      -- user associated with the token
    created text not null default CURRENT_TIMESTAMP,                                        -- creation timestamp
    expires text,                                                                           -- time at which token is no longer valid
    primary key(id,class)                                                                   -- tokens must be unique for their class
) without rowid;


-- clean up the user tables to remove unused stuff
-- if any of the removed things are implemented in future, necessary structures will be added back in at that time

create table arsse_users_new(
-- users
    id text primary key not null collate nocase,                                                            -- user id
    password text                                                                                           -- password, salted and hashed; if using external authentication this would be blank
) without rowid;
insert into arsse_users_new select id,password from arsse_users;
drop table arsse_users;
alter table arsse_users_new rename to arsse_users;

drop table arsse_users_meta;


-- use WITHOUT ROWID tables when possible; this is an SQLite-specific change

create table arsse_meta_new(
-- application metadata
    key text primary key not null,                                                                          -- metadata key
    value text                                                                                              -- metadata value, serialized as a string
) without rowid;
insert into arsse_meta_new select * from arsse_meta;
drop table arsse_meta;
alter table arsse_meta_new rename to arsse_meta;

create table arsse_marks_new(
-- users' actions on newsfeed entries
    article integer not null references arsse_articles(id) on delete cascade,                               -- article associated with the marks
    subscription integer not null references arsse_subscriptions(id) on delete cascade on update cascade,   -- subscription associated with the marks; the subscription in turn belongs to a user
    read boolean not null default 0,                                                                        -- whether the article has been read
    starred boolean not null default 0,                                                                     -- whether the article is starred
    modified text,                                                                                          -- time at which an article was last modified by a given user
    note text not null default '',                                                                          -- Tiny Tiny RSS freeform user note
    touched boolean not null default 0,                                                                     -- used to indicate a record has been modified during the course of some transactions
    primary key(article,subscription)                                                                       -- no more than one mark-set per article per user
) without rowid;
insert into arsse_marks_new select * from arsse_marks;
drop table arsse_marks;
alter table arsse_marks_new rename to arsse_marks;


-- set version marker
pragma user_version = 5;
update arsse_meta set value = '5' where "key" = 'schema_version';
