-- Sessions for Tiny Tiny RSS (and possibly others)
create table arsse_sessions (
    id text primary key,                                                                    -- UUID of session
    created datetime not null default CURRENT_TIMESTAMP,                                    -- Session start timestamp
    expires datetime not null,                                                              -- Time at which session is no longer valid
    user text not null references arsse_users(id) on delete cascade on update cascade,      -- user associated with the session
) without rowid;

-- User-defined article labels for Tiny Tiny RSS
create table arsse_labels (
    id integer primary key,                                                                 -- numeric ID
    owner text not null references arsse_users(id) on delete cascade on update cascade,     -- owning user
    name text not null,                                                                     -- label text
    foreground text,                                                                        -- foreground (text) colour in hexdecimal RGB
    background text,                                                                        -- background colour in hexadecimal RGB
    unique(owner,name)
);

-- Labels assignments for articles
create table arsse_label_members (
    label integer not null references arsse_labels(id) on delete cascade,
    article integer not null references arsse_articles(id) on delete cascade,
    subscription integer not null references arsse_subscriptions(id) on delete cascade,     -- Subscription is included so that records are deleted when a subscription is removed
    primary key(label,article)
) without rowid;

-- set version marker
pragma user_version = 2;
insert into arsse_meta(key,value) values('schema_version','2');