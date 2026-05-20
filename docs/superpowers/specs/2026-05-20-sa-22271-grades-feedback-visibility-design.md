# SA-22271 Grades Feedback Visibility Design

## Goal

Staff can view a student's grades pages as staff, student, or parent so the visible activities, marks, averages, graph data, and feedback match the selected audience as closely as the existing server-side visibility rules allow.

## Context

`/learning/grades/{userId}` and `/learning/grades/{userId}/{termCode}/{folderId}` currently show a staff-only dropdown that switches between instructor/input marks and learner/display marks in the browser. The server still renders the page as the logged-in staff user. This means activity visibility, feedback visibility, weighted averages, graph tabs, and comments are not recalculated for a student or parent audience.

The grades code already has server-side viewer-aware seams:

- `Schoolbox\Learning\Grades\Helper::filterAssessments(User $viewer, ...)`
- `Schoolbox\Learning\Grades\Helper::buildGraph(Markbook $markbook, User $viewee, User $viewer, ...)`
- `Schoolbox\Learning\Markbook\Markbook::__construct(..., User $viewingUser)`
- `Schoolbox\Learning\Assessment\Visibility::isVisibleToUser(...)`
- `IAssessable::isFeedbackVisibleToUser(User $viewingUser)`
- `Participant::findAuthoritativeVisibleFeedback(User $viewingUser)`

## Approach

Add a grades-specific selected view mode with three values:

- `staff`: the current staff view, including instructor/input mark access.
- `student`: a server-side preview of the selected student viewing their own grades.
- `parent`: a server-side preview of a parent viewing the selected student's grades.

The selected mode should be carried in a query parameter, for example `viewMode=staff|student|parent`, and preserved when navigating between the summary page, subject page, term selector, subject selector, and the AJAX report request.

## Effective Viewer

The controller should resolve an effective grades viewer from the selected view mode:

- `staff`: use the logged-in user.
- `student`: use the viewed student.
- `parent`: use a lightweight effective parent user for role-based mark visibility, or a real parent of the viewed student where the code path requires parent-child checks.

The first implementation should prefer existing APIs over broad framework changes. If a real parent user is available for the viewed student, use that for parent mode. If no parent is available, the UI should still allow staff to inspect parent mark visibility rules by using a minimal parent-role effective user only in code paths that require role type checks. Any code path that depends on `User::isParentOf()` must be handled explicitly.

## Activity And Feedback Visibility

For summary page assessment cards and subject report rows, use the effective viewer when checking:

- whether the activity is visible to the selected audience.
- whether feedback/marks are visible.
- which authoritative feedback comment is visible.

In staff mode, keep existing instructor/input mark rendering. In student and parent modes, render only learner/display marks that pass feedback visibility for the effective viewer.

## Averages And Graphs

Weighted averages and grade cards should be built with a `Markbook` whose viewing user is the effective viewer, so hidden feedback is excluded from statistics.

The subject graph should also use the effective viewer in both graph availability checks and graph data construction. This should make the graph disappear in student or parent mode when LMS/year-level policy says that audience cannot see it, while remaining visible in staff mode.

## UI

Replace the current two-state client-side mark switcher with a staff-only server-side view selector in the header options menu.

The header meta should show the current mode, for example:

- `Viewing as staff`
- `Viewing as student`
- `Viewing as parent`

The menu actions should navigate to the same grades page with the requested `viewMode` query parameter. The existing browser-side hide/show script should be removed or reduced to simple navigation behavior, because the selected mode must be reflected in server-rendered content.

Add a "View Customisations" action on `/learning/grades/{userId}` only. The link should reuse the existing custom participation/customisation route introduced by SA-20909 where possible, filtered to the viewed student and current academic period if the destination supports that filter. If the destination does not yet support direct student-period filtering, the implementation should add only the safest existing link and document any remaining route/filter gap in the implementation plan.

## Testing

Add focused coverage around the existing grades Playwright helper and `markTypes.spec.ts` flow:

- Staff mode still shows instructor/input marks by default.
- Student mode hides staff-only activities/marks and shows display marks.
- Parent mode hides student-only marks and shows parent-visible marks.
- The selected mode persists from summary to subject page and through the AJAX report.
- The customisation link is visible on the summary page and points at the expected filtered destination.

Backend unit tests should be added for any new view-mode resolver or effective-viewer helper so role selection, invalid query values, and no-parent fallback are explicit.

## Risks

Parent mode is the main risk because existing visibility code sometimes checks role type only and sometimes checks parent-child relationships. The implementation should keep that distinction visible in a small helper instead of scattering ad hoc role mutation through the controller.

Graph and average changes may expose assumptions where `Markbook` and `ClassResults` use the logged-in user for staff capabilities. Tests should pin the intended behavior before changing those call sites.
