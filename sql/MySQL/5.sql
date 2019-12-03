-- SPDX-License-Identifier: MIT
-- Copyright 2017 J. King, Dustin Wilson et al.
-- See LICENSE and AUTHORS files for details

-- Please consult the SQLite 3 schemata for commented version

-- Drop unnecessary indexes
drop index id on arsse_folders;
drop index id on arsse_feeds;
drop index id on arsse_subscriptions;
drop index id on arsse_articles;
drop index id on arsse_editions;
drop index id on arsse_labels;
drop index id on arsse_tags;

-- Ensure tables use dynamic row-format; these should be no-ops for most installations
alter table arsse_meta engine=InnoDB row_format=dynamic;
alter table arsse_users engine=InnoDB row_format=dynamic;
alter table arsse_feeds engine=InnoDB row_format=dynamic;
alter table arsse_folders engine=InnoDB row_format=dynamic;
alter table arsse_subscriptions engine=InnoDB row_format=dynamic;
alter table arsse_articles engine=InnoDB row_format=dynamic;
alter table arsse_marks engine=InnoDB row_format=dynamic;
alter table arsse_labels engine=InnoDB row_format=dynamic;
alter table arsse_label_members engine=InnoDB row_format=dynamic;
alter table arsse_tags engine=InnoDB row_format=dynamic;
alter table arsse_tag_members engine=InnoDB row_format=dynamic;
alter table arsse_editions engine=InnoDB row_format=dynamic;
alter table arsse_categories engine=InnoDB row_format=dynamic;
alter table arsse_tokens engine=InnoDB row_format=dynamic;
alter table arsse_sessions engine=InnoDB row_format=dynamic;

-- Correct character set and collation of sessions table
alter table arsse_sessions default character set utf8mb4 collate utf8mb4_unicode_ci;
alter table arsse_sessions convert to character set utf8mb4 collate utf8mb4_unicode_ci;

-- Ensure referential integrity
with valid as (select id from arsse_folders)
    delete from arsse_folders where
        owner not in (select id from arsse_users) or
        (parent is not null and parent not in (select id from valid));
delete from arsse_subscriptions where
    owner not in (select id from arsse_users) or
    feed not in (select id from arsse_feeds) or
    (folder is not null and folder not in (select id from arsse_folders));
delete from arsse_articles where feed not in (select id from arsse_feeds);
delete from arsse_enclosures where article not in (select id from arsse_articles);
delete from arsse_marks where
    article not in (select id from arsse_articles) or
    subscription not in (select id from arsse_subscriptions);
delete from arsse_editions where article not in (select id from arsse_articles);
delete from arsse_categories where article not in (select id from arsse_articles);
delete from arsse_sessions where "user" not in (select id from arsse_users);
delete from arsse_labels where owner not in (select id from arsse_users);
delete from arsse_label_members where
    label not in (select id from arsse_labels) or
    article not in (select id from arsse_articles) or
    subscription not in (select id from arsse_subscriptions);
delete from arsse_tags where owner not in (select id from arsse_users);
delete from arsse_tag_members where
    tag not in (select id from arsse_tags) or
    subscription not in (select id from arsse_subscriptions);
delete from arsse_tokens where "user" not in (select id from arsse_users);

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

-- Use longtext columns whenever possible
alter table arsse_users modify password longtext;
alter table arsse_sessions drop primary key;
alter table arsse_sessions modify id longtext;
alter table arsse_sessions add primary key(id(768));
alter table arsse_tokens drop primary key;
alter table arsse_tokens modify id longtext;
alter table arsse_tokens add primary key(id(512), class);
alter table arsse_feeds modify title longtext;
alter table arsse_feeds modify favicon longtext;
alter table arsse_feeds modify source longtext;
alter table arsse_feeds modify etag longtext;
alter table arsse_feeds modify err_msg longtext;
alter table arsse_articles modify url longtext;
alter table arsse_articles modify title longtext;
alter table arsse_articles modify author longtext;
alter table arsse_articles modify guid longtext;
alter table arsse_enclosures modify url longtext;
alter table arsse_enclosures modify type longtext;
alter table arsse_categories modify name longtext;
drop index owner on arsse_folders;
alter table arsse_folders modify name longtext not null;
alter table arsse_folders add unique index(owner, name(255), parent);
drop index owner on arsse_tags;
alter table arsse_tags modify name longtext not null;
alter table arsse_tags add unique index(owner, name(255));
drop index owner on arsse_labels;
alter table arsse_labels modify name longtext not null;
alter table arsse_labels add unique index(owner, name(255));


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
