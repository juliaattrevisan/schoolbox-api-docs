# Groups Search API Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement `GET /groups/search` — a paginated, filterable API endpoint that returns all groups in the system, accessible to superusers via JWT.

**Architecture:** Register a new route on the existing `Schoolbox\Group\Module` at `/groups/search` with `IsSuperuser` auth. The existing `Schoolbox\Group\Controller` handles the request, reading query params and delegating to a new `GroupMapper::findAllForApi()` method that executes a single SQL query with optional name/type filters, cursor pagination, and a member COUNT subquery.

**Tech Stack:** PHP 8.4, Aura Router, PDO/MariaDB, Codeception API tests

---

## File Map

| File | Change |
|---|---|
| `library/Schoolbox/Group/Module.php` | Add `GET /search` route with `IsSuperuser` |
| `library/Schoolbox/Resource/Group.php` | Add `$memberCount` property with getter/setter |
| `library/Schoolbox/Resource/GroupMapper.php` | Add `findAllForApi()` method |
| `library/Schoolbox/Group/Controller.php` | Add `apiGetGroups()` method |
| `test/codeception/Api/Groups/GroupsSearchCest.php` | New Codeception API test |

---

## Task 1: Write the failing Codeception test

**Files:**
- Create: `test/codeception/Api/Groups/GroupsSearchCest.php`

- [ ] **Step 1: Create the test file**

```php
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
     * Response shape: results array + next cursor.
     */
    public function testResponseShape(ApiTester $i): void
    {
        $i->switchUser($this->users['super']);
        $i->createGroup($this->users['super'], $this->users['staff']);

        $i->sendGet('/groups/search', []);
        $i->seeResponseCodeIs(200);

        $results = $i->grabDataFromResponseByJsonPath('$.results')[0];
        $i->assertNotEmpty($results);

        $first = $results[0];
        $i->assertArrayHasKey('id', $first);
        $i->assertArrayHasKey('name', $first);
        $i->assertArrayHasKey('codes', $first);
        $i->assertArrayHasKey('type', $first);
        $i->assertArrayHasKey('url', $first);
        $i->assertArrayHasKey('memberCount', $first);
        $i->assertIsArray($first['codes']);
        $i->assertIsInt($first['id']);
        $i->assertIsInt($first['memberCount']);
        $i->assertStringStartsWith('/groups/', $first['url']);
    }

    /**
     * q param filters by partial name match.
     */
    public function testNameFilter(ApiTester $i): void
    {
        $i->switchUser($this->users['super']);
        $i->createGroup($this->users['super'], $this->users['staff']);

        // All test groups are named "GROUP ..." by the createGroup helper.
        $i->sendGet('/groups/search', ['q' => 'GROUP']);
        $i->seeResponseCodeIs(200);
        $results = $i->grabDataFromResponseByJsonPath('$.results')[0];
        $i->assertNotEmpty($results);

        // A search that should return nothing.
        $i->sendGet('/groups/search', ['q' => 'zzz_no_match_' . uniqid()]);
        $i->seeResponseCodeIs(200);
        $empty = $i->grabDataFromResponseByJsonPath('$.results')[0];
        $i->assertEmpty($empty);
    }

    /**
     * type param filters by join type (free/moderated/private).
     * createGroup() always creates FREE_GROUP groups.
     */
    public function testTypeFilter(ApiTester $i): void
    {
        $i->switchUser($this->users['super']);
        $i->createGroup($this->users['super'], $this->users['staff']);

        $i->sendGet('/groups/search', ['type' => 'free']);
        $i->seeResponseCodeIs(200);
        $results = $i->grabDataFromResponseByJsonPath('$.results')[0];
        $i->assertNotEmpty($results);
        foreach ($results as $result) {
            $i->assertEquals('free', $result['type']);
        }

        // Invalid type value returns 400.
        $i->sendGet('/groups/search', ['type' => 'invalid_type']);
        $i->seeResponseCodeIs(400);
    }

    /**
     * limit + cursor pagination works correctly.
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
        $next = $i->grabDataFromResponseByJsonPath('$.next')[0];
        $i->assertNotNull($next);
        $i->assertNotEmpty($next);

        // Second page: use the cursor, still limit=1.
        $i->sendGet('/groups/search', ['limit' => 1, 'cursor' => $next]);
        $i->seeResponseCodeIs(200);
        $results = $i->grabDataFromResponseByJsonPath('$.results')[0];
        $i->assertCount(1, $results);

        // Invalid limit returns 400.
        $i->sendGet('/groups/search', ['limit' => 'abc']);
        $i->seeResponseCodeIs(400);
    }

    /**
     * memberCount reflects active members (ACCESS_READ and above).
     * createGroup() adds exactly 2 members: one ACCESS_FULL, one ACCESS_READ.
     */
    public function testMemberCount(ApiTester $i): void
    {
        $i->switchUser($this->users['super']);
        $groupFolder = $i->createGroup($this->users['super'], $this->users['staff']);
        $groupId     = $groupFolder->getId();

        $i->sendGet('/groups/search', []);
        $i->seeResponseCodeIs(200);

        $results  = $i->grabDataFromResponseByJsonPath('$.results')[0];
        $matching = array_values(array_filter($results, fn($r) => $r['id'] === $groupId));
        $i->assertCount(1, $matching);
        $i->assertEquals(2, $matching[0]['memberCount']);
    }
}
```

