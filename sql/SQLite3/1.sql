-- Sessions for Tiny Tiny RSS (and possibly others)
create table arsse_sessions (
    id text primary key,                                                                    -- UUID of session
    created text not null default CURRENT_TIMESTAMP,                                        -- Session start timestamp
    expires text not null,                                                                  -- Time at which session is no longer valid
    user text not null references arsse_users(id) on delete cascade on update cascade       -- user associated with the session
) without rowid;

-- User-defined article labels for Tiny Tiny RSS
create table arsse_labels (
    id integer primary key,                                                                 -- numeric ID
    owner text not null references arsse_users(id) on delete cascade on update cascade,     -- owning user
    name text not null,                                                                     -- label text
    modified text not null default CURRENT_TIMESTAMP,                                       -- time at which the label was last modified
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
update arsse_meta set value = '2' where key is 'schema_version';