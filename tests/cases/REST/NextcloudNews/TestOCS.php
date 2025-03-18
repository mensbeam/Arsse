<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\REST\NextcloudNews;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Misc\HTTP;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\REST\NextcloudNews\OCS;
use JKingWeb\Arsse\User\ExceptionConflict;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(\JKingWeb\Arsse\REST\NextcloudNews\OCS::class)]
class TestOCS extends \JKingWeb\Arsse\Test\AbstractTest {
    protected $h;
    protected $userId;
    protected $now;

    protected function req(string $method, string $target, $data = "", array $headers = [], bool $authenticated = true, bool $body = true): ResponseInterface {
        $prefix = "/ocs/v1.php/cloud/users/";
        $url = $prefix.$target;
        if ($body) {
            $params = [];
        } else {
            $params = $data;
            $data = [];
        }
        $req = $this->serverRequest($method, $url, $prefix, $headers, [], $data, "application/json", $params, $authenticated ? "john.doe@example.com" : "");
        return $this->h->dispatch($req);
    }

    public function setUp(): void {
        parent::setUp();
        self::setConf();
        // create a mock user manager
        $this->userId = "john.doe@example.com";
        Arsse::$user = \Phake::mock(User::class);
        Arsse::$user->id = $this->userId;
        \Phake::when(Arsse::$user)->auth->thenReturn(true);
        \Phake::when(Arsse::$user)->propertiesGet($this->userId, $this->anything())->thenReturn(['admin' => true, 'lang' => "en_CA"]);
        \Phake::when(Arsse::$user)->propertiesGet("jane.doe@example.com", $this->anything())->thenReturn(['admin' => false, 'lang' => null]);
        // produce consistent timestamps
        $this->now = new \DateTimeImmutable();
        \Phake::when(Arsse::$obj)->get(\DateTimeImmutable::class)->thenReturn($this->now);
        // initialize a handler
        $this->h = new OCS();
    }

    protected static function v($value) {
        return $value;
    }

    public function testSendAuthenticationChallenge(): void {
        $exp = HTTP::respEmpty(401);
        $this->assertMessage($exp, $this->req("GET", $this->userId, "", [], false));
    }

    public function testSendOptionsRequest(): void {
        $exp = HTTP::challenge(HTTP::respEmpty(204, ['Allow' => "GET,HEAD", 'Vary' => "Accept"]));
        $this->assertMessage($exp, $this->req("OPTIONS", $this->userId, "", [], false));
    }

    public function testSendBadRequest(): void {
        $exp = HTTP::respEmpty(405, ['Allow' => "GET,HEAD", 'Vary' => "Accept"]);
        $this->assertMessage($exp, $this->req("PUT", $this->userId, "", [], false));
    }

    public function testQuerySelf(): void {
        $now = $this->now->getTimestamp();
        $user = $this->userId;
        $exp = HTTP::respXml("<ocs><meta><status>ok</status><statuscode>200</statuscode><message>OK</message></meta><data><enabled>1</enabled><storageLocation>/</storageLocation><id>$user</id><firstLoginTimestamp>-1</firstLoginTimestamp><lastLoginTimestamp>$now</lastLoginTimestamp><lastLogin>{$now}000</lastLogin><backend>Database</backend><subadmin/><quota><free>-3</free><used>0</used><total>-3</total><relative>0</relative><quota>-3</quota></quota><manager/><avatarScope>v2-federated</avatarScope><email/><emailScope>v2-federated</emailScope><additional_mail/><additional_mailScope/><displayname>$user</displayname><display-name>$user</display-name><displaynameScope/><phone/><phoneScope>v2-local</phoneScope><address/><addressScope>v2-local</addressScope><website/><websiteScope>v2-local</websiteScope><twitter/><twitterScope>v2-local</twitterScope><fediverse/><fediverseScope>v2-local</fediverseScope><organisation/><organisationScope>v2-local</organisationScope><role/><roleScope>v2-local</roleScope><headline/><headlineScope>v2-local</headlineScope><biography/><biographyScope>v2-local</biographyScope><profile_enabled>0</profile_enabled><profile_enabledScope>v2-local</profile_enabledScope><pronouns/><pronounsScope>v2-federated</pronounsScope><groups><element>admin</element></groups><language>en_CA</language><locale/><notify_email/><backendCapabilities><setDisplayName/><setPassword/></backendCapabilities></data></ocs>", 200);
        $this->assertMessage($exp, $this->req("GET", $user));
        $exp = HTTP::respJson(['ocs' => ['meta' => ['status' => "ok", 'statuscode' => 200, 'message' => "OK"], 'data' => ['enabled' => true,'storageLocation' => '/','id' => $user,'firstLoginTimestamp' => -1,'lastLoginTimestamp' => $now,'lastLogin' => $now * 1000,'backend' => 'Database','subadmin' => [],'quota' => ['free' => -3,'used' => 0,'total' => -3,'relative' => 0,'quota' => -3],'manager' => '','avatarScope' => 'v2-federated','email' => null,'emailScope' => 'v2-federated','additional_mail' => [],'additional_mailScope' => [],'displayname' => $user,'display-name' => $user,'displaynameScope' => null,'phone' => '','phoneScope' => 'v2-local','address' => '','addressScope' => 'v2-local','website' => '','websiteScope' => 'v2-local','twitter' => '','twitterScope' => 'v2-local','fediverse' => '','fediverseScope' => 'v2-local','organisation' => '','organisationScope' => 'v2-local','role' => '','roleScope' => 'v2-local','headline' => '','headlineScope' => 'v2-local','biography' => '','biographyScope' => 'v2-local','profile_enabled' => '0','profile_enabledScope' => 'v2-local','pronouns' => '','pronounsScope' => 'v2-federated','groups' => ['admin'],'language' => 'en_CA','locale' => '','notify_email' => null,'backendCapabilities' => ['setDisplayName' => false,'setPassword' => false]]]], 200);
        $this->assertMessage($exp, $this->req("GET", $user, "", ['Accept' => "application/json"]));
    }