- [ ] **Step 2: Run the test to confirm it fails with 404**

```bash
/usr/share/schoolbox/vendor/bin/codecept run api Groups/GroupsSearchCest
```

Expected: All tests fail. `testRequiresSuperuser` gets 404 (route doesn't exist yet).

---

## Task 2: Register the route in `Group\Module`

**Files:**
- Modify: `library/Schoolbox/Group/Module.php`

- [ ] **Step 1: Add `IsSuperuser` route to `registerRoutes()`**

In `library/Schoolbox/Group/Module.php`, add the new route inside `registerRoutes()`, after the existing `$loggedInAccess` routes:

```php
public function registerRoutes(): void
{
    parent::registerRoutes(); // TODO: Change the autogenerated stub
    $loggedInAccess = new Rule\IsLoggedIn();
    $this->register('GET', '', 'getNewGroups', $loggedInAccess);
    $this->register('POST', '/add', 'postMyGroupAdd', $loggedInAccess);
    $this->register('POST', '/delete', 'postMyGroupDelete', $loggedInAccess);
    $this->register('POST', '/notify', 'postMyGroupNotify', $loggedInAccess);
    $this->register('POST', '/favourite', 'postMyGroupFavourite', $loggedInAccess);

    $hasApiAccess = new Rule\All(new Rule\IsLoggedIn(), new Rule\IsSuperuser());
    $this->register('GET', '/search', 'apiGetGroups', $hasApiAccess);
}
```

- [ ] **Step 2: Run the test to confirm it now fails differently**

```bash
/usr/share/schoolbox/vendor/bin/codecept run api Groups/GroupsSearchCest
```

Expected: `testRequiresSuperuser` now gets 401 for non-superusers (auth works) but a 500 or similar for superuser (method `apiGetGroups` not yet defined on controller).

---

## Task 3: Add `memberCount` to `Group` model

**Files:**
- Modify: `library/Schoolbox/Resource/Group.php`

This is a transient computed property — it is never persisted to the database. The mapper sets it after executing the COUNT query.

- [ ] **Step 1: Add the property and getter/setter to `Group`**

In `library/Schoolbox/Resource/Group.php`, add after the `$notificationDisabled` property and its getter/setter:

```php
/**
 * Transient: total active members for this group, populated by GroupMapper::findAllForApi().
 * Not persisted to the database.
 */
private int $memberCount = 0;

/**
 * @return int
 */
public function getMemberCount(): int
{
    return $this->memberCount;
}

/**
 * @param int $memberCount
 * @return self
 */
public function setMemberCount(int $memberCount): self
{
    $this->memberCount = $memberCount;
    return $this;
}
```

- [ ] **Step 2: Confirm no tests are broken**

```bash
/usr/share/schoolbox/vendor/bin/codecept run api Groups/GroupsSearchCest
```

Expected: Same result as before — the `Group` model change alone does not affect behaviour.

---

## Task 4: Add `findAllForApi()` to `GroupMapper`

**Files:**
- Modify: `library/Schoolbox/Resource/GroupMapper.php`

- [ ] **Step 1: Add the required `use` statement at the top of `GroupMapper`**

`GroupMapper.php` already imports `UserAccess` indirectly through `UserAccessMapper`, but the constant needs to be accessible. Confirm the following `use` is already present (it is):

```php
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
```

- [ ] **Step 2: Add `findAllForApi()` to `GroupMapper`**

Add this method after `getAvailableGroupIds()`:

```php
/**
 * Return a paginated list of groups for the API, with optional name and type filters.
 *
 * @param string   $q      Partial name match. Empty string means no filter.
 * @param int|null $type   Group::PRIVATE_GROUP, MODERATED_GROUP, or FREE_GROUP. Null means no filter.
 * @param int      $limit  Maximum number of results to return.
 * @param int|null $cursor Return only groups with folder id > this value. Null means start from beginning.
 * @return array{results: \Schoolbox\GenericCollection, nextCursor: int|null}
 */
public function findAllForApi(string $q, ?int $type, int $limit, ?int $cursor): array
{
    /** @var \Schoolbox\Resource\Folder\Mapper $folderMapper */
    $folderMapper = $this->getJoinMapper('folder');

    $conditions = [];
    $params     = [
        'service'   => UserAccess::SERVICE_HOMEPAGE,
        'minAccess' => UserAccess::ACCESS_READ,
        'limit'     => $limit,
    ];

    if ($q !== '') {
        $conditions[] = 'f.name LIKE :name';
        $params['name'] = "%{$q}%";
    }

    if ($type !== null) {
        $conditions[] = 'ug.type = :type';
        $params['type'] = $type;
    }

    if ($cursor !== null) {
        $conditions[] = 'f.id > :cursor';
        $params['cursor'] = $cursor;
    }

    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $query = "
        SELECT
            {$this->getColumnString('ug')},
            {$this->getJoinColumnString('folder', 'f')},
            COALESCE(mc.cnt, 0) AS member_count
        FROM `{$this->getTable()}` ug
        INNER JOIN {$this->getJoinSql('folder', 'ug', 'f')}
        LEFT JOIN (
            SELECT sid, COUNT(*) AS cnt
            FROM userAccess
            WHERE service = :service
            AND type >= :minAccess
            GROUP BY sid
        ) AS mc ON mc.sid = f.id
        {$where}
        ORDER BY f.id ASC
        LIMIT :limit
    ";

    $statement = $this->database->prepare($query);
    $statement->execute($params);

    $groups = $this->createCollection();
    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $group = $this->hydrate($row)
            ->setFolder($folderMapper->hydrate($row, 'f'))
            ->setMemberCount((int)$row['member_count']);
        $groups[$group->getFolder()->getId()] = $group;
    }

    $nextCursor = $groups->count() === $limit
        ? $groups->last()->getFolder()->getId()
        : null;

    return ['results' => $groups, 'nextCursor' => $nextCursor];
}
```

- [ ] **Step 3: Run tests to confirm mapper change doesn't break existing tests**

```bash
/usr/share/schoolbox/vendor/bin/codecept run api User/UserGroupMembershipCest
```

Expected: All existing group membership tests pass.

---

## Task 5: Add `apiGetGroups()` to `Group\Controller`

**Files:**
- Modify: `library/Schoolbox/Group/Controller.php`

- [ ] **Step 1: Add the `use` statement for `Group` at the top**

`library/Schoolbox/Group/Controller.php` already imports `Schoolbox\Resource\Group`:

```php
use Schoolbox\Resource\Group;
```

Confirm it is present — no change needed.

- [ ] **Step 2: Add `apiGetGroups()` to the controller**

Add this method after `postMyGroupFavourite()` and before the private helper methods:

```php
/**
 * Returns a paginated list of all groups in the system.
 *
 * @return void
 * @api   GET /groups/search
 * @since 26.1
 */
public function apiGetGroups(): void
{
    $q       = (string)($this->requestQuery->get('q') ?? '');
    $typeStr = $this->requestQuery->get('type');
    $limitRaw = $this->requestQuery->get('limit');
    $cursor  = $this->requestQuery->get('cursor');

    // Validate limit: must be a positive integer string or absent.
    if ($limitRaw !== null && (!ctype_digit((string)$limitRaw) || (int)$limitRaw <= 0)) {
        $this->sendJsonError('Invalid limit value', 400);
        return;
    }
    $limit = $limitRaw !== null ? (int)$limitRaw : 50;

    // Validate type: must be one of the known join type names, or absent.
    $type = null;
    if ($typeStr !== null) {
        if (!array_key_exists($typeStr, Group::TYPE_NAME_TO_VAL)) {
            $this->sendJsonError('Invalid type value', 400);
            return;
        }
        $type = Group::TYPE_NAME_TO_VAL[$typeStr];
    }

    // Decode cursor: opaque base64(json) value encoding the last folder id.
    $cursorId = null;
    if ($cursor !== null && $cursor !== '') {
        $decoded  = json_decode(base64_decode($cursor, true), true);
        $cursorId = isset($decoded['id']) ? (int)$decoded['id'] : null;
    }

    ['results' => $groups, 'nextCursor' => $nextCursor] =
        $this->groupMapper->findAllForApi($q, $type, $limit, $cursorId);

    $results = [];
    foreach ($groups as $group) {
        $folder = $group->getFolder();
        $codes  = [];
        if ($folder->getCodes()) {
            foreach ($folder->getCodes() as $code) {
                if (!$code->isDeleted()) {
                    $codes[] = $code->getCode();
                }
            }
        }
        $results[] = [
            'id'          => $folder->getId(),
            'name'        => $folder->getName(),
            'codes'       => $codes,
            'type'        => $group->getTypeString(),
            'url'         => sprintf('/groups/%d', $folder->getId()),
            'memberCount' => $group->getMemberCount(),
        ];
    }

    $next = $nextCursor !== null
        ? base64_encode(json_encode(['id' => $nextCursor], JSON_THROW_ON_ERROR))
        : null;

    $this->response = $this->responseAsJson([
        'results' => $results,
        'next'    => $next,
    ]);
}
```

- [ ] **Step 3: Run the full test suite for the new endpoint**

```bash
/usr/share/schoolbox/vendor/bin/codecept run api Groups/GroupsSearchCest
```

Expected: All 6 tests pass.

- [ ] **Step 4: Run the existing group membership tests to confirm no regression**

```bash
/usr/share/schoolbox/vendor/bin/codecept run api User/UserGroupMembershipCest
```

Expected: All existing tests pass.

---

## Task 6: Commit

- [ ] **Step 1: Stage and commit all changes**

```bash
git add \
  library/Schoolbox/Resource/Group.php \
  library/Schoolbox/Resource/GroupMapper.php \
  library/Schoolbox/Group/Module.php \
  library/Schoolbox/Group/Controller.php \
  test/codeception/Api/Groups/GroupsSearchCest.php

git commit -m "$(cat <<'EOF'
feat: add GET /groups/search API endpoint [SA-22075]

Adds a paginated, filterable endpoint for listing all groups in the
system. Requires superuser JWT. Supports q (partial name), type
(private/moderated/free), limit, and cursor-based pagination.
Returns id, name, codes[], type, url, and memberCount per group.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

Expected output: 1 commit created on branch `task-develop/SA-22075`.
