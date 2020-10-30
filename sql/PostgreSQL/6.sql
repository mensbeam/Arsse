-- SPDX-License-Identifier: MIT
-- Copyright 2017 J. King, Dustin Wilson et al.
-- See LICENSE and AUTHORS files for details

alter table arsse_users add column num bigint unique;
alter table arsse_users add column admin smallint not null default 0;
alter table arsse_users add column lang text;
alter table arsse_users add column tz text not null default 'Etc/UTC';
alter table arsse_users add column soort_asc smallint not null default 0;
create temp table arsse_users_existing(
    id text not null,
    num bigserial
);
insert into arsse_users_existing(id) select id from arsse_users;
update arsse_users as u
    set num = e.num
from arsse_users_existing as e
where u.id = e.id;
drop table arsse_users_existing;
alter table arsse_users alter column num set not null;

update arsse_meta set value = '7' where "key" = 'schema_version';
