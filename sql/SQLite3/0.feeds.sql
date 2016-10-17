-- newsfeeds, deduplicated
create table feeds.newssync_feeds(
	id integer primary key not null,																		-- sequence number
	url TEXT not null,																						-- URL of feed
	title TEXT,																								-- default title of feed
	favicon TEXT,																							-- URL of favicon
	source TEXT,																							-- URL of site to which the feed belongs
	updated datetime,																						-- time at which the feed was last fetched
	modified datetime not null default CURRENT_TIMESTAMP,													--
	err_count integer not null default 0,																	-- count of successive times update resulted in error since last successful update
	err_msg TEXT,																							-- last error message
	username TEXT,																							-- HTTP authentication username
	password TEXT,																							-- HTTP authentication password (this is stored in plain text)
	unique(url,username,password)																			-- a URL with particular credentials should only appear once
);

-- entries in newsfeeds
create table feeds.newssync_articles(
	id integer primary key not null,																		-- sequence number
	feed integer not null references newssync_feeds(id) on delete cascade,									-- feed for the subscription
	url TEXT not null,																						-- URL of article
	title TEXT,																								-- article title
	author TEXT,																							-- author's name
	published datetime,																						-- time of original publication
	edited datetime,																						-- time of last edit 
	guid TEXT,																								-- GUID 
	content TEXT,																							-- content, as (X)HTML
	modified datetime not null default CURRENT_TIMESTAMP,													-- date when article properties were last modified
	hash varchar(64) not null,																				-- ownCloud hash 
	fingerprint varchar(64) not null,																		-- ownCloud fingerprint 
	enclosures_hash varchar(64),																			-- hash of enclosures, if any; since enclosures are not uniquely identified, we need to know when they change
	tags_hash varchar(64)																					-- hash of RSS/Atom categories included in article; since these categories are not uniquely identified, we need to know when they change
);

-- enclosures associated with articles
create table feeds.newssync_enclosures(
	article integer not null references newssync_articles(id) on delete cascade,
	url TEXT,
	type varchar(255)
);

-- author labels ("categories" in RSS/Atom parlance) associated with newsfeed entries
create table feeds.newssync_tags(
	article integer not null references newssync_articles(id) on delete cascade,
	name TEXT
);

-- set version marker
pragma feeds.user_version = 1;
