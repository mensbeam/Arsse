-- SPDX-License-Identifier: MIT
-- Copyright 2017 J. King, Dustin Wilson et al.
-- See LICENSE and AUTHORS files for details

-- Please consult the SQLite 3 schemata for commented version

-- Correct character set and collation of sessions table
alter table arsse_sessions default character set utf8mb4 collate utf8mb4_unicode_ci;
alter table arsse_sessions convert to character set utf8mb4 collate utf8mb4_unicode_ci;

-- Make integer foreign key referrers unsigned to match serial-type keys
alter table arsse_folders modify parent bigint unsigned;
alter table arsse_subscriptions modify feed bigint unsigned not null;
alter table arsse_subscriptions modify folder bigint unsigned;
alter table arsse_articles modify feed bigint unsigned not null;
alter table arsse_enclosures modify article bigint unsigned not null;
alter table arsse_marks modify article bigint unsigned not null;
alter table arsse_marks modify subscription bigint unsigned not null;
alter table arsse_editions modify article bigint unsigned not null;
alter table arsse_categories modify article bigint unsigned not null;
alter table arsse_label_members modify label bigint unsigned not null;
alter table arsse_label_members modify article bigint unsigned not null;
alter table arsse_label_members modify subscription bigint unsigned not null;
alter table arsse_tag_members modify tag bigint unsigned not null;
alter table arsse_tag_members modify subscription bigint unsigned not null;

-- Fix foreign key constraints
alter table arsse_folders add foreign key(owner) references arsse_users(id) on delete cascade on update cascade;
alter table arsse_folders add foreign key(parent) references arsse_folders(id) on delete cascade;
alter table arsse_subscriptions add foreign key(owner) references arsse_users(id) on delete cascade on update cascade;
alter table arsse_subscriptions add foreign key(feed) references arsse_feeds(id) on delete cascade;
alter table arsse_subscriptions add foreign key(folder) references arsse_folders(id) on delete cascade;
alter table arsse_articles add foreign key(feed) references arsse_feeds(id) on delete cascade;
alter table arsse_enclosures add foreign key(article) references arsse_articles(id) on delete cascade;
alter table arsse_marks add foreign key(article) references arsse_articles(id) on delete cascade;
alter table arsse_marks add foreign key(subscription) references arsse_subscriptions(id) on delete cascade;
alter table arsse_editions add foreign key(article) references arsse_articles(id) on delete cascade;
alter table arsse_categories add foreign key(article) references arsse_articles(id) on delete cascade;
alter table arsse_sessions add foreign key("user") references arsse_users(id) on delete cascade on update cascade;
alter table arsse_labels add foreign key(owner) references arsse_users(id) on delete cascade on update cascade;
alter table arsse_label_members add foreign key(label) references arsse_labels(id) on delete cascade;
alter table arsse_label_members add foreign key(article) references arsse_articles(id) on delete cascade;
alter table arsse_label_members add foreign key(subscription) references arsse_subscriptions(id) on delete cascade;
alter table arsse_tags add foreign key(owner) references arsse_users(id) on delete cascade on update cascade;
alter table arsse_tag_members add foreign key(tag) references arsse_tags(id) on delete cascade;
alter table arsse_tag_members add foreign key(subscription) references arsse_subscriptions(id) on delete cascade;
alter table arsse_tokens add foreign key("user") references arsse_users(id) on delete cascade on update cascade;

update arsse_meta set value = '6' where "key" = 'schema_version';
