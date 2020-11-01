-- SPDX-License-Identifier: MIT
-- Copyright 2017 J. King, Dustin Wilson et al.
-- See LICENSE and AUTHORS files for details

alter table arsse_users add column num bigint unsigned unique;
alter table arsse_users add column admin boolean not null default 0;
alter table arsse_users add column lang longtext;
alter table arsse_users add column tz varchar(44) not null default 'Etc/UTC';
alter table arsse_users add column sort_asc boolean not null default 0;
create temporary table arsse_users_existing(
    id text not null,
    num serial primary key
) character set utf8mb4 collate utf8mb4_unicode_ci;
insert into arsse_users_existing(id) select id from arsse_users;
update arsse_users as u, arsse_users_existing as n
    set u.num = n.num
where u.id = n.id;
drop table arsse_users_existing;
alter table arsse_users modify num bigint unsigned not null;

update arsse_meta set value = '7' where "key" = 'schema_version';
