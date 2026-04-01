<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Api\Groups;

use Tests\Support\ApiTester;

/**
 * /usr/share/schoolbox/vendor/bin/codecept run api Groups/GroupsSearchCest
 */
class GroupsSearchCest
{
    private array $users;

    public function _before(ApiTester $i): void
    {
        $this->users = $i->getUsers();
    }

    /**
     * Only superusers may access the endpoint.
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function testRequiresSuperuser(ApiTester $i): void
    {
        foreach ($this->users as $user) {
            $i->switchUser($user);
            $i->sendGet('/groups/search', []);

            if ($user->isSuperuser()) {
                $i->seeResponseCodeIs(200);
            } else {
                $i->seeResponseCodeIs(401);
            }
        }
    }

    /**
     * Response shape: results array with correct field types, plus top-level next key.
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function testResponseShape(ApiTester $i): void
    {
        $i->switchUser($this->users['super']);
        $groupFolder = $i->createGroup($this->users['super'], $this->users['staff']);
        $groupId     = $groupFolder->getId();

        $i->sendGet('/groups/search', ['q' => 'GROUP']);
        $i->seeResponseCodeIs(200);

        // Top-level response must contain both keys.
        $response = $i->grabDataFromResponseByJsonPath('$')[0];
        $i->assertArrayHasKey('results', $response);
        $i->assertArrayHasKey('next', $response);

        // Find the specific group we just created.
        $results  = $i->grabDataFromResponseByJsonPath('$.results')[0];
        $matching = array_values(array_filter($results, fn($r) => $r['id'] === $groupId));
        $i->assertCount(1, $matching, 'Created group must appear in results');

        $group = $matching[0];
        $i->assertArrayHasKey('id', $group);
        $i->assertArrayHasKey('name', $group);
        $i->assertArrayHasKey('codes', $group);
        $i->assertArrayHasKey('type', $group);
        $i->assertArrayHasKey('url', $group);
        $i->assertArrayHasKey('memberCount', $group);
        $i->assertIsArray($group['codes']);
        $i->assertIsInt($group['id']);
        $i->assertIsInt($group['memberCount']);
        $i->assertStringStartsWith('/groups/', $group['url']);
    }

    /**
     * q param filters by partial name match.
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function testNameFilter(ApiTester $i): void
    {
        $i->switchUser($this->users['super']);
        $groupFolder = $i->createGroup($this->users['super'], $this->users['staff']);
        $groupId     = $groupFolder->getId();

        // All test groups are named "GROUP ..." by the createGroup helper.
        $i->sendGet('/groups/search', ['q' => 'GROUP']);
        $i->seeResponseCodeIs(200);
        $results  = $i->grabDataFromResponseByJsonPath('$.results')[0];
        $matching = array_values(array_filter($results, fn($r) => $r['id'] === $groupId));
        $i->assertCount(1, $matching, 'Created group must appear when searching by its partial name');

        // A search that should return nothing.
        $i->sendGet('/groups/search', ['q' => 'zzz_no_match_' . uniqid()]);
        $i->seeResponseCodeIs(200);
        $empty = $i->grabDataFromResponseByJsonPath('$.results')[0];
        $i->assertEmpty($empty);
    }

    /**
     * type param filters by join type (free/moderated/private).
     * createGroup() always creates FREE_GROUP groups.
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function testTypeFilter(ApiTester $i): void
    {
        $i->switchUser($this->users['super']);
        $groupFolder = $i->createGroup($this->users['super'], $this->users['staff']);
        $groupId     = $groupFolder->getId();

        $i->sendGet('/groups/search', ['type' => 'free', 'q' => 'GROUP']);
        $i->seeResponseCodeIs(200);
        $results = $i->grabDataFromResponseByJsonPath('$.results')[0];

        // Every returned result must be a free group.
        foreach ($results as $result) {
            $i->assertEquals('free', $result['type']);
        }

        // The specific group we created must appear in the filtered results.
        $matching = array_values(array_filter($results, fn($r) => $r['id'] === $groupId));
        $i->assertCount(1, $matching, 'Created free group must appear when filtering by type=free');

        // Invalid type value returns 400.
        $i->sendGet('/groups/search', ['type' => 'invalid_type']);
        $i->seeResponseCodeIs(400);
    }

    /**
     * limit + cursor pagination works correctly.
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function testPagination(ApiTester $i): void
    {
        $i->switchUser($this->users['super']);
        $i->createGroup($this->users['super'], $this->users['staff']);
        $i->createGroup($this->users['super'], $this->users['staff']);
        $i->createGroup($this->users['super'], $this->users['staff']);

        // First page: limit=1, expect a next cursor.
        $i->sendGet('/groups/search', ['limit' => 1]);
        $i->seeResponseCodeIs(200);
        $page1Results = $i->grabDataFromResponseByJsonPath('$.results')[0];
        $i->assertCount(1, $page1Results);
        $page1Id = $page1Results[0]['id'];

        $next = $i->grabDataFromResponseByJsonPath('$.next')[0];
        $i->assertNotNull($next);
        $i->assertNotEmpty($next);

        // Second page: use the cursor, still limit=1.
        $i->sendGet('/groups/search', ['limit' => 1, 'cursor' => $next]);
        $i->seeResponseCodeIs(200);
        $page2Results = $i->grabDataFromResponseByJsonPath('$.results')[0];
        $i->assertCount(1, $page2Results);

        // Page 2 must return a different group than page 1.
        $i->assertNotEquals($page1Id, $page2Results[0]['id'], 'Page 2 must return a different group than page 1');

        // Invalid limit values return 400.
        $i->sendGet('/groups/search', ['limit' => 'abc']);
        $i->seeResponseCodeIs(400);

        $i->sendGet('/groups/search', ['limit' => 0]);
        $i->seeResponseCodeIs(400);
    }

    /**
     * memberCount reflects active members (ACCESS_READ and above).
     * createGroup() adds exactly 2 members: one ACCESS_FULL, one ACCESS_READ.
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function testMemberCount(ApiTester $i): void
    {
        $i->switchUser($this->users['super']);
        $groupFolder = $i->createGroup($this->users['super'], $this->users['staff']);
        $groupId     = $groupFolder->getId();

        // Use q=GROUP to narrow the result set to test groups only, avoiding
        // pagination issues if the DB has many pre-existing groups with lower ids.
        $i->sendGet('/groups/search', ['q' => 'GROUP', 'limit' => 200]);
        $i->seeResponseCodeIs(200);

        $results  = $i->grabDataFromResponseByJsonPath('$.results')[0];
        $matching = array_values(array_filter($results, fn($r) => $r['id'] === $groupId));
        $i->assertCount(1, $matching, 'Created group must appear in results');
        $i->assertEquals(2, $matching[0]['memberCount'], 'memberCount must reflect both active members');
    }
}
