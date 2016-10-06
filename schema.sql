begin;

create table main.newssync_settings(
	key varchar(255) primary key not null,													--
	value varchar(255),																		--
	type varchar(255) not null check(
		type in('numeric','text','timestamp', 'date', 'time', 'bool')
	)																						--
);
insert into main.newssync_settings values('schema_version',1,'int');

-- users
create table main.newssync_users(
	id TEXT primary key not null,															-- user id
	password TEXT,																			-- password, salted and hashed; if using external authentication this would be blank
	name TEXT,																				-- display name
	avatar_type TEXT,																		-- avatar image's MIME content type
	avatar_data BLOB,																		-- avatar image's binary data
	admin boolean not null default 0														-- whether the user is an administrator
);

-- TT-RSS categories and ownCloud folders
create table main.newssync_categories(
	id integer primary key not null,														-- sequence number
	owner TEXT not null references users(id) on delete cascade on update cascade,			-- owner of category
	parent integer,																			-- parent category id
	folder integer not null,																-- first-level category (ownCloud folder)
	name TEXT not null,																		-- category name
	modified datetime not null default CURRENT_TIMESTAMP,									--
	unique(owner,name,parent)																-- cannot have multiple categories with the same name under the same parent for the same owner
);

-- newsfeeds, deduplicated
create table feeds.newssync_feeds(
	id integer primary key not null,														-- sequence number
	url TEXT not null,																		-- URL of feed
	title TEXT,																				-- default title of feed
	favicon TEXT,																			-- URL of favicon
	source TEXT,																			-- URL of site to which the feed belongs
	updated datetime,																		-- time at which the feed was last fetched
	modified datetime not null default CURRENT_TIMESTAMP,									--
	err_count integer not null default 0,													-- count of successive times update resulted in error since last successful update
	err_msg TEXT,																			-- last error message
	username TEXT,																			-- HTTP authentication username
	password TEXT,																			-- HTTP authentication password (this is stored in plain text)
	unique(url,username,password)															-- a URL with particular credentials should only appear once
);

-- users' subscriptions to newsfeeds, with settings
create table main.newssync_subscriptions(
	id integer primary key not null,														-- sequence number
	owner TEXT not null references users(id) on delete cascade on update cascade,			-- owner of subscription
	feed integer not null references feeds(id) on delete cascade,									-- feed for the subscription
	added datetime not null default CURRENT_TIMESTAMP,										-- time at which feed was added
	modified datetime not null default CURRENT_TIMESTAMP,									-- date at which subscription properties were last modified
	title TEXT,																				-- user-supplied title
	order_type int not null default 0,														-- ownCloud sort order
	pinned boolean not null default 0,														-- whether feed is pinned (always sorts at top)
	category integer not null references categories(id) on delete set null,							-- TT-RSS category (nestable); the first-level category (which acts as ownCloud folder) is joined in when needed
	unique(owner,feed)																		-- a given feed should only appear once for a given owner
);

-- entries in newsfeeds
create table feeds.newssync_articles(
	id integer primary key not null,														-- sequence number
	feed integer not null references feeds(id) on delete cascade,							-- feed for the subscription
	url TEXT not null,																		-- URL of article
	title TEXT,																				-- article title
	author TEXT,																			-- author's name
	published datetime,																		-- time of original publication
	edited datetime,																		-- time of last edit 
	guid TEXT,																				-- GUID 
	content TEXT,																			-- content, as (X)HTML
	modified datetime not null default CURRENT_TIMESTAMP,									-- date when article properties were last modified
	hash varchar(64) not null,																-- ownCloud hash 
	fingerprint varchar(64) not null,														-- ownCloud fingerprint 
	enclosures_hash varchar(64),															-- hash of enclosures, if any; since enclosures are not uniquely identified, we need to know when they change
	tags_hash varchar(64)																	-- hash of RSS/Atom categories included in article; since these categories are not uniquely identified, we need to know when they change
);

-- users' actions on newsfeed entries
create table main.newssync_subscription_articles(
	id integer primary key not null,
	article integer not null references articles(id) on delete cascade,
	read boolean not null default 0,
	starred boolean not null default 0,
	modified datetime not null default CURRENT_TIMESTAMP
);

-- enclosures associated with articles
create table main.newssync_enclosures(
	article integer not null references articles(id) on delete cascade,
	url TEXT,
	type varchar(255)
);

-- author labels ("categories" in RSS/Atom parlance) associated with newsfeed entries
create table main.newssync_tags(
	article integer not null references articles(id) on delete cascade,
	name TEXT
);

-- user labels associated with newsfeed entries
create table main.newssync_labels(
	sub_article integer not null references subscription_articles(id) on delete cascade,
	owner TEXT not null references users(id) on delete cascade on update cascade,
	name TEXT 
);
create index main.newssync_label_names on newssync_labels(name);

commit;