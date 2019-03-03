-- SPDX-License-Identifier: MIT
-- Copyright 2017 J. King, Dustin Wilson et al.
-- See LICENSE and AUTHORS files for details

-- Please consult the SQLite 3 schemata for commented version

alter table arsse_users default character set utf8mb4 collate utf8mb4_unicode_ci;
alter table arsse_folders default character set utf8mb4 collate utf8mb4_unicode_ci;
alter table arsse_feeds default character set utf8mb4 collate utf8mb4_unicode_ci;
alter table arsse_subscriptions default character set utf8mb4 collate utf8mb4_unicode_ci;
alter table arsse_articles default character set utf8mb4 collate utf8mb4_unicode_ci;
alter table arsse_categories default character set utf8mb4 collate utf8mb4_unicode_ci;
alter table arsse_labels default character set utf8mb4 collate utf8mb4_unicode_ci;

alter table arsse_users convert to character set utf8mb4 collate utf8mb4_unicode_ci;
alter table arsse_folders convert to character set utf8mb4 collate utf8mb4_unicode_ci;
alter table arsse_feeds convert to character set utf8mb4 collate utf8mb4_unicode_ci;
alter table arsse_subscriptions convert to character set utf8mb4 collate utf8mb4_unicode_ci;
alter table arsse_articles convert to character set utf8mb4 collate utf8mb4_unicode_ci;
alter table arsse_categories convert to character set utf8mb4 collate utf8mb4_unicode_ci;
alter table arsse_labels convert to character set utf8mb4 collate utf8mb4_unicode_ci;

update arsse_meta set value = '3' where "key" = 'schema_version';
