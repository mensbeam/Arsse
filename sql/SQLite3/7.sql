-- SPDX-License-Identifier: MIT
-- Copyright 2017 J. King, Dustin Wilson et al.
-- See LICENSE and AUTHORS files for details

-- Create a temporary table mapping old article IDs to new article IDs per-user.
-- Any articles which have only one subscription will be unchanged, which will
-- limit the amount of disruption
create temp table arsse_articles_map(
    article int not null,
    subscription int not null,
    id integer primary key autoincrement
);
replace into temp.sqlite_sequence(name,seq) select 'arsse_articles_map', max(id) from arsse_articles;
insert into arsse_articles_map(article, subscription)
    select
       a.id as article,
       s.id as subscription
    from arsse_articles as a join arsse_subscriptions as s using(feed)
    where feed in (
        select feed from (select feed, count(*) as count from arsse_subscriptions group by feed) as c where count > 1
    );
insert into arsse_articles_map(article, subscription, id)
    select
       a.id as article,
       s.id as subscription,
        a.id as id
    from arsse_articles as a join arsse_subscriptions as s using(feed)
    where feed in (
        select feed from (select feed, count(*) as count from arsse_subscriptions group by feed) as c where count = 1
    );

-- Create a new articles table which combines the marks table but does not include content
create table arsse_articles_new(
-- metadata for entries in newsfeeds, including user state
    id integer primary key,                                                                                 -- sequence number
    subscription integer not null references arsse_subscriptions(id) on delete cascade on update cascade,   -- associated subscription
    read int not null default 0,                                                                            -- whether the article has been read
    starred int not null default 0,                                                                         -- whether the article is starred
    hidden int not null default 0,                                                                          -- whether the article should be excluded from selection by default
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
        coalesce(m.read,0),
        coalesce(m.starred,0),
        coalesce(m.hidden,0),
        a.published,
        a.edited,
        a.modified,
        m.modified,
        a.url,
        a.title,
        a.author,
        a.guid,
        a.url_title_hash,
        a.url_content_hash,
        a.title_content_hash,
        coalesce(m.note,'')
    from arsse_articles_map as i
    left join arsse_articles as a on a.id = i.article
    left join arsse_marks as m on a.id = m.article and m.subscription = i.subscription;

-- Create a new table to hold article content
create table arsse_article_contents(
-- contents of articles, which is typically large text
    id integer primary key references arsse_articles(id) on delete cascade on update cascade,   -- reference to the article ID
    content text                                                                                -- the contents
);
insert into arsse_article_contents
    select
        m.id,
        case when s.scrape = 0 then a.content else coalesce(a.content_scraped, a.content) end
    from arsse_articles_map as m
    left join arsse_articles as a on a.id = m.article
    left join arsse_subscriptions as s on s.id = m.subscription;

-- Create one edition for each renumbered article, and delete any editions for obsolete articles
insert into arsse_editions(article) select id from arsse_articles_map where id <> article;
delete from arsse_editions where article in (select article from arsse_articles_map where id <> article);

-- Create enclures for renumbered articles and delete obsolete enclosures
insert into arsse_enclosures(article, url, type)
    select
       m.id, url, type
    from arsse_articles_map as m
    join arsse_enclosures as e on m.article = e.article
    where m.id <> m.article;
delete from arsse_enclosures where article in (select article from arsse_articles_map where id <> article);

-- Create categories for renumbered articles and delete obsolete categories
insert into arsse_categories(article, name)
    select
       m.id, name
    from arsse_articles_map as m
    join arsse_categories as c on m.article = c.article
    where m.id <> m.article;
delete from arsse_categories where article in (select article from arsse_articles_map where id <> article);

-- Create a new label-associations table which omits the subscription column and populate it with new data
create table arsse_label_members_new(
-- label assignments for articles
    label integer not null references arsse_labels(id) on delete cascade,                   -- label ID associated to an article; label IDs belong to a user
    article integer not null references arsse_articles(id) on delete cascade,               -- article associated to a label
    assigned int not null default 1,                                                        -- whether the association is current, to support soft deletion
    modified text not null default CURRENT_TIMESTAMP,                                       -- time at which the association was last made or unmade
    primary key(label,article)                                                              -- only one association of a given label to a given article
) without rowid;
insert into arsse_label_members_new
    select
        label, m.id, assigned, l.modified
    from arsse_articles_map as m
    join arsse_label_members as l using(article);

-- Create a new subscriptions table which combines the feeds table
create table arsse_subscriptions_new(
-- users' subscriptions to newsfeeds, with settings
    id integer primary key,                                                                 -- sequence number
    owner text not null references arsse_users(id) on delete cascade on update cascade,     -- owner of subscription
    url text not null,                                                                      -- URL of feed
    feed_title text collate nocase,                                                         -- feed title
    title text collate nocase,                                                              -- user-supplied title, which overrides the feed title when set
    folder integer references arsse_folders(id) on delete cascade,                          -- TT-RSS category (nestable); the first-level category (which acts as Nextcloud folder) is joined in when needed
    last_mod text,                                                                          -- time at which the feed last actually changed at the foreign host
    etag text not null default '',                                                          -- HTTP ETag hash used for cache validation, changes each time the content changes
    next_fetch text,                                                                        -- time at which the feed should next be fetched
    added text not null default CURRENT_TIMESTAMP,                                          -- time at which feed was added
    source text,                                                                            -- URL of site to which the feed belongs
    updated text,                                                                           -- time at which the feed was last fetched
    err_count integer not null default 0,                                                   -- count of successive times update resulted in error since last successful update
    err_msg text,                                                                           -- last error message
    size integer not null default 0,                                                        -- number of articles in the feed at last fetch
    icon integer references arsse_icons(id) on delete set null,                             -- numeric identifier of any associated icon
    modified text not null default CURRENT_TIMESTAMP,                                       -- time at which subscription properties were last modified by the user
    order_type int not null default 0,                                                      -- Nextcloud sort order
    pinned int not null default 0,                                                          -- whether feed is pinned (always sorts at top)
    scrape int not null default 0,                                                          -- whether the user has requested scraping content from source articles
    keep_rule text,                                                                         -- Regular expression the subscription's articles must match to avoid being hidden
    block_rule text,                                                                        -- Regular expression the subscription's articles must not match to avoid being hidden
    unique(owner,url)                                                                       -- a URL with particular credentials should only appear once
);
insert into arsse_subscriptions_new
    select
        s.id,
        s.owner,
        f.url,
        f.title,
        s.title,
        s.folder,
        f.modified,
        f.etag,
        f.next_fetch,
        s.added,
        f.source,
        f.updated,
        f.err_count,
        f.err_msg,
        f.size,
        f.icon,
        s.modified,
        s.order_type,
        s.pinned,
        s.scrape,
        s.keep_rule,
        s.block_rule
    from arsse_subscriptions as s left join arsse_feeds as f on s.feed = f.id;

-- Delete the old tables and rename the new ones
drop table arsse_label_members;
drop table arsse_subscriptions;
drop table arsse_feeds;
drop table arsse_articles;
drop table arsse_marks;
drop table arsse_articles_map;
alter table arsse_subscriptions_new rename to arsse_subscriptions;
alter table arsse_articles_new rename to arsse_articles;
alter table arsse_label_members_new rename to arsse_label_members;

-- set version marker
pragma user_version = 8;
update arsse_meta set value = '8' where "key" = 'schema_version';
