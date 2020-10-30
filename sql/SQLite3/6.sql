-- SPDX-License-Identifier: MIT
-- Copyright 2017 J. King, Dustin Wilson et al.
-- See LICENSE and AUTHORS files for details

-- Add multiple columns to the users table
-- In particular this adds a numeric identifier for each user, which Miniflux requires
create table arsse_users_new(
-- users
    id text primary key not null collate nocase,    -- user id
    password text,                                  -- password, salted and hashed; if using external authentication this would be blank
    num integer unique not null,                    -- numeric identfier used by Miniflux
    admin boolean not null default 0,               -- Whether the user is an administrator
    lang text,                                      -- The user's chosen language code e.g. 'en', 'fr-ca'; null uses the system default
    tz text not null default 'Etc/UTC',             -- The user's chosen time zone, in zoneinfo format
    sort_asc boolean not null default 0             -- Whether the user prefers to sort articles in ascending order
) without rowid;
create temp table arsse_users_existing(
    id text not null,
    num integer primary key
);
insert into arsse_users_existing(id) select id from arsse_users;
insert into arsse_users_new(id, password, num) 
    select id, password, num 
    from arsse_users 
    join arsse_users_existing using(id);
drop table arsse_users;
drop table arsse_users_existing;
alter table arsse_users_new rename to arsse_users;

-- set version marker
pragma user_version = 7;
update arsse_meta set value = '7' where "key" = 'schema_version';
