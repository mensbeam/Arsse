begin;

-- users
create table chibi_users(
	id TEXT primary key not null,		-- user id
	password TEXT,						-- password, salted and hashed; if using external authentication this would be blank
	name TEXT,							-- display name
	avatar_type TEXT,					-- avatar image's MIME content type
	avatar_data BLOB,					-- avatar image's binary data
	admin boolean not null default 0	-- whether the user is an administrator
);

-- TT-RSS categories and ownCloud folders
create table chibi_categories(
	id integer primary key not null,										-- sequence number
	owner TEXT references users(id) on delete cascade on update cascade,	-- owner of category
	parent integer,															-- parent category id
	folder integer not null,												-- first-level category (ownCloud folder)
	name TEXT not null,														-- category name
	unique(owner,name,parent)												-- cannot have multiple categories with the same name under the same parent for the same owner
);

-- newsfeeds, deduplicated
create table chibi_feeds(
	id integer primary key not null,		-- sequence number
	url TEXT not null,						-- URL of feed
	title TEXT,								-- default title of feed
	favicon TEXT,							-- URL of favicon
	source TEXT,							-- URL of site to which the feed belongs
	updated datetime,						-- time at which the feed was last fetched
	err_count integer not null default 0,	-- count of successive times update resulted in error since last successful update
	err_msg TEXT,							-- last error message
	username TEXT,							-- HTTP authentication username
	password TEXT,							-- HTTP authentication password (this is stored in plain text)
	unique(url,username,password)			-- 
);

-- users' subscriptions to newsfeeds, with settings
create table chibi_subscriptions(
	id integer primary key not null,														-- sequence number
	owner TEXT references users(id) on delete cascade on update cascade,					-- owner of subscription
	feed integer references feeds(id) on delete cascade,									-- feed for the subscription
	added datetime not null default CURRENT_TIMESTAMP,										-- time at which feed was added
	title TEXT,																				-- user-supplied title
	order_type int not null default 0,														-- ownCloud sort order
	pinned boolean not null default 0,														-- whether feed is pinned (always sorts at top)
	category integer references categories(id) on delete set null							-- TT-RSS category (nestable); the first-level category (which acts as ownCloud folder) is joined in when needed
);

-- entries in newsfeeds
create table chibi_articles(
	id integer primary key not null,						-- sequence number
	feed integer references feeds(id) on delete cascade,	-- feed for the subscription
	url TEXT not null,										-- URL of article
	title TEXT,												-- article title
	author TEXT,											-- author's name
	published datetime,										-- time of original publication
	edited datetime,										-- time of last edit 
	guid TEXT,												-- GUID 
	content TEXT,											-- content, as (X)HTML
	hash varchar(64) not null,								-- ownCloud hash 
	fingerprint varchar(64) not null,						-- ownCloud fingerprint 
	enclosures_hash varchar(64),							-- hash of enclosures, if any; since enclosures are not uniquely identified, we need to know when they change
	tags_hash varchar(64)									-- hash of RSS/Atom categories included in article; since these categories are not uniquely identified, we need to know when they change
);

-- users' actions on newsfeed entries
create table chibi_subscription_articles(
	id integer primary key,
	article integer references articles(id) on delete cascade,
	read boolean not null default 0,
	starred boolean not null default 0
);

-- enclosures associated with articles
create table chibi_enclosures(
	article integer references articles(id) on delete cascade,
	url TEXT,
	type varchar(255)
);

-- author labels ("categories" in RSS/Atom parlance) associated with newsfeed entries
create table chibi_tags(
	article integer references articles(id) on delete cascade,
	name TEXT
);

-- user labels associated with newsfeed entries
create table chibi_labels(
	sub_article integer references subscription_articles(id) on delete cascade,
	owner TEXT references users(id) on delete cascade on update cascade,
	name TEXT 
);
create index chibi_label_names on chibi_labels(name);

commit;