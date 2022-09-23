-- SPDX-License-Identifier: MIT
-- Copyright 2017 J. King, Dustin Wilson et al.
-- See LICENSE and AUTHORS files for details

-- Create a temporary table mapping old article IDs to new article IDs per-user.
-- Any articles which have only one subscription will be unchanged, which will
-- limit the amount of disruption
create table arsse_articles_map(
    article bigint unsigned not null,
    subscription bigint unsigned not null,
    id serial
);
-- alter table arsse_articles_map auto_increment = (select max(id) + 1 from arsse_articles);
insert into arsse_articles_map 
    select 0, 0, max(id) from arsse_articles;
delete from arsse_articles_map;
insert into arsse_articles_map(article, subscription)
    select 
        a.id as article, 
        s.id as subscription
    from arsse_articles as a join arsse_subscriptions as s using(feed)
    where feed in (
        select feed from (select feed, count(*) as count from arsse_subscriptions group by feed) as c where c.count > 1
    );
insert into arsse_articles_map(article, subscription, id)
    select 
        a.id as article, 
        s.id as subscription,
        a.id as id
    from arsse_articles as a join arsse_subscriptions as s using(feed)
    where feed in (
        select feed from (select feed, count(*) as count from arsse_subscriptions group by feed) as c where c.count = 1
    );

-- First create the subsidiary table to hold article contents
create table arsse_article_contents(
-- contents of articles, which is typically large text
    id bigint unsigned primary key,
    content longtext,
    foreign key(id) references arsse_articles(id) on delete cascade on update cascade
) character set utf8mb4 collate utf8mb4_unicode_ci;
insert into arsse_article_contents
    select
        m.id,
        coalesce(a.content_scraped, a.content)
    from arsse_articles_map as m
    left join arsse_articles as a on a.id = m.article;

-- Drop the two content columns from the article table as they are no longer needed
alter table arsse_articles drop column content;
alter table arsse_articles drop column content_scraped;

-- Add any new columns required for the articles table
alter table arsse_articles add column subscription bigint unsigned;
alter table arsse_articles add column "read" smallint not null default 0;
alter table arsse_articles add column starred smallint not null default 0;
alter table arsse_articles add column hidden smallint not null default 0;
alter table arsse_articles add column marked datetime(0);
alter table arsse_articles add column note longtext;

-- Populate the articles table with new information; this either inserts or updates in-place
insert into arsse_articles(id,feed,subscription,"read",starred,hidden,published,edited,modified,marked,url,title,author,guid,url_title_hash,url_content_hash,title_content_hash,note)
    select
        i.id,
        a.feed,
        i.subscription,
        coalesce(m."read",0),
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
    left join arsse_marks as m on a.id = m.article
on duplicate key update
    subscription = values(subscription),
    "read" = values("read"),
    starred = values(starred),
    hidden = values(hidden),
    marked = values(marked),
    note = values(note);


-- Create one edition for each renumbered article
insert into arsse_editions(article) select id from arsse_articles_map where id <> article;

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

-- Drop the subscription column from the label members table as it is no longer needed (there is now a direct link between articles and subscriptions)
alter table arsse_label_members drop foreign key arsse_label_members_ibfk_3;
alter table arsse_label_members drop column subscription;

-- Create label associations for renumbered articles
insert into arsse_label_members
    select
        label, m.id, assigned, l.modified
    from arsse_articles_map as m
    join arsse_label_members as l using(article)
    where m.id <> m.article;

-- Clean up the articles table: delete obsolete rows, add necessary constraints on new columns which could not be satisfied before inserting information, and drop the obsolete feed column
delete from arsse_articles where id in (select article from arsse_articles_map where id <> article);
alter table arsse_articles modify subscription bigint unsigned not null;
alter table arsse_articles add foreign key(subscription) references arsse_subscriptions(id) on delete cascade on update cascade;
alter table arsse_articles drop foreign key arsse_articles_ibfk_1;
alter table arsse_articles drop column feed;

-- Add feed-related columns to the subscriptions table
alter table arsse_subscriptions add column url longtext;
alter table arsse_subscriptions add column feed_title longtext;
alter table arsse_subscriptions add column etag varchar(255) not null default '';
alter table arsse_subscriptions add column last_mod datetime(0);
alter table arsse_subscriptions add column next_fetch datetime(0);
alter table arsse_subscriptions add column updated datetime(0);
alter table arsse_subscriptions add column source longtext;
alter table arsse_subscriptions add column err_count bigint unsigned not null default 0;
alter table arsse_subscriptions add column err_msg longtext;
alter table arsse_subscriptions add column size bigint unsigned not null default 0;
alter table arsse_subscriptions add column icon bigint unsigned;

-- Populate the new columns
update arsse_subscriptions as s, arsse_feeds as f set 
    s.url = f.url,
    s.feed_title = f.title,
    s.last_mod = f.modified,
    s.etag = f.etag,
    s.next_fetch = f.next_fetch,
    s.source = f.source,
    s.updated = f.updated,
    s.err_count = f.err_count,
    s.err_msg = f.err_msg,
    s.size = f.size,
    s.icon = f.icon
where s.feed = f.id;

-- Clean up the subscriptions table: add necessary constraints on new columns which could not be satisfied before inserting information, and drop the now obsolete feed column
alter table arsse_subscriptions modify url longtext not null;
alter table arsse_subscriptions add foreign key(icon) references arsse_icons(id) on delete set null;
alter table arsse_subscriptions add unique(owner,url(255));
alter table arsse_subscriptions drop constraint arsse_subscriptions_ibfk_2;
alter table arsse_subscriptions drop constraint owner;
alter table arsse_subscriptions drop column feed;

-- Delete unneeded table
drop table arsse_articles_map;
drop table arsse_marks;
drop table arsse_feeds;

-- set version marker
update arsse_meta set value = '8' where "key" = 'schema_version';
