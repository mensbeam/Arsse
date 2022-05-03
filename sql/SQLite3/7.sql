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
    id integer primary key,
    subscription integer not null references arsse_subscriptions(id) on delete cascade on update cascade,
    read boolean not null default 0,
    starred boolean not null default 0,
    hidden boolean not null default 0,
    published text,
    edited text,
    modified text not null default CURRENT_TIMESTAMP,
    marked text,
    url text,
    title text collate nocase,
    author text collate nocase,
    guid text,
    url_title_hash text not null,
    url_content_hash text not null,
    title_content_hash text not null,
    note text not null default ''
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
    id integer primary key references arsse_articles(id) on delete cascade on update cascade,
    content text
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
    article integer
);
create table arsse_editions_new(
    id integer primary key,
    article integer references arsse_articles(id) on delete cascade on update cascade
);
insert into arsse_editions_temp values(1,1);
delete from arsse_editions_temp;
update sqlite_sequence set seq = (select max(id) from arsse_editions) where name = 'arsse_editions_temp';
insert into arsse_editions_temp(article) select id from arsse_articles_map where id = article;
insert into arsse_editions_temp(id, article)
    select id, article from arsse_editions where article in (select article from arsse_editions_temp where id <> article);
insert into arsse_editions_new select * from arsse_editions_temp;

-- Create a new enclosures table
create table arsse_enclosures_new(
    article integer not null references arsse_articles(id) on delete cascade,
    url text,
    type text
);
insert into arsse_enclosures_new 
    select
        i.id,
        e.url,
        e.type
    from arsse_articles_map as i 
    join arsse_enclosures as e on e.article = i.article;  

-- Fix up the label members table

-- Create a new subscriptions table which combines the feeds table

-- Fix up the tag members table

-- Fix up the icons table

-- Delete the old tables and rename the new ones
