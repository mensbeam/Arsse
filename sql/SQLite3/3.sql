-- SPDX-License-Identifier: MIT
-- Copyright 2017 J. King, Dustin Wilson et al.
-- See LICENSE and AUTHORS files for details

-- allow marks to initially have a null date due to changes in how marks are first created
-- and also add a "touched" column to aid in tracking changes during the course of some transactions
alter table arsse_marks rename to arsse_marks_old;
create table arsse_marks(
-- users' actions on newsfeed entries
    article integer not null references arsse_articles(id) on delete cascade,                               -- article associated with the marks
    subscription integer not null references arsse_subscriptions(id) on delete cascade on update cascade,   -- subscription associated with the marks; the subscription in turn belongs to a user
    read boolean not null default 0,                                                                        -- whether the article has been read
    starred boolean not null default 0,                                                                     -- whether the article is starred
    modified text,                                                                                          -- time at which an article was last modified by a given user
    note text not null default '',                                                                          -- Tiny Tiny RSS freeform user note
    touched boolean not null default 0,                                                                     -- used to indicate a record has been modified during the course of some transactions
    primary key(article,subscription)                                                                       -- no more than one mark-set per article per user
);
insert into arsse_marks select article,subscription,read,starred,modified,note,0 from arsse_marks_old;
drop table arsse_marks_old;

-- reindex anything which uses the nocase collation sequence; it has been replaced with a Unicode collation
reindex nocase;

-- set version marker
pragma user_version = 4;
update arsse_meta set value = '4' where key = 'schema_version';
