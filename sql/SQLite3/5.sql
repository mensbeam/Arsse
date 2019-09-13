-- SPDX-License-Identifier: MIT
-- Copyright 2017 J. King, Dustin Wilson et al.
-- See LICENSE and AUTHORS files for details

-- add a column to the token table to hold arbitrary class-specific data
alter table arsse_tokens add column data text default null;

-- set version marker
pragma user_version = 6;
update arsse_meta set value = '6' where "key" = 'schema_version';
