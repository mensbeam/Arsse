-- SPDX-License-Identifier: MIT
-- Copyright 2017 J. King, Dustin Wilson et al.
-- See LICENSE and AUTHORS files for details

-- Create a temporary table mapping old article IDs to new article IDs per-user.
-- This will have the result of every article ID being new, which will make the initial sync painful,
-- but it will avoid potential weird behaviour
create table arsse_articles_map(
    article int not null,
    subscription int not null,
    id integer primary key autoincrement
);
insert into arsse_articles_map(article, subscription) values(1,1);
delete from arsse_articles_map;
update sqlite_sequence set seq = (select max(id) from arsse_articles) where name = 'arsse_articles_map';
insert into arsse_articles_map(article, subscription)
    select arsse_articles.id as article, arsse_subscriptions.id as subscription from arsse_articles cross join arsse_subscriptions using(feed);

-- Perform a similar reset for editions
create table arsse_editions_temp(
    id integer primary key autoincrement,
    article integer
);
insert into arsse_editions_temp values(1,1);
delete from arsse_editions_temp;
update sqlite_sequence set seq = (select max(id) from arsse_editions) where name = 'arsse_editions_temp';
insert into arsse_editions_temp(article) select id from arsse_articles_map;

-- Create a new articles table which combines the marks table

-- Create a new table to hold article content

-- Fix up the enclosures table

-- Fix up the label members table

-- Rebuild the editions table

-- Create a new subscriptions table which combines the feeds table

-- Fix up the tag members table

-- Fix up the icons table

-- Delete the old tables and rename the new ones
