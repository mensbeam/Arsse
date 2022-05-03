-- SPDX-License-Identifier: MIT
-- Copyright 2017 J. King, Dustin Wilson et al.
-- See LICENSE and AUTHORS files for details

-- Create a temporary table mapping old article IDs to new article IDs per-user.
-- Any articles which have only one subscription will be unchanged, which will
-- limit the amount of disruption
create table arsse_articles_map(
    article int not null,
    subscription int not null,
    owner text not null,
    id integer primary key autoincrement
);
insert into arsse_articles_map(article, subscription, owner) values(1, 1, '');
delete from arsse_articles_map;
update sqlite_sequence set seq = (select max(id) from arsse_articles) where name = 'arsse_articles_map';
insert into arsse_articles_map(article, subscription)
    select 
        a.id as article, 
        s.id as subscription,
        s,owner as owner
    from arsse_articles as a cross join arsse_subscriptions as s using(feed)
    where feed in (
        select feed from (select feed, count(*) as count from arsse_subscriptions group by feed) as c where c.count > 1
    );
insert into arsse_articles_map(article, subscription, owner, id)
    select 
        a.id as article, 
        s.id as subscription,
        s.owner as owner,
        a.id as id
    from arsse_articles as a cross join arsse_subscriptions as s using(feed)
    where feed in (
        select feed from (select feed, count(*) as count from arsse_subscriptions group by feed) as c where c.count = 1
    );

-- Create a new articles table which combines the marks table but does not include content
create table arsse_articles_new(
-- metadata for entries in newsfeeds, including user state
    id integer primary key,                                                                                 -- sequence number
    subscription integer not null references arsse_subscriptions(id) on delete cascade on update cascade,   -- associated subscription
    read boolean not null default 0,                                                                        -- whether the article has been read
    starred boolean not null default 0,                                                                     -- whether the article is starred
    hidden boolean not null default 0,                                                                      -- whether the article should be excluded from selection by default
    published text,                                                                                         -- time of original publication
    edited text,                                                                                            -- time of last edit by author
    modified text not null default CURRENT_TIMESTAMP,                                                       -- time when article was last modified in database pursuant to an authorial edit
    marked text,                                                                                            -- time at which an article was last modified by the user
    url text,                                                                                               -- URL of article
    title text collate nocase,                                                                              -- article title
    author text collate nocase,                                                                             -- author's name
    guid text,                                                                                              -- a nominally globally unique identifier for the article, from the feed
    url_title_hash text not null,                                                                           -- hash of URL + title; used when checking for updates and for identification if there is no guid
    url_content_hash text not null,                                                                         -- hash of URL + content + enclosure URL + enclosure content type; used when checking for updates and for identification if there is no guid
    title_content_hash text not null,                                                                       -- hash of title + content + enclosure URL + enclosure content type; used when checking for updates and for identification if there is no guid
    note text not null default ''                                                                           -- Tiny Tiny RSS freeform user note
);
insert into arsse_articles_new
    select
        i.id,
        i.subscription,
        m.read,
        m.starred,
        m.hidden,
        a.published,
        a.edited,
        a.modified,
        m.modified,
        a.url,
        a.title,
        a.author,
        a.guid,
        a.url_title_hash,
        a_url_content_hash,
        a.title_content_hash,
        m.note
    from arsse_articles_map as i
    left join arsse_articles as a on a.id = i.article
    left join arsse_marks as m on a.id = m.article;

-- Create a new table to hold article content
create table arsse_article_contents(
-- contents of articles, which is typically large text
    id integer primary key references arsse_articles(id) on delete cascade on update cascade,   -- reference to the article ID
    content text                                                                                -- the contents
);
insert into arsse_article_contents
    select
        i.id,
        a.content
    from arsse_articles_map as i
    left join arsse_articles as a on a.id = i.article;

-- Create a new table for editions
create table arsse_editions_temp(
    id integer primary key autoincrement,
    article integer,
    modified datetime not null default CURRENT_TIMESTAMP
);
create table arsse_editions_new(
-- IDs for specific editions of articles (required for at least Nextcloud News)
-- every time an article is updated by its author, a new unique edition number is assigned
-- with Nextcloud News this prevents users from marking as read an article which has been 
-- updated since the client state was last refreshed
    id integer primary key,                                                     -- sequence number
    article integer not null references arsse_articles(id) on delete cascade,   -- the article of which this is an edition
    modified datetime not null default CURRENT_TIMESTAMP                        -- time at which the edition was modified (practically, when it was created)
);
insert into arsse_editions_temp values(1,1);
delete from arsse_editions_temp;
update sqlite_sequence set seq = (select max(id) from arsse_editions) where name = 'arsse_editions_temp';
insert into arsse_editions_temp(article) select id from arsse_articles_map where id = article;
insert into arsse_editions_temp(id, article, modified)
    select id, article, modified from arsse_editions where article in (select article from arsse_editions_temp where id <> article);
insert into arsse_editions_new select * from arsse_editions_temp;

-- Create a new enclosures table
create table arsse_enclosures_new(
-- enclosures (attachments) associated with articles
    article integer not null references arsse_articles(id) on delete cascade,   -- article to which the enclosure belongs
    url text,                                                                   -- URL of the enclosure
    type text                                                                   -- content-type (MIME type) of the enclosure
);
insert into arsse_enclosures_new 
    select
        i.id,
        e.url,
        e.type
    from arsse_articles_map as i 
    join arsse_enclosures as e on e.article = i.article;  

-- Create a new label members table
create table arsse_label_members_new(
-- label assignments for articles
    label integer not null references arsse_labels(id) on delete cascade,                   -- label ID associated to an article; label IDs belong to a user
    article integer not null references arsse_articles(id) on delete cascade,               -- article associated to a label
    subscription integer not null references arsse_subscriptions(id) on delete cascade,     -- Subscription is included so that records are deleted when a subscription is removed
    assigned boolean not null default 1,                                                    -- whether the association is current, to support soft deletion
    modified text not null default CURRENT_TIMESTAMP,                                       -- time at which the association was last made or unmade
    primary key(label,article)                                                              -- only one association of a given label to a given article
) without rowid;

-- Create a new subscriptions table which combines the feeds table

-- Fix up the tag members table

-- Fix up the icons table

-- Delete the old tables and rename the new ones
