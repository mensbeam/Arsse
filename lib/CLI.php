<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;

class CLI {
    protected $args = [];
    
    protected function usage(): string {
        $prog = basename($_SERVER['argv'][0]);
        return <<<USAGE_TEXT
Usage:
    $prog daemon
    $prog feed refresh <n>
    $prog --version
    $prog --help | -h

The Arsse command-line interface currently allows you to start the refresh
daemon or refresh a specific feed by numeric ID.
USAGE_TEXT;
    }

    function __construct(array $argv = null) {
        if(is_null($argv)) {
            $argv = array_slice($_SERVER['argv'], 1);
        }
        $this->args = \Docopt::handle($this->usage(), [
            'argv' => $argv,
            'help' => true,
            'version' => VERSION,
        ]);
    }

    function dispatch(array $args = null): int {
        // act on command line
        if(is_null($args)) {
            $args = $this->args;
        }
        if($args['daemon']) {
            return $this->daemon();
        } elseif($args['feed'] && $args['refresh']) {
            return $this->feedRefresh((int) $args['<n>']);
        }
    }

    protected function daemon(bool $loop = true): int {
        (new Service)->watch($loop);
        return 0; // FIXME: should return the exception code of thrown exceptions
    }

    protected function feedRefresh(int $id): int {
        return (int) !Arsse::$db->feedUpdate($id);
    }
}