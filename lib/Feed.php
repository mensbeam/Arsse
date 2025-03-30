<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse;

use JKingWeb\Arsse\Feed\Item;
use JKingWeb\Arsse\Misc\Date;
use PicoFeed\PicoFeedException;
use PicoFeed\Config\Config;
use PicoFeed\Client\Client;
use PicoFeed\Reader\Reader;
use PicoFeed\Reader\Favicon;
use PicoFeed\Scraper\Scraper;

class Feed {
    public $title;
    public $siteUrl;
    public $iconUrl;
    public $iconType;
    public $iconData;
    public $modified = false;
    public $lastModified;
    public $etag;
    public $nextFetch;
    public $items = [];
    public $newItems = [];
    public $changedItems = [];

    public static function discover(string $url, ?string $userAgent = null, ?string $cookie = null): string {
        // fetch the candidate feed
        [$client, $reader] = self::download($url, "", "", $userAgent, $cookie);
        if ($reader->detectFormat($client->getContent())) {
            // if the prospective URL is a feed, use it
            $out = $url;
        } else {
            $links = $reader->find($client->getUrl(), $client->getContent());
            if (!$links) {
                throw new Feed\Exception("", ['url' => $url], new \PicoFeed\Reader\SubscriptionNotFoundException('Unable to find a subscription'));
            } else {
                $out = $links[0];
            }
        }
        return $out;
    }

    public static function discoverAll(string $url, ?string $userAgent = null, ?string $cookie = null): array {
        // fetch the candidate feed
        [$client, $reader] = self::download($url, "", "", $userAgent, $cookie);
        if ($reader->detectFormat($client->getContent())) {
            // if the prospective URL is a feed, use it
            return [$url];
        } else {
            return $reader->find($client->getUrl(), $client->getContent());
        }
    }

    public function __construct(?int $feedID, string $url, string $lastModified = '', string $etag = '', ?string $userAgent = null, ?string $cookie = null, bool $scrape = false) {
        // fetch the feed
        [$client, $reader] = self::download($url, $lastModified, $etag, $userAgent, $cookie);
        // format the HTTP Last-Modified date returned
        $lastMod = $client->getLastModified();
        if (strlen($lastMod ?? "")) {
            $this->lastModified = Date::normalize($lastMod, "http");
        }
        $this->modified = $client->isModified();
        // get the ETag
        $this->etag = $client->getEtag();
        // parse the feed, if it has been modified
        if ($this->modified) {
            $this->parse($client, $reader);
            // ascertain whether there are any articles not in the database
            $this->matchToDatabase($feedID);
            // if caching header fields are not sent by the server, try to ascertain a last-modified date from the feed contents
            if (!$this->lastModified) {
                $this->lastModified = $this->computeLastModified();
            }
            // we only really care if articles have been modified; if there are no new articles, act as if the feed is unchanged
            if (!sizeof($this->newItems) && !sizeof($this->changedItems)) {
                $this->modified = false;
            } else {
                // if requested, scrape full content for any new and changed items
                if ($scrape) {
                    $this->scrape($userAgent, $cookie);
                }
            }
        }
        // compute the time at which the feed should next be fetched
        $this->nextFetch = $this->computeNextFetch();
    }

    protected static function configure(?string $userAgent, ?string $cookie): Config {
        $userAgent = $userAgent ?? Arsse::$conf->fetchUserAgentString ?? sprintf(
            'Arsse/%s (%s %s; %s; https://thearsse.com/)',
            Arsse::VERSION, // Arsse version
            php_uname('s'), // OS
            php_uname('r'), // OS version
            php_uname('m') // platform architecture
        );
        $config = new Config;
        $config->setMaxBodySize(Arsse::$conf->fetchSizeLimit);
        $config->setClientTimeout(Arsse::$conf->fetchTimeout);
        $config->setGrabberTimeout(Arsse::$conf->fetchTimeout);
        $config->setClientUserAgent($userAgent);
        $config->setGrabberUserAgent($userAgent);
        $config->setClientHeaders(['Cookie' => $cookie]);
        return $config;
    }

    protected static function download(string $url, string $lastModified, string $etag, ?string $userAgent = null, ?string $cookie = null): array {
        try {
            $reader = new Reader(self::configure($userAgent, $cookie));
            $client = $reader->download($url, $lastModified, $etag, "", "");
            return [$client, $reader];
        } catch (PicoFeedException $e) {
            throw new Feed\Exception("", ['url' => $url], $e); // @codeCoverageIgnore
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            throw new Feed\Exception("", ['url' => $url], $e);
        }
    }

