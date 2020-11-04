-- SPDX-License-Identifier: MIT
-- Copyright 2017 J. King, Dustin Wilson et al.
-- See LICENSE and AUTHORS files for details

-- Please consult the SQLite 3 schemata for commented version

alter table arsse_users add column num bigint unique;
alter table arsse_users add column admin smallint not null default 0;
alter table arsse_users add column lang text;
alter table arsse_users add column tz text not null default 'Etc/UTC';
alter table arsse_users add column sort_asc smallint not null default 0;
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

create table arsse_icons(
    id bigserial primary key,
    url text unique not null,
    modified timestamp(0) without time zone,
    etag text not null default '',
    next_fetch timestamp(0) without time zone,
    orphaned timestamp(0) without time zone,
    type text,
    data bytea
);
insert into arsse_icons(url) select distinct favicon from arsse_feeds where favicon is not null;
alter table arsse_feeds add column icon bigint references arsse_icons(id) on delete set null;
update arsse_feeds as f set icon = i.id from arsse_icons as i where f.favicon = i.url;
alter table arsse_feeds drop column favicon;

update arsse_meta set value = '7' where "key" = 'schema_version';
