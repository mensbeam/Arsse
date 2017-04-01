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

    public function __construct(string $url, string $lastModified = '', string $etag = '', string $username = '', string $password = '') {
        try {
            $config = new Config;
            $config->setClientUserAgent(Data::$conf->userAgentString);
            $config->setGrabberUserAgent(Data::$conf->userAgentString);

            $this->reader = new Reader($config);
            $this->resource = $this->reader->download($url, $lastModified, $etag, $username, $password);
            // Grab the favicon for the feed; returns an empty string if it cannot find one.
            $this->favicon = (new Favicon)->find($url);
        } catch (PicoFeedException $e) {
            throw new Feed\Exception($url, $e);
        }
    }

    public function parse(): bool {
        try {
            $this->parser = $this->reader->getParser(
                $this->resource->getUrl(),
                $this->resource->getContent(),
                $this->resource->getEncoding()
            );
            $feed = $this->parser->execute();
        } catch (PicoFeedException $e) {
            throw new Feed\Exception($url, $e);
        }

        // PicoFeed does not provide valid ids when there is no id element. Its solution
        // of hashing the url, title, and content together for the id if there is no id
        // element is stupid. Many feeds are frankenstein mixtures of Atom and RSS, but
        // some are pure RSS with guid elements while others use the Dublin Core spec for
        // identification. These feeds shouldn't be duplicated when updated. That should
        // only be reserved for severely broken feeds.

        foreach ($feed->items as &$f) {
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

        $this->data = $feed;
        return true;
    }
}