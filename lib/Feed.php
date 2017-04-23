<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;
use PicoFeed\Reader\Reader;
use PicoFeed\PicoFeedException;
use PicoFeed\Reader\Favicon;
use PicoFeed\Config\Config;

class Feed {
    public $data = null;
    public $favicon;
    public $parser;
    public $reader;
    public $resource;
    public $newItems = [];
    public $changedItems = [];

    public function __construct(string $url, string $lastModified = '', string $etag = '', string $username = '', string $password = '') {
        try {
            $config = new Config;
            $config->setClientUserAgent(Data::$conf->userAgentString);
            $config->setGrabberUserAgent(Data::$conf->userAgentString);

            $this->reader = new Reader($config);
            $this->resource = $this->reader->download($url, $lastModified, $etag, $username, $password);
        } catch (PicoFeedException $e) {
            throw new Feed\Exception($url, $e);
        }
    }

    public function parse(int $feedID = null): bool {
        try {
            $this->parser = $this->reader->getParser(
                $this->resource->getUrl(),
                $this->resource->getContent(),
                $this->resource->getEncoding()
            );
            $feed = $this->parser->execute();

            // Grab the favicon for the feed; returns an empty string if it cannot find one.
            // Some feeds might use a different domain (eg: feedburner), so the site url is
            // used instead of the feed's url.
            $this->favicon = (new Favicon)->find($feed->siteUrl);
        } catch (PicoFeedException $e) {
            throw new Feed\Exception($url, $e);
        }

        // PicoFeed does not provide valid ids when there is no id element. Its solution
        // of hashing the url, title, and content together for the id if there is no id
        // element is stupid. Many feeds are frankenstein mixtures of Atom and RSS, but
        // some are pure RSS with guid elements while others use the Dublin Core spec for
        // identification. These feeds shouldn't be duplicated when updated. That should
        // only be reserved for severely broken feeds.

        foreach ($feed->items as $f) {
            // Hashes used for comparison to check for updates and also to identify when an
            // id doesn't exist.
            $f->urlTitleHash = hash('sha256', $f->url.$f->title);
            $f->urlContentHash = hash('sha256', $f->url.$f->content.$f->enclosureUrl.$f->enclosureType);
            $f->titleContentHash = hash('sha256', $f->title.$f->content.$f->enclosureUrl.$f->enclosureType);

            // If there is an id element then continue. The id is used already.
            $id = (string)$f->xml->id;
            if ($id !== '') {
                continue;
            }

            // If there is a guid element use it as the id.
            $id = (string)$f->xml->guid;
            if ($id !== '') {
                $f->id = hash('sha256', $id);
                continue;
            }

            // If there is a Dublin Core identifier use it.
            $id = (string)$f->xml->children('http://purl.org/dc/elements/1.1/')->identifier;
            if ($id !== '') {
                $f->id = hash('sha256', $id);
                continue;
            }

            // If there aren't any of those there is no id.
            $f->id = '';
        }
        // if a feedID is supplied, determine which items are already in the database, which are not, and which might have been edited
        if(!is_null($feedID)) {
            // FIXME: first perform deduplication on items
            // array if items in the fetched feed
            $items = $feed->items;
            // get as many of the latest articles in the database as there are in the feed
            $articles = Data::$db->articleMatchLatest($feedID, sizeof($items));
            // arrays holding new, edited, and tentatively new items; items may be tentatively new because we perform two passes
            $new = $tentative = $edited = [];
            // iterate through the articles and for each determine whether it is existing, edited, or entirely new
            foreach($items as $index => $i) {
                foreach($articles as $a) {
                    if(
                        // the item matches if the GUID matches...
                        ($i->id && $i->id === $a['guid']) ||
                        // ... or if any one of the hashes match
                        $i->urlTitleHash     === $a['url_title_hash']     ||
                        $i->urlContentHash   === $a['url_content_hash']   ||
                        $i->titleContentHash === $a['title_content_hash']
                    ) {
                        if($i->updatedDate && $i->updatedDate->getTimestamp() !== $match['edited_date']) {
                            // if the item has an edit timestamp and it doesn't match that of the article in the database, the the article has been edited
                            // we store the item index and database record ID as a key/value pair
                            $edited[$index] = $a['id'];
                            break;
                        } else if($i->urlTitleHash !== $a['url_title_hash'] || $i->urlContentHash !== $a['url_content_hash'] || $i->titleContentHash !== $a['title_content_hash']) {
                            // if any of the hashes do not match, then the article has been edited
                            $edited[$index] = $a['id'];
                            break;
                        } else {
                            // otherwise the item is unchanged and we can ignore it
                            break;
                        }
                    } else {
                        // if we don't have a match, add the item to the tentatively new list
                        $tentative[] = $index;
                    }
                }
            }
            if(sizeof($tentative)) {
                // if we need to, perform a second pass on the database looking specifically for IDs and hashes of the new items
                $ids = $hashesUT = $hashesUC = $hashesTC = [];
                foreach($tentative as $index) {
                    $i = $items[$index];
                    if($i->id) $ids[] = $id->id;
                    $hashesUT[] = $i->urlTitleHash;
                    $hashesUC[] = $i->urlContentHash;
                    $hashesTC[] = $i->titleContentHash;
                }
                $articles = Data::$db->articleMatchIds($feedID, $ids, $hashesUT, $hashesUC, $hashesTC);
                foreach($tentative as $index) {
                    $i = $items[$index];
                    foreach($articles as $a) {
                        if(
                            // the item matches if the GUID matches...
                            ($i->id && $i->id === $a['guid']) ||
                            // ... or if any one of the hashes match
                            $i->urlTitleHash     === $a['url_title_hash']     ||
                            $i->urlContentHash   === $a['url_content_hash']   ||
                            $i->titleContentHash === $a['title_content_hash']
                        ) {
                            if($i->updatedDate && $i->updatedDate->getTimestamp() !== $match['edited_date']) {
                                // if the item has an edit timestamp and it doesn't match that of the article in the database, the the article has been edited
                                // we store the item index and database record ID as a key/value pair
                                $edited[$index] = $a['id'];
                                break;
                            } else if($i->urlTitleHash !== $a['url_title_hash'] || $i->urlContentHash !== $a['url_content_hash'] || $i->titleContentHash !== $a['title_content_hash']) {
                                // if any of the hashes do not match, then the article has been edited
                                $edited[$index] = $a['id'];
                                break;
                            } else {
                                // otherwise the item is unchanged and we can ignore it
                                break;
                            }
                        } else {
                            // if we don't have a match, add the item to the definite new list
                            $new[] = $index;
                        }
                    }
                }
            }
            // FIXME: fetch full content when appropriate
            foreach($new as $index) {
                $this->newItems[] = $items[$index];
            }
            foreach($edited as $index => $id) {
                $this->changedItems[$id] = $items[$index];
            }
        }
        $this->data = $feed;
        return true;
    }
}