-- metadata
create table arsse_meta(
    key text primary key not null,                                                                          -- metadata key
    value text                                                                                              -- metadata value, serialized as a string
);

-- users
create table arsse_users(
    id text primary key not null,                                                                           -- user id
    password text,                                                                                          -- password, salted and hashed; if using external authentication this would be blank
    name text,                                                                                              -- display name
    avatar_type text,                                                                                       -- internal avatar image's MIME content type
    avatar_data blob,                                                                                       -- internal avatar image's binary data
    admin boolean default 0,                                                                                -- whether the user is a member of the special "admin" group
    rights integer not null default 0                                                                       -- temporary admin-rights marker FIXME: remove reliance on this
);

-- extra user metadata
create table arsse_users_meta(
    owner text not null references arsse_users(id) on delete cascade on update cascade,
    key text not null,
    value text,
    primary key(owner,key)
);

-- NextCloud News folders
create table arsse_folders(
    id integer primary key,                                                                                 -- sequence number
    owner text not null references arsse_users(id) on delete cascade on update cascade,                     -- owner of folder
    parent integer references arsse_folders(id) on delete cascade,                                          -- parent folder id
    name text not null,                                                                                     -- folder name
    modified datetime not null default CURRENT_TIMESTAMP,                                                   --
    unique(owner,name,parent)                                                                               -- cannot have multiple folders with the same name under the same parent for the same owner
);

-- newsfeeds, deduplicated
create table arsse_feeds(
    id integer primary key,                                                                                 -- sequence number
    url text not null,                                                                                      -- URL of feed
    title text,                                                                                             -- default title of feed
    favicon text,                                                                                           -- URL of favicon
    source text,                                                                                            -- URL of site to which the feed belongs
    updated datetime,                                                                                       -- time at which the feed was last fetched
    modified datetime,                                                                                      -- time at which the feed last actually changed
    next_fetch datetime,                                                                                    -- time at which the feed should next be fetched
    etag text not null default '',                                                                          -- HTTP ETag hash used for cache validation, changes each time the content changes
    err_count integer not null default 0,                                                                   -- count of successive times update resulted in error since last successful update
    err_msg text,                                                                                           -- last error message
    username text not null default '',                                                                      -- HTTP authentication username
    password text not null default '',                                                                      -- HTTP authentication password (this is stored in plain text)
    unique(url,username,password)                                                                           -- a URL with particular credentials should only appear once
);

-- users' subscriptions to newsfeeds, with settings
create table arsse_subscriptions(
    id integer primary key,                                                                                 -- sequence number
    owner text not null references arsse_users(id) on delete cascade on update cascade,                     -- owner of subscription
    feed integer not null references arsse_feeds(id) on delete cascade,                                     -- feed for the subscription
    added datetime not null default CURRENT_TIMESTAMP,                                                      -- time at which feed was added
    modified datetime not null default CURRENT_TIMESTAMP,                                                   -- date at which subscription properties were last modified
    title text,                                                                                             -- user-supplied title
    order_type int not null default 0,                                                                      -- NextCloud sort order
    pinned boolean not null default 0,                                                                      -- whether feed is pinned (always sorts at top)
    folder integer references arsse_folders(id) on delete cascade,                                          -- TT-RSS category (nestable); the first-level category (which acts as NextCloud folder) is joined in when needed
    unique(owner,feed)                                                                                      -- a given feed should only appear once for a given owner
);

-- entries in newsfeeds
create table arsse_articles(
    id integer primary key,                                                                                 -- sequence number
    feed integer not null references arsse_feeds(id) on delete cascade,                                     -- feed for the subscription
    url text,                                                                                               -- URL of article
    title text,                                                                                             -- article title
    author text,                                                                                            -- author's name
    published datetime,                                                                                     -- time of original publication
    edited datetime,                                                                                        -- time of last edit
    modified datetime not null default CURRENT_TIMESTAMP,                                                   -- date when article properties were last modified
    content text,                                                                                           -- content, as (X)HTML
    guid text,                                                                                              -- GUID
    url_title_hash text not null,                                                                           -- hash of URL + title; used when checking for updates and for identification if there is no guid.
    url_content_hash text not null,                                                                         -- hash of URL + content, enclosure URL, & content type; used when checking for updates and for identification if there is no guid.
    title_content_hash text not null                                                                        -- hash of title + content, enclosure URL, & content type; used when checking for updates and for identification if there is no guid.
);

-- enclosures associated with articles
create table arsse_enclosures(
    article integer not null references arsse_articles(id) on delete cascade,
    url text,
    type text
);

-- users' actions on newsfeed entries
create table arsse_marks(
    id integer primary key,
    article integer not null references arsse_articles(id) on delete cascade,
    owner text not null references arsse_users(id) on delete cascade on update cascade,
    read boolean not null default 0,
    starred boolean not null default 0,
    modified datetime not null default CURRENT_TIMESTAMP,
    unique(article,owner)
);

-- IDs for specific editions of articles (required for at least NextCloud News)
create table arsse_editions(
    id integer primary key,
    article integer not null references arsse_articles(id) on delete cascade,
    modified datetime not null default CURRENT_TIMESTAMP
);

-- author categories associated with newsfeed entries
create table arsse_categories(
    article integer not null references arsse_articles(id) on delete cascade,
    name text
);

-- set version marker
pragma user_version = 1;
insert into arsse_meta(key,value) values('schema_version','1');