    protected function parse(Client $client, Reader $reader): void {
        try {
            $feed = $reader->getParser(
                $client->getUrl(),
                $client->getContent(),
                $client->getEncoding()
            )->execute();
        } catch (PicoFeedException $e) {
            throw new Feed\Exception("", ['url' => $client->getUrl()], $e);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) { // @codeCoverageIgnore
            throw new Feed\Exception("", ['url' => $client->getUrl()], $e); // @codeCoverageIgnore
        }

        // Grab the favicon for the feed, or null if no valid icon is found
        // Some feeds might use a different domain (eg: feedburner), so the site url is
        // used instead of the feed's url.
        $icon = new Favicon;
        $this->iconUrl = $icon->find($feed->siteUrl, $feed->getIcon());
        $this->iconData = $icon->getContent();
        if (strlen($this->iconData)) {
            $this->iconType = $icon->getType();
        } else {
            $this->iconUrl = $this->iconData = null;
        }

        // Next gather all other feed-level information we want out of the feed
        $this->siteUrl = $feed->siteUrl;
        $this->title = $feed->title;

        // PicoFeed does not provide valid ids when there is no id element. Its solution
        // of hashing the url, title, and content together for the id if there is no id
        // element is stupid. Many feeds are frankenstein mixtures of Atom and RSS, but
        // some are pure RSS with guid elements while others use the Dublin Core spec for
        // identification. These feeds shouldn't be duplicated when updated. That should
        // only be reserved for severely broken feeds.

        foreach ($feed->items as $f) {
            // copy the basic information of an article
            $i = new Item;
            $i->url = $f->url;
            $i->title = $f->title;
            $i->content = $f->content;
            $i->author = $f->author;
            $i->publishedDate = $f->publishedDate;
            $i->updatedDate = $f->updatedDate;
            $i->enclosureType = $f->enclosureType;
            $i->enclosureUrl = $f->enclosureUrl;
            // add hashes used for comparison to check for updates and also to identify when an
            // id doesn't exist.
            $content = $f->content.$f->enclosureUrl.$f->enclosureType;
            // if the item link URL and item title are both equal to the feed link URL, then the item has neither a link URL nor a title
            if ($f->url === $feed->siteUrl && $f->title === $feed->siteUrl) {
                $i->urlTitleHash = "";
            } else {
                $i->urlTitleHash = hash('sha256', $f->url.$f->title);
            }
            // if the item link URL is equal to the feed link URL, it has no link URL; if there is additionally no content, these should not be hashed
            if (!strlen($content) && $f->url === $feed->siteUrl) {
                $i->urlContentHash = "";
            } else {
                $i->urlContentHash = hash('sha256', $f->url.$content);
            }
            // if the item's title is the same as its link URL, it has no title; if there is additionally no content, these should not be hashed
            if (!strlen($content) && $f->title === $f->url) {
                $i->titleContentHash = "";
            } else {
                $i->titleContentHash = hash('sha256', $f->title.$content);
            }
            // next add an id; prefer an Atom ID as the item's ID
            $id = (string) $f->xml->children('http://www.w3.org/2005/Atom')->id;
            // otherwise use the RSS2 guid element
            if (!strlen($id)) {
                $id = (string) $f->xml->guid;
            }
            // otherwise use the Dublin Core identifier element
            if (!strlen($id)) {
                $id = (string) $f->xml->children('http://purl.org/dc/elements/1.1/')->identifier;
            }
            // otherwise there is no ID; if there is one, hash it
            if (strlen($id)) {
                $i->id = hash('sha256', $id);
            }

            // PicoFeed also doesn't gather up categories, so we do this as well
            // first add Atom categories
            foreach ($f->xml->children('http://www.w3.org/2005/Atom')->category as $c) {
                // if the category has a label, use that
                $name = (string) $c->attributes()->label;
                // otherwise use the term
                if (!strlen($name)) {
                    $name = (string) $c->attributes()->term;
                }
                // ... assuming it has that much
                if (strlen($name)) {
                    $i->categories[] = $name;
                }
            }
            // next add RSS2 categories
            foreach ($f->xml->children()->category as $c) {
                $name = (string) $c;
                if (strlen($name)) {
                    $i->categories[] = $name;
                }
            }
            // and finally try Dublin Core subjects
            foreach ($f->xml->children('http://purl.org/dc/elements/1.1/')->subject as $c) {
                $name = (string) $c;
                if (strlen($name)) {
                    $i->categories[] = $name;
                }
            }
            //sort the results
            sort($i->categories);
            // add the item to the feed's list of items
            $this->items[] = $i;
        }
    }

