<?php

/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\REST\TinyTinyRSS;

use JKingWeb\Arsse\Context\Context;
use JKingWeb\Arsse\REST\TinyTinyRSS\Search;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(\JKingWeb\Arsse\REST\TinyTinyRSS\Search::class)]
class TestSearch extends \JKingWeb\Arsse\Test\AbstractTest {
    public static function provideSearchStrings(): iterable {
        return [
            'Blank string'                        => ["", new Context],
            'Whitespace only'                     => [" \n  \t", new Context],
            'Simple bare token'                   => ['OOK', (new Context)->searchTerms(["ook"])],
            'Simple negative bare token'          => ['-OOK', (new Context)->not->searchTerms(["ook"])],
            'Simple quoted token'                 => ['"OOK eek"', (new Context)->searchTerms(["ook eek"])],
            'Simple negative quoted token'        => ['"-OOK eek"', (new Context)->not->searchTerms(["ook eek"])],
            'Simple bare tokens'                  => ['OOK eek', (new Context)->searchTerms(["ook", "eek"])],
            'Simple mixed bare tokens'            => ['-OOK eek', (new Context)->not->searchTerms(["ook"])->searchTerms(["eek"])],
            'Unclosed quoted token'               => ['"OOK eek', (new Context)->searchTerms(["ook eek"])],
            'Unclosed quoted token 2'             => ['"OOK eek" "', (new Context)->searchTerms(["ook eek"])],
            'Broken quoted token 1'               => ['"-OOK"eek"', (new Context)->not->searchTerms(["ookeek\""])],
            'Broken quoted token 2'               => ['""eek"', (new Context)->searchTerms(["eek\""])],
            'Broken quoted token 3'               => ['"-"eek"', (new Context)->not->searchTerms(["eek\""])],
            'Empty quoted token'                  => ['""', new Context],
            'Simple quoted tokens'                => ['"OOK eek" "eek ack"', (new Context)->searchTerms(["ook eek", "eek ack"])],
            'Bare blank tag'                      => [':ook', (new Context)->searchTerms([":ook"])],
            'Quoted blank tag'                    => ['":ook"', (new Context)->searchTerms([":ook"])],
            'Bare negative blank tag'             => ['-:ook', (new Context)->not->searchTerms([":ook"])],
            'Quoted negative blank tag'           => ['"-:ook"', (new Context)->not->searchTerms([":ook"])],
            'Bare valueless blank tag'            => [':', (new Context)->searchTerms([":"])],
            'Quoted valueless blank tag'          => ['":"', (new Context)->searchTerms([":"])],
            'Bare negative valueless blank tag'   => ['-:', (new Context)->not->searchTerms([":"])],
            'Quoted negative valueless blank tag' => ['"-:"', (new Context)->not->searchTerms([":"])],
            'Double negative'                     => ['--eek', (new Context)->not->searchTerms(["-eek"])],
            'Double negative 2'                   => ['--@eek', (new Context)->not->searchTerms(["-@eek"])],
            'Double negative 3'                   => ['"--@eek"', (new Context)->not->searchTerms(["-@eek"])],
            'Double negative 4'                   => ['"--eek"', (new Context)->not->searchTerms(["-eek"])],
            'Negative before quote'               => ['-"ook"', (new Context)->not->searchTerms(["\"ook\""])],
            'Bare unread tag true'                => ['UNREAD:true', (new Context)->unread(true)],
            'Bare unread tag false'               => ['UNREAD:false', (new Context)->unread(false)],
            'Bare negative unread tag true'       => ['-unread:true', (new Context)->unread(false)],
            'Bare negative unread tag false'      => ['-unread:false', (new Context)->unread(true)],
            'Quoted unread tag true'              => ['"UNREAD:true"', (new Context)->unread(true)],
            'Quoted unread tag false'             => ['"UNREAD:false"', (new Context)->unread(false)],
            'Quoted negative unread tag true'     => ['"-unread:true"', (new Context)->unread(false)],
            'Quoted negative unread tag false'    => ['"-unread:false"', (new Context)->unread(true)],
            'Bare star tag true'                  => ['STAR:true', (new Context)->starred(true)],
            'Bare star tag false'                 => ['STAR:false', (new Context)->starred(false)],
            'Bare negative star tag true'         => ['-star:true', (new Context)->starred(false)],
            'Bare negative star tag false'        => ['-star:false', (new Context)->starred(true)],
            'Quoted star tag true'                => ['"STAR:true"', (new Context)->starred(true)],
            'Quoted star tag false'               => ['"STAR:false"', (new Context)->starred(false)],
            'Quoted negative star tag true'       => ['"-star:true"', (new Context)->starred(false)],
            'Quoted negative star tag false'      => ['"-star:false"', (new Context)->starred(true)],
            'Bare note tag true'                  => ['NOTE:true', (new Context)->annotated(true)],
            'Bare note tag false'                 => ['NOTE:false', (new Context)->annotated(false)],
            'Bare negative note tag true'         => ['-note:true', (new Context)->annotated(false)],
            'Bare negative note tag false'        => ['-note:false', (new Context)->annotated(true)],
            'Quoted note tag true'                => ['"NOTE:true"', (new Context)->annotated(true)],
            'Quoted note tag false'               => ['"NOTE:false"', (new Context)->annotated(false)],
            'Quoted negative note tag true'       => ['"-note:true"', (new Context)->annotated(false)],
            'Quoted negative note tag false'      => ['"-note:false"', (new Context)->annotated(true)],
            'Bare pub tag true'                   => ['PUB:true', null],
            'Bare pub tag false'                  => ['PUB:false', new Context],
            'Bare negative pub tag true'          => ['-pub:true', new Context],
            'Bare negative pub tag false'         => ['-pub:false', null],
            'Quoted pub tag true'                 => ['"PUB:true"', null],
            'Quoted pub tag false'                => ['"PUB:false"', new Context],
            'Quoted negative pub tag true'        => ['"-pub:true"', new Context],
            'Quoted negative pub tag false'       => ['"-pub:false"', null],
            'Non-boolean unread tag'              => ['unread:maybe', (new Context)->searchTerms(["unread:maybe"])],
            'Non-boolean star tag'                => ['star:maybe', (new Context)->searchTerms(["star:maybe"])],
            'Non-boolean pub tag'                 => ['pub:maybe', (new Context)->searchTerms(["pub:maybe"])],
            'Non-boolean note tag'                => ['note:maybe', (new Context)->annotationTerms(["maybe"])],
            'Valueless unread tag'                => ['unread:', (new Context)->searchTerms(["unread:"])],
            'Valueless star tag'                  => ['star:', (new Context)->searchTerms(["star:"])],
            'Valueless pub tag'                   => ['pub:', (new Context)->searchTerms(["pub:"])],
            'Valueless note tag'                  => ['note:', (new Context)->searchTerms(["note:"])],
            'Valueless title tag'                 => ['title:', (new Context)->searchTerms(["title:"])],
            'Valueless author tag'                => ['author:', (new Context)->searchTerms(["author:"])],
            'Escaped quote 1'                     => ['"""I say, Jeeves!"""', (new Context)->searchTerms(["\"i say, jeeves!\""])],
            'Escaped quote 2'                     => ['"\\"I say, Jeeves!\\""', (new Context)->searchTerms(["\"i say, jeeves!\""])],
            'Escaped quote 3'                     => ['\\"I say, Jeeves!\\"', (new Context)->searchTerms(["\\\"i", "say,", "jeeves!\\\""])],
            'Escaped quote 4'                     => ['"\\"\\I say, Jeeves!\\""', (new Context)->searchTerms(["\"\\i say, jeeves!\""])],
            'Escaped quote 5'                     => ['"\\I say, Jeeves!"', (new Context)->searchTerms(["\\i say, jeeves!"])],
            'Escaped quote 6'                     => ['"\\"I say, Jeeves!\\', (new Context)->searchTerms(["\"i say, jeeves!\\"])],
            'Escaped quote 7'                     => ['"\\', (new Context)->searchTerms(["\\"])],
            'Quoted author tag 1'                 => ['"author:Neal Stephenson"', (new Context)->authorTerms(["neal stephenson"])],
            'Quoted author tag 2'                 => ['"author:Jo ""Cap\'n Tripps"" Ashburn"', (new Context)->authorTerms(["jo \"cap'n tripps\" ashburn"])],
            'Quoted author tag 3'                 => ['"author:Jo \\"Cap\'n Tripps\\" Ashburn"', (new Context)->authorTerms(["jo \"cap'n tripps\" ashburn"])],
            'Quoted author tag 4'                 => ['"author:Jo ""Cap\'n Tripps"Ashburn"', (new Context)->authorTerms(["jo \"cap'n trippsashburn\""])],
            'Quoted author tag 5'                 => ['"author:Jo ""Cap\'n Tripps\ Ashburn"', (new Context)->authorTerms(["jo \"cap'n tripps\\ ashburn"])],
            'Quoted author tag 6'                 => ['"author:Neal Stephenson\\', (new Context)->authorTerms(["neal stephenson\\"])],
            'Quoted title tag'                    => ['"title:Generic title"', (new Context)->titleTerms(["generic title"])],
            'Contradictory booleans'              => ['unread:true -unread:true', null],
            'Doubled boolean'                     => ['unread:true unread:true', (new Context)->unread(true)],
            'Bare blank date'                     => ['@', new Context],
            'Quoted blank date'                   => ['"@"', new Context],
            'Bare ISO date'                       => ['@2019-03-01', (new Context)->modifiedRanges([["2019-03-01T00:00:00Z", "2019-03-01T23:59:59Z"]])],
            'Quoted ISO date'                     => ['"@March 1st, 2019"', (new Context)->modifiedRanges([["2019-03-01T00:00:00Z", "2019-03-01T23:59:59Z"]])],
            'Bare negative ISO date'              => ['-@2019-03-01', (new Context)->not->modifiedRanges([["2019-03-01T00:00:00Z", "2019-03-01T23:59:59Z"]])],
            'Quoted negative English date'        => ['"-@March 1st, 2019"', (new Context)->not->modifiedRanges([["2019-03-01T00:00:00Z", "2019-03-01T23:59:59Z"]])],
            'Invalid date'                        => ['@Bugaboo', new Context],
            'Escaped quoted date 1'               => ['"@""Yesterday" and today', (new Context)->searchTerms(["and", "today"])],
            'Escaped quoted date 2'               => ['"@\\"Yesterday" and today', (new Context)->searchTerms(["and", "today"])],
            'Escaped quoted date 3'               => ['"@Yesterday\\', new Context],
            'Escaped quoted date 4'               => ['"@Yesterday\\and today', new Context],
            'Escaped quoted date 5'               => ['"@Yesterday"and today', (new Context)->searchTerms(["today"])],
            'Contradictory dates'                 => ['@2010-01-01 @2015-01-01', (new Context)->modifiedRanges([["2010-01-01T00:00:00Z", "2010-01-01T23:59:59Z"], ["2015-01-01T00:00:00Z", "2015-01-01T23:59:59Z"]])], // This differs from TTRSS' behaviour
            'Doubled date'                        => ['"@March 1st, 2019" @2019-03-01', (new Context)->modifiedRanges([["2019-03-01T00:00:00Z", "2019-03-01T23:59:59Z"]])],
            'Doubled negative date'               => ['"-@March 1st, 2019" -@2019-03-01', (new Context)->not->modifiedRanges([["2019-03-01T00:00:00Z", "2019-03-01T23:59:59Z"]])],
        ];
    }


    #[DataProvider('provideSearchStrings')]
    public function testApplySearchToContext(string $search, $exp): void {
        $act = Search::parse($search, "UTC");
        $this->assertEquals($exp, $act);
    }

    public function testApplySearchToContextWithTimeZone() {
        $act = Search::parse("@2022-02-02", "America/Toronto");
        $exp = (new Context)->modifiedRanges([["2022-02-02T05:00:00Z", "2022-02-03T04:59:59Z"]]);
        $this->assertEquals($exp, $act);
    }
}
