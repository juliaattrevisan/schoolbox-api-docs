# Design: GET /groups/search API Endpoint

**Jira:** SA-22075  
**Date:** 2026-04-01  
**Status:** Approved

---

## Background

Paperly (an external developer) needs to retrieve a full list of all groups in Schoolbox to power a group-assignment workflow. The existing `GET /search` API requires full-word matches and introduces latency, making it unsuitable for a responsive filtered UI.

This endpoint provides a paginated, searchable list of all groups in the system.

---

## Endpoint

```
GET /groups/search
Authorization: Bearer <JWT>
```

**Authentication:** Requires a valid JWT token for a superuser account (`IsSuperuser` rule).

---

## Query Parameters

| Parameter | Type   | Required | Description |
|-----------|--------|----------|-------------|
| `q`       | string | No       | Partial name match (LIKE `%q%`) |
| `type`    | string | No       | Filter by group join type: `private`, `moderated`, or `free` |
| `limit`   | int    | No       | Number of results per page. Defaults to `50`. |
| `cursor`  | string | No       | Opaque cursor for fetching the next page |

---

## Response Schema

```json
{
  "results": [
    {
      "id": 123,
      "name": "First XI Basketball",
      "codes": ["BASK-01"],
      "type": "moderated",
      "url": "/groups/123",
      "memberCount": 15
    }
  ],
  "next": "<encoded cursor string, or null if no further pages>"
}
```

### Field Notes

- `codes` — array of all codes associated with the group folder (a group can have zero or more codes). Sourced from `Folder\Model::getCodes()`.
- `type` — maps to `Group::TYPE_VAL_TO_NAME`: `private`, `moderated`, or `free`.
- `url` — relative URL to the group homepage, formatted as `/groups/{id}`.
- `memberCount` — count of users with `access_level >= ACCESS_READ` (i.e. `>= 1`) in `user_access` for this group. Excludes pending (`ACCESS_AWAITING_AUTHORISATION = 0`) and denied (`ACCESS_DENY = 0`) users. Computed via a COUNT subquery (not N+1).
- `next` — opaque Base64-encoded cursor pointing to the next page. `null` when there are no further results.

---

## Architecture

### Files Modified

**`library/Schoolbox/Group/Module.php`**  
Add a new route registration:
```php
$hasApiAccess = new Rule\All(new Rule\IsLoggedIn(), new Rule\IsSuperuser());
$this->register('GET', '/search', 'apiGetGroups', $hasApiAccess);
```
This sits alongside the existing `IsLoggedIn` routes with no conflict.

**`library/Schoolbox/Group/Controller.php`**  
Add `apiGetGroups()` method. Reads `q`, `type`, `limit`, and `cursor` from the query string, calls `GroupMapper::findAllForApi()`, and returns a JSON response with `results` and `next`.

**`library/Schoolbox/Resource/GroupMapper.php`**  
Add `findAllForApi(string $q, ?string $type, int $limit, ?string $cursor): array` method. Returns both the result collection and the next cursor value.

### No New Files

No new module, no changes to `conf/modules/API.php`. The route is registered within the existing `Schoolbox\Group\Module` which is already mounted at `/groups`.

---

## Data Access

### Query Design

`GroupMapper::findAllForApi()` executes a single SQL query with:

- `INNER JOIN` to `folders` (already part of `GroupMapper`'s base query)
- `LEFT JOIN` with a COUNT subquery against `user_access` for `memberCount`, filtered to `service = SERVICE_HOMEPAGE` and non-zero access level
- `WHERE f.name LIKE :q` when `q` is provided
- `WHERE ug.type = :type` when `type` is provided (mapped from string to int via `Group::TYPE_NAME_TO_VAL`)
- `ORDER BY f.id ASC` for stable cursor pagination
- Cursor applied as `WHERE f.id > :cursor` when a cursor value is present
- `LIMIT :limit`

### Cursor Encoding

The cursor encodes the folder `id` of the last result, Base64-encoded as JSON (consistent with the existing `MapperCursor` codec pattern). The `next` value in the response is `null` when the result count is less than `limit`.

---

## Error Handling

| Condition | HTTP Status | Response |
|-----------|-------------|----------|
| Not authenticated | 403 | Handled by `IsSuperuser` rule before controller is reached |
| Invalid `type` value | 400 | JSON error: `"Invalid type value"` |
| Invalid `limit` value (non-integer or <= 0) | 400 | JSON error: `"Invalid limit value"` |
| Database error | 500 | JSON error: `"Failed to retrieve groups"` |

---

## Out of Scope

- Creating, updating, or deleting groups via this endpoint
- Returning group membership details for a specific user (covered by `GET /api/user/{userId}/group`)
- A `type` filter based on school category (class/house/extracurricular) — no such categorisation exists in the data model
