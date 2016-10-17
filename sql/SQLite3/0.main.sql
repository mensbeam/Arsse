-- settings
create table main.newssync_settings(
	key varchar(255) primary key not null,																	--
	value varchar(255),																						--
	type varchar(255) not null check(
		type in('int','numeric','text','timestamp','date','time','bool','null','json')
	)																										--
);

-- users
create table main.newssync_users(
	id TEXT primary key not null,																			-- user id
	password TEXT,																							-- password, salted and hashed; if using external authentication this would be blank
	name TEXT,																								-- display name
	avatar_type TEXT,																						-- avatar image's MIME content type
	avatar_data BLOB,																						-- avatar image's binary data
	admin boolean not null default 0																		-- whether the user is an administrator
);

-- TT-RSS categories and ownCloud folders
create table main.newssync_categories(
	id integer primary key not null,																		-- sequence number
	owner TEXT not null references newssync_users(id) on delete cascade on update cascade,					-- owner of category
	parent integer,																							-- parent category id
	folder integer not null,																				-- first-level category (ownCloud folder)
	name TEXT not null,																						-- category name
	modified datetime not null default CURRENT_TIMESTAMP,													--
	unique(owner,name,parent)																				-- cannot have multiple categories with the same name under the same parent for the same owner
);

-- users' subscriptions to newsfeeds, with settings
create table main.newssync_subscriptions(
	id integer primary key not null,																		-- sequence number
	owner TEXT not null references newssync_users(id) on delete cascade on update cascade,					-- owner of subscription
	feed integer not null references newssync_feeds(id) on delete cascade,									-- feed for the subscription
	added datetime not null default CURRENT_TIMESTAMP,														-- time at which feed was added
	modified datetime not null default CURRENT_TIMESTAMP,													-- date at which subscription properties were last modified
	title TEXT,																								-- user-supplied title
	order_type int not null default 0,																		-- ownCloud sort order
	pinned boolean not null default 0,																		-- whether feed is pinned (always sorts at top)
	category integer not null references newssync_categories(id) on delete set null,						-- TT-RSS category (nestable); the first-level category (which acts as ownCloud folder) is joined in when needed
	unique(owner,feed)																						-- a given feed should only appear once for a given owner
);

-- users' actions on newsfeed entries
create table main.newssync_subscription_articles(
	id integer primary key not null,
	article integer not null references newssync_articles(id) on delete cascade,
	read boolean not null default 0,
	starred boolean not null default 0,
	modified datetime not null default CURRENT_TIMESTAMP
);

-- user labels associated with newsfeed entries
create table main.newssync_labels(
	sub_article integer not null references newssync_subscription_articles(id) on delete cascade,			--
	owner TEXT not null references newssync_users(id) on delete cascade on update cascade,
	name TEXT 
);
create index main.newssync_label_names on newssync_labels(name);

-- set version marker
pragma main.user_version = 1;
insert into main.newssync_settings values('schema_version',1,'int');