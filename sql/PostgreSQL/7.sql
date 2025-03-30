-- SPDX-License-Identifier: MIT
-- Copyright 2017 J. King, Dustin Wilson et al.
-- See LICENSE and AUTHORS files for details

-- Create a temporary table mapping old article IDs to new article IDs per-user.
-- Any articles which have only one subscription will be unchanged, which will
-- limit the amount of disruption
create temp table arsse_articles_map(
    article bigint not null,
    subscription bigint not null,
    id bigserial primary key
);
select setval('arsse_articles_map_id_seq', (select max(id) from arsse_articles));
insert into arsse_articles_map(article, subscription)
    select 
        a.id as article, 
        s.id as subscription
    from arsse_articles as a join arsse_subscriptions as s using(feed)
    where feed in (
        select feed from (select feed, count(*) as count from arsse_subscriptions group by feed) as c where c.count > 1
    )
    order by a.id, s.id;
insert into arsse_articles_map(article, subscription, id)
    select 
        a.id as article, 
        s.id as subscription,
        a.id as id
    from arsse_articles as a join arsse_subscriptions as s using(feed)
    where feed in (
        select feed from (select feed, count(*) as count from arsse_subscriptions group by feed) as c where c.count = 1
    );

-- Add any new columns required for the articles table
alter table arsse_articles add column subscription bigint references arsse_subscriptions(id) on delete cascade on update cascade;
alter table arsse_articles add column read smallint not null default 0;
alter table arsse_articles add column starred smallint not null default 0;
alter table arsse_articles add column hidden smallint not null default 0;
alter table arsse_articles add column touched smallint not null default 0;
alter table arsse_articles add column marked timestamp(0) without time zone;
alter table arsse_articles add column note text collate "und-x-icu" not null default '';

-- Populate the articles table with new information; this either inserts or updates in-place
with new_data as (
    select
        i.id,
        a.feed,
        i.subscription,
        coalesce(m.read,0) as read,
        coalesce(m.starred,0) as starred,
        coalesce(m.hidden,0) as hidden,
        a.published,
        a.edited,
        a.modified,
        m.modified as marked,
        a.url,
        a.title,
        a.author,
        a.guid,
        a.url_title_hash,
        a.url_content_hash,
        a.title_content_hash,
        coalesce(m.note,'') as note
    from arsse_articles_map as i
    left join arsse_articles as a on a.id = i.article
    left join arsse_marks as m on a.id = m.article and m.subscription = i.subscription
)
insert into arsse_articles(id,feed,subscription,read,starred,hidden,published,edited,modified,marked,url,title,author,guid,url_title_hash,url_content_hash,title_content_hash,note)
    select * from new_data
on conflict (id) do update set (subscription,read,starred,hidden,marked,note) = (
    select subscription, read, starred, hidden, marked, note from new_data where id = excluded.id
);
-- set the sequence number appropriately
select setval('arsse_articles_id_seq', (select max(id) from arsse_articles));

-- Next create the subsidiary table to hold article contents
create table arsse_article_contents(
-- contents of articles, which is typically large text
    id bigint primary key references arsse_articles(id) on delete cascade on update cascade,
    content text collate "und-x-icu"
);
insert into arsse_article_contents
    select
        m.id,
        case when s.scrape = 0 then a.content else coalesce(a.content_scraped, a.content) end
    from arsse_articles_map as m
    left join arsse_articles as a on a.id = m.article
    left join arsse_subscriptions as s on s.id = m.subscription;

-- Drop the two content columns from the article table as they are no longer needed
alter table arsse_articles drop column content_scraped;
alter table arsse_articles drop column content;

-- Create one edition for each renumbered article
insert into arsse_editions(article, modified)
    select 
        m.id, e.modified
    from arsse_editions as e
    join arsse_articles_map as m using(article)
    where m.id <> article
    order by m.id, modified;

-- Create enclures for renumbered articles
insert into arsse_enclosures(article, url, type)
    select 
        m.id, url, type
    from arsse_articles_map as m
    join arsse_enclosures as e on m.article = e.article
    where m.id <> m.article;

-- Create categories for renumbered articles
insert into arsse_categories(article, name)
    select 
        m.id, name
    from arsse_articles_map as m
    join arsse_categories as c on m.article = c.article
    where m.id <> m.article;

-- Create label associations for renumbered articles
insert into arsse_label_members
    select
        label, m.id, subscription, assigned, l.modified
    from arsse_articles_map as m
    join arsse_label_members as l using(article, subscription)
    where m.id <> m.article;

-- Drop the subscription column from the label members table as it is no longer needed (there is now a direct link between articles and subscriptions)
alter table arsse_label_members drop column subscription;

-- Clean up the articles table: delete obsolete rows, add necessary constraints on new columns which could not be satisfied before inserting information, and drop the obsolete feed column
delete from arsse_articles where id in (select article from arsse_articles_map where id <> article);
delete from arsse_articles where subscription is null;
alter table arsse_articles alter column subscription set not null;
alter table arsse_articles drop column feed;

-- Add feed-related columns to the subscriptions table
alter table arsse_subscriptions add column url text;
alter table arsse_subscriptions add column feed_title text collate "und-x-icu";
alter table arsse_subscriptions add column etag text not null default '';
alter table arsse_subscriptions add column last_mod timestamp(0) without time zone;
alter table arsse_subscriptions add column next_fetch timestamp(0) without time zone;
alter table arsse_subscriptions add column updated timestamp(0) without time zone;
alter table arsse_subscriptions add column source text;
alter table arsse_subscriptions add column err_count bigint not null default 0;
alter table arsse_subscriptions add column err_msg text collate "und-x-icu";
alter table arsse_subscriptions add column size bigint not null default 0;
alter table arsse_subscriptions add column icon bigint references arsse_icons(id) on delete set null;
alter table arsse_subscriptions add column deleted smallint not null default 0;

-- Populate the new columns
update arsse_subscriptions as s set 
    url = f.url,
    feed_title = f.title,
    last_mod = f.modified,
    etag = f.etag,
    next_fetch = f.next_fetch,
    source = f.source,
    updated = f.updated,
    err_count = f.err_count,
    err_msg = f.err_msg,
    size = f.size,
    icon = f.icon
from arsse_feeds as f
where s.feed = f.id;

-- Clean up the subscriptions table: add necessary constraints on new columns which could not be satisfied before inserting information, and drop the now obsolete feed column
alter table arsse_subscriptions alter column url set not null;
alter table arsse_subscriptions add unique(owner,url);
alter table arsse_subscriptions drop column feed;

-- Add new columns to the subscriptions table
alter table arsse_subscriptions add column user_agent text;
alter table arsse_subscriptions add column cookie text;

-- Delete unneeded table
drop table arsse_articles_map;
drop table arsse_marks;
drop table arsse_feeds;

-- set version marker
update arsse_meta set value = '8' where "key" = 'schema_version';
