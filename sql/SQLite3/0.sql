-- settings
create table newssync_settings(
    key varchar(255) primary key not null,                                                                  -- setting key
    value varchar(255),                                                                                     -- setting value, serialized as a string
    type varchar(255) not null check(
        type in('int','numeric','text','timestamp','date','time','bool','null','json')
    ) default 'text'                                                                                        -- the deserialized type of the value
);

-- users
create table newssync_users(
    id TEXT primary key not null,                                                                           -- user id
    password TEXT,                                                                                          -- password, salted and hashed; if using external authentication this would be blank
    name TEXT,                                                                                              -- display name
    avatar_url TEXT,                                                                                        -- external URL to avatar
    avatar_type TEXT,                                                                                       -- internal avatar image's MIME content type
    avatar_data BLOB,                                                                                       -- internal avatar image's binary data
    rights integer not null default 0                                                                       -- any administrative rights the user may have
);

-- newsfeeds, deduplicated
create table newssync_feeds(
    id integer primary key not null,                                                                        -- sequence number
    url TEXT not null,                                                                                      -- URL of feed
    title TEXT,                                                                                             -- default title of feed
    favicon TEXT,                                                                                           -- URL of favicon
    source TEXT,                                                                                            -- URL of site to which the feed belongs
    updated datetime,                                                                                       -- time at which the feed was last fetched
    modified datetime,                                                                                      -- time at which the feed last actually changed
    etag TEXT,                                                                                              -- HTTP ETag hash used for cache validation, changes each time the content changes
    err_count integer not null default 0,                                                                   -- count of successive times update resulted in error since last successful update
    err_msg TEXT,                                                                                           -- last error message
    username TEXT not null default '',                                                                      -- HTTP authentication username
    password TEXT not null default '',                                                                      -- HTTP authentication password (this is stored in plain text)
    unique(url,username,password)                                                                           -- a URL with particular credentials should only appear once
);

-- users' subscriptions to newsfeeds, with settings
create table newssync_subscriptions(
    id integer primary key not null,                                                                        -- sequence number
    owner TEXT not null references newssync_users(id) on delete cascade on update cascade,                  -- owner of subscription
    feed integer not null references newssync_feeds(id) on delete cascade,                                  -- feed for the subscription
    added datetime not null default CURRENT_TIMESTAMP,                                                      -- time at which feed was added
    modified datetime not null default CURRENT_TIMESTAMP,                                                   -- date at which subscription properties were last modified
    title TEXT,                                                                                             -- user-supplied title
    order_type int not null default 0,                                                                      -- ownCloud sort order
    pinned boolean not null default 0,                                                                      -- whether feed is pinned (always sorts at top)
    folder integer references newssync_folders(id) on delete set null,                                      -- TT-RSS category (nestable); the first-level category (which acts as ownCloud folder) is joined in when needed
    unique(owner,feed)                                                                                      -- a given feed should only appear once for a given owner
);

-- TT-RSS categories and ownCloud folders
create table newssync_folders(
    id integer primary key not null,                                                                        -- sequence number
    owner TEXT not null references newssync_users(id) on delete cascade on update cascade,                  -- owner of folder
    parent integer not null default 0,                                                                      -- parent folder id
    root integer not null default 0,                                                                        -- first-level folder (ownCloud folder)
    name TEXT not null,                                                                                     -- folder name
    modified datetime not null default CURRENT_TIMESTAMP,                                                   --
    unique(owner,name,parent)                                                                               -- cannot have multiple folders with the same name under the same parent for the same owner
);

-- entries in newsfeeds
create table newssync_articles(
    id integer primary key not null,                                                                        -- sequence number
    feed integer not null references newssync_feeds(id) on delete cascade,                                  -- feed for the subscription
    url TEXT not null,                                                                                      -- URL of article
    title TEXT,                                                                                             -- article title
    author TEXT,                                                                                            -- author's name
    published datetime,                                                                                     -- time of original publication
    edited datetime,                                                                                        -- time of last edit
    guid TEXT,                                                                                              -- GUID
    content TEXT,                                                                                           -- content, as (X)HTML
    modified datetime not null default CURRENT_TIMESTAMP,                                                   -- date when article properties were last modified
    hash varchar(64) not null,                                                                              -- ownCloud hash
    fingerprint varchar(64) not null,                                                                       -- ownCloud fingerprint
    enclosures_hash varchar(64),                                                                            -- hash of enclosures, if any; since enclosures are not uniquely identified, we need to know when they change
    tags_hash varchar(64)                                                                                   -- hash of RSS/Atom categories included in article; since these categories are not uniquely identified, we need to know when they change
);

-- enclosures associated with articles
create table newssync_enclosures(
    article integer not null references newssync_articles(id) on delete cascade,
    url TEXT,
    type varchar(255)
);

-- users' actions on newsfeed entries
create table newssync_subscription_articles(
    id integer primary key not null,
    article integer not null references newssync_articles(id) on delete cascade,
    read boolean not null default 0,
    starred boolean not null default 0,
    modified datetime not null default CURRENT_TIMESTAMP
);

-- user labels associated with newsfeed entries
create table newssync_labels(
    sub_article integer not null references newssync_subscription_articles(id) on delete cascade,            --
    owner TEXT not null references newssync_users(id) on delete cascade on update cascade,
    name TEXT
);
create index newssync_label_names on newssync_labels(name);

-- author labels ("categories" in RSS/Atom parlance) associated with newsfeed entries
create table newssync_tags(
    article integer not null references newssync_articles(id) on delete cascade,
    name TEXT
);

-- set version marker
pragma user_version = 1;
insert into newssync_settings values('schema_version',1,'int');