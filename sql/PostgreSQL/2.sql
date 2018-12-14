-- SPDX-License-Identifier: MIT
-- Copyright 2017 J. King, Dustin Wilson et al.
-- See LICENSE and AUTHORS files for details

-- Please consult the SQLite 3 schemata for commented version

alter table arsse_users alter column id type text collate "und-x-icu";
alter table arsse_folders alter column name type text collate "und-x-icu";
alter table arsse_feeds alter column title type text collate "und-x-icu";
alter table arsse_subscriptions alter column title type text collate "und-x-icu";
alter table arsse_articles alter column title type text collate "und-x-icu";
alter table arsse_articles alter column author type text collate "und-x-icu";
alter table arsse_categories alter column name type text collate "und-x-icu";
alter table arsse_labels alter column name type text collate "und-x-icu";

update arsse_meta set value = '3' where key = 'schema_version';
