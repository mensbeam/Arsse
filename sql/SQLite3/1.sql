-- SPDX-License-Identifier: MIT
-- Copyright 2017 J. King, Dustin Wilson et al.
-- See LICENSE and AUTHORS files for details

create table arsse_sessions (
-- sessions for Tiny Tiny RSS (and possibly others)
    id text primary key,                                                                    -- UUID of session
    created text not null default CURRENT_TIMESTAMP,                                        -- Session start timestamp
    expires text not null,                                                                  -- Time at which session is no longer valid
    user text not null references arsse_users(id) on delete cascade on update cascade       -- user associated with the session
) without rowid;

create table arsse_labels (
-- user-defined article labels for Tiny Tiny RSS
    id integer primary key,                                                                 -- numeric ID
    owner text not null references arsse_users(id) on delete cascade on update cascade,     -- owning user
    name text not null,                                                                     -- label text
    modified text not null default CURRENT_TIMESTAMP,                                       -- time at which the label was last modified
    unique(owner,name)
);

create table arsse_label_members (
-- uabels assignments for articles
    label integer not null references arsse_labels(id) on delete cascade,                   -- label ID associated to an article; label IDs belong to a user
    article integer not null references arsse_articles(id) on delete cascade,               -- article associated to a label
    subscription integer not null references arsse_subscriptions(id) on delete cascade,     -- Subscription is included so that records are deleted when a subscription is removed
    assigned boolean not null default 1,                                                    -- whether the association is current, to support soft deletion
    modified text not null default CURRENT_TIMESTAMP,                                       -- time at which the association was last made or unmade
    primary key(label,article)                                                              -- only one association of a given label to a given article
) without rowid;

-- alter marks table to add Tiny Tiny RSS' notes
-- SQLite has limited ALTER TABLE support, so the table must be re-created
-- and its data re-entered; other database systems have a much simpler prodecure
alter table arsse_marks rename to arsse_marks_old;
create table arsse_marks(
-- users' actions on newsfeed entries
    article integer not null references arsse_articles(id) on delete cascade,                               -- article associated with the marks
    subscription integer not null references arsse_subscriptions(id) on delete cascade on update cascade,   -- subscription associated with the marks; the subscription in turn belongs to a user
    read boolean not null default 0,                                                                        -- whether the article has been read
    starred boolean not null default 0,                                                                     -- whether the article is starred
    modified text not null default CURRENT_TIMESTAMP,                                                       -- time at which an article was last modified by a given user
    note text not null default '',                                                                          -- Tiny Tiny RSS freeform user note
    primary key(article,subscription)                                                                       -- no more than one mark-set per article per user
);
insert into arsse_marks(article,subscription,read,starred,modified) select article,subscription,read,starred,modified from arsse_marks_old;
drop table arsse_marks_old;

-- set version marker
pragma user_version = 2;
update arsse_meta set value = '2' where key = 'schema_version';