    protected function deduplicateItems(array $items): array {
        /* Rationale:
            Some newsfeeds (notably Planet) include multiple versions of an
            item if it is updated. As we only care about the latest, we
            try to remove any "old" versions of an item that might also be
            present within the feed.
        */
        $out = [];
        foreach ($items as $item) {
            foreach ($out as $index => $check) {
                // if the two items both have IDs and they differ, they do not match, regardless of hashes
                if ($item->id && $check->id && $item->id !== $check->id) {
                    continue;
                }
                // if the two items have the same ID or any one hash matches, they are two versions of the same item
                if (
                    ($item->id && $check->id && $item->id === $check->id) ||
                    ($item->urlTitleHash && $item->urlTitleHash === $check->urlTitleHash) ||
                    ($item->urlContentHash && $item->urlContentHash === $check->urlContentHash) ||
                    ($item->titleContentHash && $item->titleContentHash === $check->titleContentHash)
                ) {
                    if (// because newsfeeds are usually ordered newest-first, the later item should only be used if...
                        // the later item has an update date and the existing item does not
                        ($item->updatedDate && !$check->updatedDate) ||
                        // the later item has an update date newer than the existing item's
                        ($item->updatedDate && $check->updatedDate && $item->updatedDate->getTimestamp() > $check->updatedDate->getTimestamp()) ||
                        // neither item has update dates, both have publish dates, and the later item has a newer publish date
                        (!$item->updatedDate && !$check->updatedDate && $item->publishedDate && $check->publishedDate && $item->publishedDate->getTimestamp() > $check->publishedDate->getTimestamp())
                    ) {
                        // if the later item should be used, replace the existing one
                        $out[$index] = $item;
                        continue 2;
                    } else {
                        // otherwise skip the item
                        continue 2;
                    }
                }
            }
            // if there was no match, add the item
            $out[] = $item;
        }
        return $out;
    }

    protected function matchToDatabase(?int $feedID = null): void {
        // first perform deduplication on items
        $items = $this->deduplicateItems($this->items);
        // if we haven't been given a database feed ID to check against, all items are new
        if (is_null($feedID)) {
            $this->newItems = $items;
            return;
        }
        // get as many of the latest articles in the database as there are in the feed
        $articles = Arsse::$db->feedMatchLatest($feedID, sizeof($items))->getAll();
        // perform a first pass matching the latest articles against items in the feed
        [$this->newItems, $this->changedItems] = $this->matchItems($items, $articles);
        if (sizeof($this->newItems)) {
            // if we need to, perform a second pass on the database looking specifically for IDs and hashes of the new items
            $ids = $hashesUT = $hashesUC = $hashesTC = [];
            foreach ($this->newItems as $i) {
                if ($i->id) {
                    $ids[] = $i->id;
                }
                if ($i->urlTitleHash) {
                    $hashesUT[] = $i->urlTitleHash;
                }
                if ($i->urlContentHash) {
                    $hashesUC[] = $i->urlContentHash;
                }
                if ($i->titleContentHash) {
                    $hashesTC[] = $i->titleContentHash;
                }
            }
            $articles = Arsse::$db->feedMatchIds($feedID, $ids, $hashesUT, $hashesUC, $hashesTC)->getAll();
            [$this->newItems, $changed] = $this->matchItems($this->newItems, $articles);
            // merge the two change-lists, preserving keys
            $this->changedItems = array_combine(array_merge(array_keys($this->changedItems), array_keys($changed)), array_merge($this->changedItems, $changed));
        }
    }

