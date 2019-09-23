-- SPDX-License-Identifier: MIT
-- Copyright 2017 J. King, Dustin Wilson et al.
-- See LICENSE and AUTHORS files for details

-- Please consult the SQLite 3 schemata for commented version

alter table arsse_tokens add column data longtext default null;

update arsse_meta set value = '6' where "key" = 'schema_version';