    public function testQueryAnotherUser(): void {
        $now = $this->now->getTimestamp();
        $user = "jane.doe@example.com";
        $exp = HTTP::respXml("<ocs><meta><status>ok</status><statuscode>200</statuscode><message>OK</message></meta><data><enabled>1</enabled><storageLocation>/</storageLocation><id>$user</id><firstLoginTimestamp>-1</firstLoginTimestamp><lastLoginTimestamp>$now</lastLoginTimestamp><lastLogin>{$now}000</lastLogin><backend>Database</backend><subadmin/><quota><free>-3</free><used>0</used><total>-3</total><relative>0</relative><quota>-3</quota></quota><manager/><avatarScope>v2-federated</avatarScope><email/><emailScope>v2-federated</emailScope><additional_mail/><additional_mailScope/><displayname>$user</displayname><display-name>$user</display-name><displaynameScope/><phone/><phoneScope>v2-local</phoneScope><address/><addressScope>v2-local</addressScope><website/><websiteScope>v2-local</websiteScope><twitter/><twitterScope>v2-local</twitterScope><fediverse/><fediverseScope>v2-local</fediverseScope><organisation/><organisationScope>v2-local</organisationScope><role/><roleScope>v2-local</roleScope><headline/><headlineScope>v2-local</headlineScope><biography/><biographyScope>v2-local</biographyScope><profile_enabled>0</profile_enabled><profile_enabledScope>v2-local</profile_enabledScope><pronouns/><pronounsScope>v2-federated</pronounsScope><groups></groups><language>en</language><locale/><notify_email/><backendCapabilities><setDisplayName/><setPassword/></backendCapabilities></data></ocs>", 200);
        $this->assertMessage($exp, $this->req("GET", $user));
        $exp = HTTP::respJson(['ocs' => ['meta' => ['status' => "ok", 'statuscode' => 200, 'message' => "OK"], 'data' => ['enabled' => true,'storageLocation' => '/','id' => $user,'firstLoginTimestamp' => -1,'lastLoginTimestamp' => $now,'lastLogin' => $now * 1000,'backend' => 'Database','subadmin' => [],'quota' => ['free' => -3,'used' => 0,'total' => -3,'relative' => 0,'quota' => -3],'manager' => '','avatarScope' => 'v2-federated','email' => null,'emailScope' => 'v2-federated','additional_mail' => [],'additional_mailScope' => [],'displayname' => $user,'display-name' => $user,'displaynameScope' => null,'phone' => '','phoneScope' => 'v2-local','address' => '','addressScope' => 'v2-local','website' => '','websiteScope' => 'v2-local','twitter' => '','twitterScope' => 'v2-local','fediverse' => '','fediverseScope' => 'v2-local','organisation' => '','organisationScope' => 'v2-local','role' => '','roleScope' => 'v2-local','headline' => '','headlineScope' => 'v2-local','biography' => '','biographyScope' => 'v2-local','profile_enabled' => '0','profile_enabledScope' => 'v2-local','pronouns' => '','pronounsScope' => 'v2-federated','groups' => [],'language' => 'en','locale' => '','notify_email' => null,'backendCapabilities' => ['setDisplayName' => false,'setPassword' => false]]]], 200);
        $this->assertMessage($exp, $this->req("GET", $user, "", ['Accept' => "application/json"]));
    }

    public function testQueryAnotherUserWithoutAuthority(): void {
        \Phake::when(Arsse::$user)->propertiesGet($this->userId, $this->anything())->thenReturn(['admin' => false, 'lang' => "en_CA"]);
        $user = "jane.doe@example.com";
        $exp = HTTP::respXml("<ocs><meta><status>failure</status><statuscode>998</statuscode><message></message></meta><data></data></ocs>", 404);
        $this->assertMessage($exp, $this->req("GET", $user));
        $exp = HTTP::respJson(['ocs' => ['meta' => ['status' => "failure", 'statuscode' => 998, 'message' => ""], 'data' => []]], 404);
        $this->assertMessage($exp, $this->req("GET", $user, "", ['Accept' => "application/json"]));
    }

    public function testQueryAMissingUser(): void {
        \Phake::when(Arsse::$user)->propertiesGet("oops", $this->anything())->thenThrow(new ExceptionConflict());
        $exp = HTTP::respXml("<ocs><meta><status>failure</status><statuscode>404</statuscode><message>User does not exist</message></meta><data></data></ocs>", 404);
        $this->assertMessage($exp, $this->req("GET", "oops"));
        $exp = HTTP::respJson(['ocs' => ['meta' => ['status' => "failure", 'statuscode' => 404, 'message' => "User does not exist"], 'data' => []]], 404);
        $this->assertMessage($exp, $this->req("GET", "oops", "", ['Accept' => "application/json"]));
    }
}