    protected function matchItems(array $items, array $articles): array {
        $new = $edited = [];
        // iterate through the articles and for each determine whether it is existing, edited, or entirely new
        foreach ($items as $i) {
            $found = false;
            foreach ($articles as $a) {
                // if the item has an ID and it doesn't match the article ID, the two don't match, regardless of hashes
                if ($i->id && $i->id !== $a['guid']) {
                    continue;
                }
                if (
                    // the item matches if the GUID matches...
                    ($i->id && $i->id === $a['guid']) ||
                    // ... or if any one of the hashes match
                    ($i->urlTitleHash && $i->urlTitleHash === $a['url_title_hash']) ||
                    ($i->urlContentHash && $i->urlContentHash === $a['url_content_hash']) ||
                    ($i->titleContentHash && $i->titleContentHash === $a['title_content_hash'])
                ) {
                    if ($i->updatedDate && Date::transform($i->updatedDate, "sql") !== $a['edited']) {
                        // if the item has an edit timestamp and it doesn't match that of the article in the database, the the article has been edited
                        // we store the item index and database record ID as a key/value pair
                        $found = true;
                        $edited[$a['id']] = $i;
                        break;
                    } elseif ($i->urlTitleHash !== $a['url_title_hash'] || $i->urlContentHash !== $a['url_content_hash'] || $i->titleContentHash !== $a['title_content_hash']) {
                        // if any of the hashes do not match, then the article has been edited
                        $found = true;
                        $edited[$a['id']] = $i;
                        break;
                    } else {
                        // otherwise the item is unchanged and we can ignore it
                        $found = true;
                        break;
                    }
                }
            }
            if (!$found) {
                $new[] = $i;
            }
        }
        return [$new, $edited];
    }

    protected function computeNextFetch(): \DateTimeImmutable {
        $now = Date::normalize(time());
        if (!$this->modified) {
            if ($this->lastModified) {
                $diff = $now->getTimestamp() - $this->lastModified->getTimestamp();
                $offset = $this->normalizeDateDiff($diff);
            } else {
                // if no timestamp is available, fall back to three hours
                $offset = "3 hours";
            }
            return $now->modify("+".$offset);
        } else {
            // the algorithm for updated feeds (returning 200 rather than 304) uses the same parameters as for 304,
            // save that the last three intervals between item dates are computed, and if any two fall within
            // the same interval range, that interval is used (e.g. if the intervals are 23m, 12m, and 4h, the used
            // interval is "less than 30m"). If there is no commonality, the feed is checked in 1 hour.
            $offsets = [];
            $dates = $this->gatherDates();
            if (sizeof($dates) > 3) {
                for ($a = 0; $a < 3; $a++) {
                    $diff = $dates[$a] - $dates[$a + 1];
                    $offsets[] = $this->normalizeDateDiff($diff);
                }
                if ($offsets[0] === $offsets[1] || $offsets[0] === $offsets[2]) {
                    return $now->modify("+".$offsets[0]);
                } elseif ($offsets[1] === $offsets[2]) {
                    return $now->modify("+".$offsets[1]);
                } else {
                    return $now->modify("+ 1 hour");
                }
            } else {
                return $now->modify("+ 1 hour");
            }
        }
    }

    public static function nextFetchOnError($errCount): \DateTimeImmutable {
        if ($errCount < 3) {
            $offset = "5 minutes";
        } elseif ($errCount < 15) {
            $offset = "3 hours";
        } else {
            $offset = "1 day";
        }
        return Date::normalize("now + ".$offset);
    }

    protected function normalizeDateDiff(int $diff): string {
        if ($diff < (30 * 60)) { // less than 30 minutes
            $offset = "15 minutes";
        } elseif ($diff < (60 * 60)) { // less than an hour
            $offset = "30 minutes";
        } elseif ($diff < (3 * 60 * 60)) { // less than three hours
            $offset = "1 hour";
        } elseif ($diff >= (36 * 60 * 60)) { // more than 36 hours
            $offset = "1 day";
        } else {
            $offset = "3 hours";
        }
        return $offset;
    }

    protected function computeLastModified(): ?\DateTimeImmutable {
        if (!$this->modified) {
            return $this->lastModified; // @codeCoverageIgnore
        }
        $dates = $this->gatherDates();
        if (sizeof($dates)) {
            return Date::normalize($dates[0]);
        } else {
            return null; // @codeCoverageIgnore
        }
    }

    protected function gatherDates(): array {
        $dates = [];
        foreach ($this->items as $item) {
            if ($item->updatedDate) {
                $dates[] = $item->updatedDate->getTimestamp();
            }
            if ($item->publishedDate) {
                $dates[] = $item->publishedDate->getTimestamp();
            }
        }
        $dates = array_unique($dates, \SORT_NUMERIC);
        rsort($dates);
        return $dates;
    }

    protected function scrape(?string $userAgent = null, ?string $cookie = null): void {
        $scraper = new Scraper(self::configure($userAgent, $cookie));
        foreach (array_merge($this->newItems, $this->changedItems) as $item) {
            $scraper->setUrl($item->url);
            $scraper->execute();
            if ($scraper->hasRelevantContent()) {
                $item->scrapedContent = $scraper->getFilteredContent();
            }
        }
    }
}
