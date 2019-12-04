-- SPDX-License-Identifier: MIT
-- Copyright 2017 J. King, Dustin Wilson et al.
-- See LICENSE and AUTHORS files for details

-- This schema version strictly applies fixes for MySQL, 
-- hence this file is functionally empty

update arsse_meta set value = '6' where "key" = 'schema_version';
