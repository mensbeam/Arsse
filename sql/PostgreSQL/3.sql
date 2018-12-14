-- SPDX-License-Identifier: MIT
-- Copyright 2017 J. King, Dustin Wilson et al.
-- See LICENSE and AUTHORS files for details

-- Please consult the SQLite 3 schemata for commented version

alter table arsse_marks alter column modified drop default;
alter table arsse_marks alter column modified drop not null;
alter table arsse_marks add column touched smallint not null default 0;

update arsse_meta set value = '4' where key = 'schema_version';
