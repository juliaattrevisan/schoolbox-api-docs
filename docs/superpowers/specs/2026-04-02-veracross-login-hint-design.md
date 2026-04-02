# Veracross: Add login_hint to Attendance Link

**Jira:** SA-21944  
**Date:** 2026-04-02

## Problem

When a staff member clicks the Veracross attendance deep link, they are prompted to supply their email address before being redirected to the attendance page. Veracross supports a `login_hint` query parameter that pre-fills the email, bypassing this prompt and taking the user directly to the attendance page.

## Solution

Append `login_hint=<email>` to the attendance URL in `AttendanceLink::generate()`, using the currently logged-in user's email address. If the user has no email, the URL is returned unchanged (attendance still works, user just sees the prompt).

## Files Changed

### `library/Schoolbox/External/Connector/Veracross/Base/Endpoint/Codec/AttendanceLink.php`

After retrieving the URL from `Reader`, append the `login_hint` query parameter:

```php
$url = (string)(new Reader())->getAttendanceLink($event->getMetadata());

if ($url !== '' && $user->hasEmail()) {
    $separator = str_contains($url, '?') ? '&' : '?';
    $url .= $separator . http_build_query(['login_hint' => $user->getEmail()]);
}

return $url;
```

- `http_build_query` handles URL-encoding of the email address.
- `str_contains($url, '?')` safely handles URLs that already have query parameters.
- If `$user->hasEmail()` is false, the URL is returned as-is.

## Testing

New test class: `library/Schoolbox/External/Connector/Veracross/Base/Endpoint/Codec/Test/AttendanceLinkTest.php`

| Case | Expected outcome |
|---|---|
| User has email, URL has no query string | `login_hint=<email>` appended with `?` |
| User has email, URL already has query params | `login_hint=<email>` appended with `&` |
| User has no email | URL returned unchanged (no `login_hint`) |
| Attendance disabled | Empty string (existing behaviour) |
| Non-Veracross event source | Empty string (existing behaviour) |
| No metadata on event | Empty string (existing behaviour) |

## Out of Scope

- `provider_hint` parameter (hardcoded IdP) — per the Veracross release notes this is only appropriate when all users share a single IdP. `login_hint` is the preferred approach for schools with multiple IdPs.
- Changes to how the URL base is stored or built during timetable import.
