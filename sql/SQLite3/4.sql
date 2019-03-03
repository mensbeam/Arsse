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

-- set version marker
pragma user_version = 5;
update arsse_meta set value = '5' where "key" = 'schema_version';
